<?php

if (!defined('ABSPATH')) {
    exit;
}

class Event_Registration_Helpers {

    public static function delete_by_cookie($wpdb, $table_name, $customer_cookie, $event_uid = '') {
        $customer_cookie = sanitize_text_field((string) $customer_cookie);
        $event_uid       = sanitize_text_field((string) $event_uid);
        $table_name      = sanitize_text_field((string) $table_name);

        if ($customer_cookie === '' || $table_name === '') {
            return false;
        }

        $where = array(
            'str_registration_cookie' => $customer_cookie,
        );

        $formats = array('%s');

        if ($event_uid !== '') {
            $where['fky_event_uid'] = $event_uid;
            $formats[] = '%s';
        }

        return $wpdb->delete(
            $table_name,
            $where,
            $formats
        );
    }

    public static function get_registration_value($data, $field_name, $default = '', $encode_non_scalar = true) {
        if (!is_array($data)) {
            return $default;
        }

        $field_name_lower = strtolower((string) $field_name);

        foreach ($data as $key => $value) {
            if (strtolower((string) $key) === $field_name_lower) {
                if (is_scalar($value)) {
                    return (string) $value;
                }

                return $encode_non_scalar
                    ? wp_json_encode($value, JSON_UNESCAPED_UNICODE)
                    : $default;
            }
        }

        return $default;
    }

    public static function get_table_columns($wpdb, $table_name, $include_normalized_keys = false) {
        $table_name = sanitize_text_field((string) $table_name);

        if ($table_name === '') {
            return array();
        }

        if (!$include_normalized_keys) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}", 0);

            if (empty($columns)) {
                return array();
            }

            return array_fill_keys($columns, true);
        }

        $columns = array();
        $rows    = $wpdb->get_results("DESCRIBE {$table_name}", ARRAY_A);

        if (empty($rows)) {
            return $columns;
        }

        foreach ($rows as $row) {
            if (empty($row['Field'])) {
                continue;
            }

            $field = (string) $row['Field'];

            $columns[strtolower($field)] = $field;
            $columns[self::normalize_column_key($field)] = $field;
        }

        return $columns;
    }

    public static function get_table_name($table_name) {
        return (string) $table_name;
    }

    public static function sanitize_ids($ids, $allow_zero = false) {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (!is_array($ids)) {
            return array();
        }

        $ids = array_map('absint', $ids);

        $ids = array_filter($ids, static function($id) use ($allow_zero) {
            return $allow_zero ? $id >= 0 : $id > 0;
        });

        return array_values($ids);
    }

    public static function sanitize_language($lang) {
        $lang = sanitize_key((string) $lang);

        $allowed_languages = array('de', 'en', 'fr', 'it');

        return in_array($lang, $allowed_languages, true) ? $lang : 'de';
    }

    public static function value_ci(array $row, $field_name, $default = '') {
        $field_name_lower = strtolower((string) $field_name);

        foreach ($row as $key => $value) {
            if (strtolower((string) $key) === $field_name_lower) {
                return is_scalar($value) ? (string) $value : $default;
            }
        }

        return $default;
    }

    public static function normalize_column_key($field) {
        $field = strtolower((string) $field);
        $field = preg_replace('/[^a-z0-9]+/', '', $field);

        return (string) $field;
    }

    /**
     * Whether the theme already ships Bootstrap (CSS + JS) for the given event.
     *
     * Controlled by the per-event option `theme_uses_bootstrap` stored in
     * wp_evtmgr_options (type true_false, default "0"):
     *   - "1"          → theme provides Bootstrap, the component loads nothing
     *   - "0" / unset  → the component must load its own Bootstrap
     *
     * Without an event_uid the option cannot be resolved, so we assume the
     * theme has no Bootstrap and let it load (safe default). Can be overridden
     * with the `evtmgr_theme_uses_bootstrap` filter.
     *
     * @param string $event_uid
     * @return bool
     */
    public static function theme_uses_bootstrap($event_uid = '') {
        $event_uid      = sanitize_text_field((string) $event_uid);
        $uses_bootstrap = false;

        if ($event_uid !== '') {
            if (!class_exists('Evtmgr_Options')) {
                require_once __DIR__ . '/class-evtmgr-options.php';
            }

            $uses_bootstrap = (new Evtmgr_Options())->get_option($event_uid, 'theme_uses_bootstrap') === '1';
        }

        return (bool) apply_filters('evtmgr_theme_uses_bootstrap', $uses_bootstrap, $event_uid);
    }

    /**
     * Enqueue the bundled Bootstrap stylesheet (and optionally the JS bundle)
     * for the public views, unless the theme already provides Bootstrap for
     * this event.
     *
     * Safe to call multiple times and from inside shortcode callbacks:
     * wp_enqueue_style()/wp_enqueue_script() de-duplicate by handle, and
     * WordPress prints a late enqueue in the footer.
     *
     * @param string $event_uid
     * @param bool   $with_js    Also load bootstrap.bundle.min.js (Popper +
     *                           modal/collapse/dropdown/tooltip). Needed by the
     *                           event_registration view (modal, accordion).
     * @return void
     */
    public static function enqueue_bootstrap($event_uid = '', $with_js = false) {
        if (self::theme_uses_bootstrap($event_uid)) {
            return;
        }

        $base_rel = '/db-custom/event-registration/public/assets/vendor/bootstrap/';
        $base_dir = get_stylesheet_directory() . $base_rel;
        $base_uri = get_stylesheet_directory_uri() . $base_rel;

        $css_handle = 'evtmgr-bootstrap';

        if (!wp_style_is($css_handle, 'registered') && !wp_style_is($css_handle, 'enqueued')) {
            $css_file = 'css/bootstrap.min.css';
            wp_register_style(
                $css_handle,
                $base_uri . $css_file,
                array(),
                file_exists($base_dir . $css_file) ? filemtime($base_dir . $css_file) : '5.3.8'
            );
        }
        wp_enqueue_style($css_handle);

        if (!$with_js) {
            return;
        }

        $js_handle = 'evtmgr-bootstrap';

        if (!wp_script_is($js_handle, 'registered') && !wp_script_is($js_handle, 'enqueued')) {
            $js_file = 'js/bootstrap.bundle.min.js';
            wp_register_script(
                $js_handle,
                $base_uri . $js_file,
                array(),
                file_exists($base_dir . $js_file) ? filemtime($base_dir . $js_file) : '5.3.8',
                true
            );
        }
        wp_enqueue_script($js_handle);
    }
}
