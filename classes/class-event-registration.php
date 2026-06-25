<?php

if (!defined('ABSPATH')) {
    exit;
}

class Event_Registration_Context {

    protected $cookie_name = 'cookie_event_uid';
    protected $fallback_cookie_name = 'current_event_uid';

    private static $registered_page_slugs = [];

    public static function register_pages(array $slugs): void {
        self::$registered_page_slugs = array_merge(self::$registered_page_slugs, $slugs);
    }

    private function is_evtmgr_page(string $page): bool {
        if (empty(self::$registered_page_slugs)) {
            return true; // No registration done: fall back to original behaviour (no restriction).
        }
        return in_array($page, self::$registered_page_slugs, true);
    }

    public function get_cookie_event_uid($die_on_missing = true) {
            $event_uid = '';

            if (!empty($_COOKIE[$this->cookie_name])) {
                $event_uid = sanitize_text_field(wp_unslash($_COOKIE[$this->cookie_name]));
            } elseif (!empty($_COOKIE[$this->fallback_cookie_name])) {
                // Fallback for the existing dashboard cookie name.
                $event_uid = sanitize_text_field(wp_unslash($_COOKIE[$this->fallback_cookie_name]));
            }

            $current_page = isset($_GET['page'])
                ? sanitize_text_field(wp_unslash($_GET['page']))
                : '';

            $is_admin_page_with_page_param =
                is_admin()
                && isset($_SERVER['PHP_SELF'])
                && basename(sanitize_text_field(wp_unslash($_SERVER['PHP_SELF']))) === 'admin.php'
                && $current_page !== ''
                && $current_page !== 'dashboard'
                && $this->is_evtmgr_page($current_page);

            if ($event_uid === '' && $die_on_missing && $is_admin_page_with_page_param) {
                wp_safe_redirect(admin_url('admin.php?page=dashboard&evtmgr_notice=no_event'));
                exit;
            }

            return $event_uid;
        }
    }
