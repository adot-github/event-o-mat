<?php

if (!defined('ABSPATH')) {
    exit;
}

class Evtmgr_Workshop_Likes {

    const COOKIE_NAME = 'evtmgr_visitor_id';
    const COOKIE_TTL  = YEAR_IN_SECONDS;

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_tbx_workshop_likes';
    }

    /**
     * Returns the visitor's identifying cookie, creating and setting one
     * (valid for a year) if this is their first visit. No login required.
     */
    public function get_or_create_visitor_cookie() {
        $existing = $this->get_visitor_cookie();

        if ($existing !== '') {
            return $existing;
        }

        $new_cookie = wp_generate_uuid4();
        $this->set_visitor_cookie($new_cookie);

        return $new_cookie;
    }

    public function get_visitor_cookie() {
        return !empty($_COOKIE[self::COOKIE_NAME])
            ? $this->sanitize_cookie(wp_unslash($_COOKIE[self::COOKIE_NAME]))
            : '';
    }

    protected function set_visitor_cookie($cookie_value) {
        $cookie_value = $this->sanitize_cookie($cookie_value);

        if ($cookie_value === '' || headers_sent()) {
            return false;
        }

        return setcookie(
            self::COOKIE_NAME,
            $cookie_value,
            time() + self::COOKIE_TTL,
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    protected function sanitize_cookie($cookie_value) {
        $cookie_value = sanitize_text_field((string) $cookie_value);

        return preg_match('/^[a-zA-Z0-9\-]{8,100}$/', $cookie_value)
            ? $cookie_value
            : '';
    }

    public function is_liked($event_uid, $workshop_id, $cookie) {
        $workshop_id = absint($workshop_id);
        $event_uid   = sanitize_text_field((string) $event_uid);
        $cookie      = $this->sanitize_cookie($cookie);

        if ($cookie === '' || $workshop_id <= 0 || $event_uid === '') {
            return false;
        }

        $sql = "
            SELECT COUNT(*)
            FROM {$this->table_name}
            WHERE fky_event_uid = %s
              AND fky_workshop_id = %d
              AND str_cookie = %s
        ";

        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $event_uid, $workshop_id, $cookie)
        );
    }

    public function get_liked_workshop_ids($event_uid, $cookie) {
        $event_uid = sanitize_text_field((string) $event_uid);
        $cookie    = $this->sanitize_cookie($cookie);

        if ($cookie === '' || $event_uid === '') {
            return array();
        }

        $sql = "
            SELECT fky_workshop_id
            FROM {$this->table_name}
            WHERE fky_event_uid = %s
              AND str_cookie = %s
        ";

        $ids = $this->wpdb->get_col(
            $this->wpdb->prepare($sql, $event_uid, $cookie)
        );

        return array_map('absint', $ids);
    }

    /**
     * Toggles the like for one workshop and returns the new state.
     */
    public function toggle_like($event_uid, $workshop_id, $cookie) {
        $event_uid   = sanitize_text_field((string) $event_uid);
        $workshop_id = absint($workshop_id);
        $cookie      = $this->sanitize_cookie($cookie);

        if ($cookie === '' || $workshop_id <= 0 || $event_uid === '') {
            return array('success' => false, 'liked' => false);
        }

        if ($this->is_liked($event_uid, $workshop_id, $cookie)) {
            $this->wpdb->delete(
                $this->table_name,
                array(
                    'fky_event_uid'   => $event_uid,
                    'fky_workshop_id' => $workshop_id,
                    'str_cookie'      => $cookie,
                ),
                array('%s', '%d', '%s')
            );

            return array('success' => true, 'liked' => false);
        }

        $this->wpdb->insert(
            $this->table_name,
            array(
                'fky_event_uid'   => $event_uid,
                'fky_workshop_id' => $workshop_id,
                'str_cookie'      => $cookie,
            ),
            array('%s', '%d', '%s')
        );

        return array('success' => true, 'liked' => true);
    }
}
