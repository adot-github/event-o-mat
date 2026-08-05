<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Presenters {

    protected $wpdb;
    protected $table_name;
    protected $workshops_presenters_table;
    protected $timezones_presenters_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb                    = $wpdb;
        $this->table_name              = 'wp_evtmgr_presenters';
        $this->workshops_presenters_table = 'wp_evtmgr_tbx_workshops_presenters';
        $this->timezones_presenters_table = 'wp_evtmgr_tbx_timezones_presenters';
        
    }

    public function get_presenters_by_workshop_id($workshop_id = 0, $lang = 'de') {
        $workshop_id = absint($workshop_id);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT
                p.id,
                p.str_first_name,
                p.str_last_name,
                p.str_academic_title,
                p.str_employer,
                p.str_job_title_{$lang} AS str_job_title,
                p.str_institution_{$lang} AS str_institution,
                p.str_person_image,
                p.mem_presenter_text_{$lang} AS mem_presenter_text
            FROM {$this->workshops_presenters_table} wp
            INNER JOIN {$this->table_name} p
                ON wp.fky_person_id = p.id
            WHERE wp.fky_workshop_id = %d
            ORDER BY p.str_last_name, p.str_first_name
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );
    }

    public function get_presenters_by_timezone_id($timezone_id = 0, $lang = 'de') {
        $workshop_id = absint($timezone_id);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT
                p.str_first_name,
                p.str_last_name,
                p.str_academic_title,
                p.str_job_title_{$lang} AS str_job_title,
                p.str_institution_{$lang} AS str_institution,
                p.str_person_image
            FROM {$this->timezones_presenters_table} wp
            INNER JOIN {$this->table_name} p
                ON wp.fky_person_id = p.id
            WHERE wp.fky_timezone_id = %d
            ORDER BY p.str_last_name, p.str_first_name
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );
    }

    public function get_presenters_for_event($event_uid) {
        $event_uid = sanitize_text_field($event_uid);

        $sql = "
            SELECT DISTINCT
                p.id,
                p.str_first_name,
                p.str_last_name
            FROM {$this->table_name} p
            INNER JOIN {$this->workshops_presenters_table} wp
                ON wp.fky_person_id = p.id
            INNER JOIN {$this->wpdb->prefix}evtmgr_workshops w
                ON w.id = wp.fky_workshop_id
            WHERE w.fky_event_uid = %s
              AND w.ysn_online = 1
            ORDER BY p.str_last_name, p.str_first_name
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_presenters_with_text($event_uid) {
        $event_uid = sanitize_text_field($event_uid);

        $sql = "
            SELECT *,
            mem_presenter_text_{$lang} AS mem_presenter_text
            FROM {$this->table_name}
            WHERE fky_event_uid = %s
            ORDER BY str_last_name, str_first_name
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
