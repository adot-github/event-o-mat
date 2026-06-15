<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Sponsors {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = $wpdb->prefix . 'evtmgr_sponsors_und_partner';
    }

    /**
     * Returns sponsors for a given event_uid.
     * When event_uid is empty, returns all sponsors where fky_event_uid IS NULL or empty.
     */
    public function get_sponsors_by_event_uid($event_uid = '', $lang = 'de') {
        $lang      = $this->sanitize_language($lang);
        $event_uid = sanitize_text_field((string) $event_uid);

        if ($event_uid === '') {
            $sql = "
                SELECT
                    id,
                    str_sponsor_name_{$lang}  AS str_sponsor_name,
                    str_sponsor_link_{$lang}  AS str_sponsor_link,
                    str_sponsor_logo_{$lang}  AS str_sponsor_logo,
                    str_sponsor_group
                FROM {$this->table_name}
                WHERE fky_event_uid IS NULL
                   OR fky_event_uid = ''
                ORDER BY int_sort_order, str_sponsor_group, str_sponsor_name_{$lang}
            ";

            return $this->wpdb->get_results($sql, ARRAY_A);
        }

        $sql = "
            SELECT
                id,
                str_sponsor_name_{$lang}  AS str_sponsor_name,
                str_sponsor_link_{$lang}  AS str_sponsor_link,
                str_sponsor_logo_{$lang}  AS str_sponsor_logo,
                str_sponsor_group
            FROM {$this->table_name}
            WHERE fky_event_uid = %s
            ORDER BY int_sort_order, str_sponsor_group, str_sponsor_name_{$lang}
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}
