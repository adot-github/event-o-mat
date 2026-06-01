<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Audience {

    protected $wpdb;

    protected $table_name;
    protected $workshops_audience_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb                     = $wpdb;
        $this->table_name               = 'wp_evtmgr_audience';
        $this->workshops_audience_table = 'wp_evtmgr_tbx_workshops_audience';
    }

    public function get_target_audience_by_workshop_id($workshop_id, $lang = 'de') {
        $workshop_id = absint($workshop_id);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT
                a.str_audience_{$lang} AS str_audience,
                a.str_color
            FROM {$this->table_name} a
            INNER JOIN {$this->workshops_audience_table} wa
                ON wa.fky_audience_id = a.id
            WHERE wa.fky_workshop_id = %d
        ";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );

        if (empty($rows)) {
            return '';
        }

        $audiences = array();

        foreach ($rows as $row) {
            if (!empty($row['str_audience'])) {
                $audiences[] = $row['str_audience'];
            }
        }

        return implode(' | ', $audiences);
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}