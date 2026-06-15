<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Slots {

    protected $wpdb;
    protected $slot_table;
    protected $timezones_table;
    protected $workshops_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb                   = $wpdb;
        $this->slot_table             = 'wp_evtmgr_slots';
        $this->timezones_table       = 'wp_evtmgr_timezones';
        $this->workshops_table        = 'wp_evtmgr_workshops';
    }

    public function get_slots_by_id($slot_ids = '0', $event_uid = '', $lang = 'de') {
        $ids       = $this->sanitize_ids($slot_ids);
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        if ($event_uid === '') {
            return array();
        }

        // ColdFusion always appends 0:
        // <cfset arguments.slot_ids = listAppend(arguments.slot_ids,0)>
        $ids[] = 0;
        $ids   = array_values(array_unique($ids));

        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT *,
                str_slot_name_{$lang} AS str_slot_name
            FROM {$this->slot_table}
            WHERE id IN ($placeholders)
              AND fky_event_uid = %s
            ORDER BY int_sort, str_slot_name
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_merge($ids, array($event_uid))),
            ARRAY_A
        );
    }

    public function get_slots_all($event_uid) {
        $event_uid = sanitize_text_field($event_uid);

        $sql = "
            SELECT *
            FROM {$this->slot_table}
            WHERE fky_event_uid = %s
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_slots_for_print($event_uid) {
        $event_uid = sanitize_text_field($event_uid);

        $sql = "
            SELECT *
            FROM {$this->slot_table}
            WHERE fky_event_uid = %s
              AND ysn_print = 1
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function qry_slots_by_time_zone($timezone_id = 0, $event_uid = '', $lang = 'de') {
        $timezone_ids = $this->sanitize_ids($timezone_id);
        $event_uid     = sanitize_text_field($event_uid);

        if ($event_uid === '') {
            return array();
        }

        if (empty($timezone_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($timezone_ids), '%d'));

        $sql = "
            SELECT str_slots
            FROM {$this->timezones_table}
            WHERE id IN ($placeholders)
              AND fky_event_uid = %s
        ";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_merge($timezone_ids, array($event_uid))),
            ARRAY_A
        );

        if (empty($rows)) {
            return array();
        }

        $slot_ids = array();

        foreach ($rows as $row) {
            if (!empty($row['str_slots'])) {
                $slot_ids = array_merge($slot_ids, $this->sanitize_ids($row['str_slots']));
            }
        }

        return $this->get_slots_by_id($slot_ids, $event_uid, $lang);
    }

    public function get_slots_for_output($event_uid, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_slot_name_{$lang} AS str_slot_name
            FROM {$this->slot_table}
            WHERE fky_event_uid = %s
            ORDER BY int_sort, str_slot_name_{$lang}
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_slot_by_workshop_id($workshop_id = 0, $lang = 'de') {
        $workshop_id = absint($workshop_id);
        $lang        = $this->sanitize_language($lang);

        if ($workshop_id <= 0) {
            return array();
        }

        $sql = "
            SELECT
                s.str_color,
                s.str_slot_name_{$lang} AS str_slot_name,
                s.int_sort
            FROM {$this->workshops_table} w
            INNER JOIN {$this->slot_table} s
                ON s.id = w.fky_slot_id
            WHERE w.id = %d
            LIMIT 1
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }

    protected function sanitize_ids($ids) {
        return Event_Registration_Helpers::sanitize_ids($ids, true);
    }
}