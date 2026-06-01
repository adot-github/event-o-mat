<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Rooms {

    protected $wpdb;

    protected $table_name;
    protected $workshops_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb            = $wpdb;
        $this->table_name      = 'wp_evtmgr_rooms';
        $this->workshops_table = 'wp_evtmgr_workshops';
    }

    public function get_room_by_workshop_id($workshop_id, $lang = 'de') {
        $workshop_id = absint($workshop_id);
        $lang        = $this->sanitize_language($lang);

        if ($workshop_id <= 0) {
            return array();
        }

        $sql = "
            SELECT
                r.str_room_{$lang} AS str_room,
                r.str_room_number,
                r.str_building
            FROM {$this->workshops_table} w
            INNER JOIN {$this->table_name} r
                ON r.id = w.fky_room_id
            WHERE w.id = %d
            LIMIT 1
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );
    }

    public function get_room_by_workshop_id_time_zone_id($workshop_id, $timezone_id, $lang = 'de') {
        $workshop_id = absint($workshop_id);
        $timezone_id = absint($timezone_id);
        $lang        = $this->sanitize_language($lang);

        if ($workshop_id <= 0 || $timezone_id <= 0) {
            return array();
        }

        $sql = "
            SELECT
                r.str_room_{$lang} AS str_room,
                r.str_room_number,
                r.str_building
            FROM {$this->workshops_table} w
            INNER JOIN {$this->table_name} r
                ON r.id = w.fky_room_id
            WHERE w.id = %d
              AND w.fky_timezone_id = %d
            LIMIT 1
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id, $timezone_id),
            ARRAY_A
        );
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}
