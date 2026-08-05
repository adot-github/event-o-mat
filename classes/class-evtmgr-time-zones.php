<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Time_Zones {

    protected $wpdb;
    protected $timezone_table;
    protected $workshops_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb                   = $wpdb;
        $this->timezone_table             = 'wp_evtmgr_timezones';
        $this->workshops_table        = 'wp_evtmgr_workshops';
    }

    public function get_time_zones_top($event_uid, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_timezone_name_{$lang} AS str_timezone_name
            FROM {$this->timezone_table}
            WHERE fky_parent_timezone_id = 0
              AND fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_time_zones_by_parent($id, $event_uid, $lang = 'de') {
        $id = absint($id);
        $event_uid        = sanitize_text_field($event_uid);
        $lang             = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_timezone_name_{$lang} AS str_timezone_name
            FROM {$this->timezone_table}
            WHERE fky_parent_timezone_id = %d
              AND fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $id, $event_uid),
            ARRAY_A
        );
    }

    public function get_time_zones_all($event_uid, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_timezone_name_{$lang} AS str_timezone_name
            FROM {$this->timezone_table}
            WHERE fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_time_zones_by_id($timezone_ids, $event_uid, $lang = 'de') {
        $ids       = $this->sanitize_ids($timezone_ids);
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT *,
                str_timezone_name_{$lang} AS str_timezone_name
            FROM {$this->timezone_table}
            WHERE id IN ($placeholders)
              AND fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_merge($ids, array($event_uid))),
            ARRAY_A
        );
    }

    public function get_time_zones_with_price($timezone_ids, $event_uid, $lang = 'de') {
        $ids       = $this->sanitize_ids($timezone_ids);
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT *,
                str_timezone_name_{$lang} AS str_timezone_name
            FROM {$this->timezone_table}
            WHERE id IN ($placeholders)
              AND int_price <> 0
              AND fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_merge($ids, array($event_uid))),
            ARRAY_A
        );
    }

    public function get_time_zones_with_time($timezone_ids, $event_uid) {
        $ids       = $this->sanitize_ids($timezone_ids);
        $event_uid = sanitize_text_field($event_uid);

        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT DISTINCT
                id,
                dtm_time_from,
                dtm_time_to,
                str_color
            FROM {$this->timezone_table}
            WHERE id IN ($placeholders)
              AND fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_merge($ids, array($event_uid))),
            ARRAY_A
        );
    }

    public function get_time_zone_by_workshopid($workshop_id) {
        $workshop_id = absint($workshop_id);

        if ($workshop_id <= 0) {
            return array();
        }

        $sql = "
            SELECT
                tz.str_slots,
                tz.dtm_day,
                tz.dtm_time_from,
                tz.dtm_time_to,
                tz.str_color,
                tz.str_timezone_name_de
            FROM {$this->workshops_table} w
            INNER JOIN {$this->timezone_table} tz
                ON tz.id = w.fky_timezone_id
            WHERE w.id = %d
            ORDER BY tz.dtm_time_from
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );
    }

    /**
     * Returns timezones that have ysn_selection_required = 1 but are not
     * covered by any of the given selected workshop IDs.
     *
     * Used in step-2 to show per-timezone validation messages.
     *
     * @param string $event_uid
     * @param int[]  $workshop_ids  Already-sanitized list of selected workshop IDs.
     * @param string $lang
     * @return array
     */
    public function get_uncovered_required_timezones($event_uid, array $workshop_ids, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        // No selected workshops → every required timezone is uncovered.
        if (empty($workshop_ids)) {
            $sql = "
                SELECT
                    id,
                    str_timezone_name_{$lang}             AS str_timezone_name,
                    mem_remark_on_no_selection_{$lang}    AS mem_remark_on_no_selection
                FROM {$this->timezone_table}
                WHERE fky_event_uid = %s
                  AND ysn_selection_required = 1
                ORDER BY int_sort_order
            ";

            return $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $event_uid),
                ARRAY_A
            );
        }

        $ids          = array_values(array_map('absint', $workshop_ids));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT
                tz.id,
                tz.str_timezone_name_{$lang}             AS str_timezone_name,
                tz.mem_remark_on_no_selection_{$lang}    AS mem_remark_on_no_selection
            FROM {$this->timezone_table} tz
            WHERE tz.fky_event_uid = %s
              AND tz.ysn_selection_required = 1
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->workshops_table} w
                  WHERE w.fky_timezone_id = tz.id
                    AND w.id IN ({$placeholders})
              )
            ORDER BY tz.int_sort_order
        ";

        $params = array_merge(array($event_uid), $ids);

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }

    protected function sanitize_ids($ids) {
        return Event_Registration_Helpers::sanitize_ids($ids, false);
    }
}