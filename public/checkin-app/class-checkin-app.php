<?php
/**
 * Event Check-in PWA
 * Shortcode: [evtmgr_checkin]
 * REST API:
 *   GET  /wp-json/evtmgr/v1/checkin?cookie=VALUE  — person lookup
 *   POST /wp-json/evtmgr/v1/checkin               — perform check-in
 */

if (!defined('ABSPATH')) {
    exit;
}

class Evtmgr_Checkin_App {

    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        add_action('init',            [self::class, 'register_shortcode']);
        add_action('rest_api_init',   [self::class, 'register_rest_routes']);
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────

    public static function register_shortcode(): void {
        add_shortcode('evtmgr_checkin', [self::class, 'render_shortcode']);
    }

    public static function render_shortcode(): string {
        $base_url  = get_stylesheet_directory_uri() . '/db-custom/event-registration/public/checkin-app/';
        $base_path = get_stylesheet_directory()     . '/db-custom/event-registration/public/checkin-app/';

        wp_enqueue_script(
            'html5-qrcode',
            'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
            [],
            '2.3.8',
            true
        );

        wp_enqueue_style(
            'evtmgr-checkin',
            $base_url . 'checkin-app.css',
            [],
            file_exists($base_path . 'checkin-app.css') ? filemtime($base_path . 'checkin-app.css') : '1'
        );

        wp_enqueue_script(
            'evtmgr-checkin',
            $base_url . 'checkin-app.js',
            ['html5-qrcode'],
            file_exists($base_path . 'checkin-app.js') ? filemtime($base_path . 'checkin-app.js') : '1',
            true
        );

        wp_localize_script('evtmgr-checkin', 'evtmgrCheckin', [
            'restUrl'     => rest_url('evtmgr/v1/checkin'),
            'nonce'       => wp_create_nonce('wp_rest'),
            'swUrl'       => $base_url . 'sw.js',
            'manifestUrl' => $base_url . 'manifest.json',
        ]);

        return '<div id="checkin-app" aria-live="polite"></div>'
            . '<link rel="manifest" href="' . esc_url($base_url . 'manifest.json') . '">';
    }

    // ── REST API ──────────────────────────────────────────────────────────────

    public static function register_rest_routes(): void {
        register_rest_route('evtmgr/v1', '/checkin', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'api_lookup'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'cookie' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => fn($v) => is_string($v) && trim($v) !== '',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'api_checkin'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function api_lookup(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        self::maybe_add_columns($wpdb);

        $cookie = sanitize_text_field($request->get_param('cookie'));

        $person = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, str_salutation, str_first_name, str_last_name, str_email,
                        fky_event_uid, ysn_checked_in, dtm_date_check_in
                 FROM {$wpdb->prefix}evtmgr_persons
                 WHERE str_registration_cookie = %s
                 LIMIT 1",
                $cookie
            ),
            ARRAY_A
        );

        if (empty($person)) {
            return new WP_Error('not_found', 'Person nicht gefunden.', ['status' => 404]);
        }

        $workshops = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    w.str_workshop_number,
                    w.str_workshop_title_de  AS title,
                    tz.dtm_time_from,
                    tz.dtm_time_to,
                    s.str_color
                 FROM {$wpdb->prefix}evtmgr_registrations_workshops rw
                 INNER JOIN {$wpdb->prefix}evtmgr_workshops w
                     ON w.id = rw.fky_workshop_id
                 LEFT JOIN {$wpdb->prefix}evtmgr_timezones tz
                     ON tz.id = w.fky_timezone_id
                 LEFT JOIN {$wpdb->prefix}evtmgr_slots s
                     ON s.id = w.fky_slot_id
                 WHERE rw.str_registration_cookie = %s
                 ORDER BY tz.dtm_time_from, w.str_workshop_number",
                $cookie
            ),
            ARRAY_A
        );

        return rest_ensure_response([
            'id'             => (int) $person['id'],
            'salutation'     => (string) ($person['str_salutation']   ?? ''),
            'first_name'     => (string) ($person['str_first_name']   ?? ''),
            'last_name'      => (string) ($person['str_last_name']    ?? ''),
            'email'          => (string) ($person['str_email']        ?? ''),
            'event_uid'      => (string) ($person['fky_event_uid']    ?? ''),
            'checked_in'     => !empty($person['ysn_checked_in']),
            'date_check_in'  => $person['dtm_date_check_in'] ?? null,
            'workshops'      => is_array($workshops) ? array_values($workshops) : [],
        ]);
    }

    public static function api_checkin(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        self::maybe_add_columns($wpdb);

        $body   = $request->get_json_params() ?? [];
        $cookie = sanitize_text_field((string) ($body['cookie'] ?? ''));

        if ($cookie === '') {
            return new WP_Error('missing_cookie', 'Cookie-Wert fehlt.', ['status' => 400]);
        }

        $person = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, ysn_checked_in FROM {$wpdb->prefix}evtmgr_persons
                 WHERE str_registration_cookie = %s LIMIT 1",
                $cookie
            ),
            ARRAY_A
        );

        if (empty($person)) {
            return new WP_Error('not_found', 'Person nicht gefunden.', ['status' => 404]);
        }

        if (!empty($person['ysn_checked_in'])) {
            return new WP_Error('already_checked_in', 'Person ist bereits eingecheckt.', ['status' => 409]);
        }

        $now    = current_time('mysql');
        $result = $wpdb->update(
            "{$wpdb->prefix}evtmgr_persons",
            ['ysn_checked_in' => 1, 'dtm_date_check_in' => $now],
            ['id' => (int) $person['id']],
            ['%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Datenbankfehler beim Check-in.', ['status' => 500]);
        }

        return rest_ensure_response([
            'success'        => true,
            'date_check_in'  => $now,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function maybe_add_columns(wpdb $wpdb): void {
        $wpdb->query(
            "ALTER TABLE {$wpdb->prefix}evtmgr_persons
             ADD COLUMN IF NOT EXISTS ysn_checked_in TINYINT(1) NOT NULL DEFAULT 0,
             ADD COLUMN IF NOT EXISTS dtm_date_check_in DATETIME NULL DEFAULT NULL"
        );
    }
}

Evtmgr_Checkin_App::boot();
