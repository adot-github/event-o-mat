<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Events {

    protected $wpdb;
    protected $table_name;
    protected $cookie_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb       = $wpdb;
        $this->table_name  = $wpdb->prefix . 'evtmgr_events';
        $this->cookie_name = 'current_event_uid';
    }

    public function get_events_by_event_uid($event_uid, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = sanitize_key($lang);

        if ($event_uid === '') {
            return null;
        }

        $sql = "
            SELECT *,
                str_event_name_{$lang} AS str_event_name,
                str_event_subtitle_{$lang} AS str_event_subtitle,
                mem_event_description_{$lang} AS mem_event_description
            FROM {$this->table_name}
            WHERE event_uid = %s
            LIMIT 1
        ";

        return $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_event_by_id($record_id, $lang = 'de') {
        $record_id = absint($record_id);
        $lang      = $this->sanitize_language($lang);

        if ($record_id <= 0) {
            return null;
        }

        $sql = "
            SELECT *,
                `str_event_name_{$lang}` AS str_event_name,
                `str_event_subtitle_{$lang}` AS str_event_subtitle,
                `mem_event_description_{$lang}` AS mem_event_description
            FROM `{$this->table_name}`
            WHERE `id` = %d
            LIMIT 1
        ";

        return $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $record_id),
            ARRAY_A
        );
    }

    public function get_events_all($lang = 'de') {
        $lang = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                `str_event_name_{$lang}` AS str_event_name,
                `str_event_subtitle_{$lang}` AS str_event_subtitle,
                `mem_event_description_{$lang}` AS mem_event_description
            FROM `{$this->table_name}`
            ORDER BY `id` DESC
        ";

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function get_event_by_event_uid($event_uid, $lang = 'de') {
        return $this->get_events_by_event_uid($event_uid, $lang);
    }

    public function get_current_event_uid($required = true) {
        if (!class_exists('Event_Registration_Context')) {
            $event_registration_class = __DIR__ . '/class-event-registration.php';

            if (file_exists($event_registration_class)) {
                require_once $event_registration_class;
            }
        }

        if (class_exists('Event_Registration_Context')) {
            $event_registration = new Event_Registration_Context();

            if (method_exists($event_registration, 'get_cookie_event_uid')) {
                return (string) $event_registration->get_cookie_event_uid($required);
            }
        }

        $event_uid = '';

        if (!empty($_COOKIE[$this->cookie_name])) {
            $event_uid = sanitize_text_field(wp_unslash($_COOKIE[$this->cookie_name]));
        }

        if ($event_uid === '' && $required) {
            wp_die('$$$ Sie müssen zuerst ein Event laden im Dashboard.');
        }

        return $event_uid;
    }

    public function get_current_event($lang = 'de', $required = true) {
        $event_uid = $this->get_current_event_uid($required);

        if ($event_uid === '') {
            return null;
        }

        $event = $this->get_events_by_event_uid($event_uid, $lang);

        if (empty($event) && $required) {
            return;
            wp_die('Kein Kongress für Event UID gefunden: ' . esc_html($event_uid));
        }

        return $event;
    }

    public function get_current_event_languages() {
        $event = $this->get_current_event('de', true);

        $languages_string = !empty($event['str_event_languages'])
            ? (string) $event['str_event_languages']
            : '';

        $languages = array_values(array_filter(array_map('trim', explode(',', $languages_string))));

        if (empty($languages)) {
            return array('de');
        }

        $clean_languages = array();

        foreach ($languages as $language) {
            $language = $this->sanitize_language($language);

            if (!in_array($language, $clean_languages, true)) {
                $clean_languages[] = $language;
            }
        }

        return $clean_languages;
    }

    public function get_current_event_sql_condition($table_alias = '') {
        $event_uid = $this->get_current_event_uid(true);

        $table_alias = trim((string) $table_alias);
        $table_alias = preg_replace('/[^A-Za-z0-9_]/', '', $table_alias);

        $field = 'fky_event_uid';

        if ($table_alias !== '') {
            $field = $table_alias . '.fky_event_uid';
        }

        return $field . " = '" . esc_sql($event_uid) . "'";
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}

