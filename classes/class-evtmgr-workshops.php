<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Workshops {

    protected $wpdb;

    protected $table_name;
    protected $time_zones_table;
    protected $slots_table;
    protected $workshops_audience_table;
    protected $audience_table;
    protected $workshops_categories_table;
    protected $workshop_categories_table;
    protected $categories_table;
    protected $workshops_persons_table;
    protected $workshops_presenters_table;
    protected $registrations_workshops_table;
    protected $workshop_types_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb                       = $wpdb;
        $this->table_name                 = 'wp_evtmgr_workshops';
        $this->time_zones_table           = 'wp_evtmgr_timezones';
        $this->slots_table                = 'wp_evtmgr_slots';
        $this->workshops_audience_table   = 'wp_evtmgr_tbx_workshops_audience';
        $this->audience_table             = 'wp_evtmgr_audience';
        $this->workshops_categories_table = 'wp_evtmgr_tbx_workshops_categories';
        $this->workshop_categories_table  = 'wp_evtmgr_workshop_categories';
        $this->categories_table           = 'wp_evtmgr_categories';
        $this->workshops_persons_table    = 'wp_evtmgr_tbx_workshops_persons';
        $this->workshops_presenters_table = 'wp_evtmgr_tbx_workshops_presenters';
        $this->registrations_workshops_table = 'wp_evtmgr_registrations_workshops';
        $this->workshop_types_table       = 'wp_evtmgr_workshop_types';
    }

    public function get_workshops_by_slot($slot_id, $time_slot, $event_uid, $lang = 'de') {
        $slot_id   = absint($slot_id);
        $time_slot = absint($time_slot);
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT
                w.id,
                w.str_workshop_title_{$lang} AS str_workshop_title,
                w.str_workshop_number,
                w.ysn_booked_out,
                w.ysn_auto_register,
                w.ysn_no_registration_possible,
                w.int_max_number_of_registrations,
                w.int_number_of_registrations,
                w.ysn_print,
                w.mem_workshop_description_{$lang} AS mem_workshop_description,
                w.mem_workshop_description_long_{$lang} AS mem_workshop_description_long,

                tz.dtm_day,
                tz.dtm_time_from,
                tz.dtm_time_to,

                s.str_color,
                s.int_number_of_columns,

                w.fky_slot_id,
                w.fky_timezone_id,

                IF(
                    LENGTH(tz.str_timezone_code),
                    CONCAT(IFNULL(tz.str_timezone_code, ''), '.', IFNULL(w.str_workshop_number, '')),
                    w.str_workshop_number
                ) AS str_workshop_code

            FROM {$this->table_name} w

            INNER JOIN {$this->time_zones_table} tz
                ON w.fky_timezone_id = tz.id

            INNER JOIN {$this->slots_table} s
                ON w.fky_slot_id = s.id

            WHERE w.fky_slot_id = %d
            AND w.fky_timezone_id = %d
            AND w.fky_event_uid = %s
            AND w.ysn_online = 1

            ORDER BY w.str_workshop_number, str_workshop_title
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $slot_id, $time_slot, $event_uid),
            ARRAY_A
        );
    }

    public function get_workshops_all_by_slot($slot_id, $event_uid, $lang = 'de') {
        $slot_id   = absint($slot_id);
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT
                w.id,
                w.str_workshop_title_{$lang} AS str_workshop_title,
                w.str_workshop_number,
                tz.dtm_time_from,
                tz.dtm_time_to
            FROM {$this->table_name} w
            INNER JOIN {$this->time_zones_table} tz
                ON tz.id = w.fky_timezone_id
            WHERE w.fky_slot_id = %d
              AND w.fky_event_uid = %s
              AND w.ysn_online = 1
            ORDER BY tz.dtm_time_from, w.str_workshop_number
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $slot_id, $event_uid),
            ARRAY_A
        );
    }

    public function get_workshop_types_for_output($event_uid, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT
                id,
                str_event_typename_{$lang} AS str_type_name
            FROM {$this->workshop_types_table}
            WHERE fky_event_uid = %s
            ORDER BY str_type_name
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_workshops_all_by_type($workshop_type_id, $event_uid, $lang = 'de') {
        $workshop_type_id = absint($workshop_type_id);
        $event_uid        = sanitize_text_field($event_uid);
        $lang             = $this->sanitize_language($lang);

        $sql = "
            SELECT
                w.id,
                w.str_workshop_title_{$lang} AS str_workshop_title,
                w.str_workshop_title_de,
                w.str_workshop_subtitle_{$lang} AS str_workshop_subtitle,
                w.str_workshop_number,
                w.mem_workshop_description_{$lang} AS mem_workshop_description,
                w.mem_workshop_description_long_{$lang} AS mem_workshop_description_long,
                tz.dtm_day,
                tz.dtm_time_from,
                tz.dtm_time_to
            FROM {$this->table_name} w
            INNER JOIN {$this->time_zones_table} tz
                ON tz.id = w.fky_timezone_id
            WHERE w.fky_workshop_type = %d
              AND w.fky_event_uid = %s
              AND w.ysn_online = 1
            ORDER BY tz.dtm_time_from, w.str_workshop_number
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_type_id, $event_uid),
            ARRAY_A
        );
    }

    public function get_all_categories_for_event($event_uid, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT
                id,
                str_category_{$lang} AS str_category_name
            FROM {$this->categories_table}
            WHERE fky_event_uid = %s
            ORDER BY str_category_name
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    /**
     * All workshops of an event, optionally narrowed down by a free-text
     * search and/or workshop type / category / presenter filters.
     *
     * Categories and presenters are many-to-many, so matches are found via
     * GROUP_CONCAT + FIND_IN_SET (multiple selected values within the same
     * filter are OR'ed, the different filters are AND'ed) — mirrored from
     * db-custom/mks/public/functions.php::mks_get_unterrichtsideen().
     */
    public function get_filtered_workshops(
        $event_uid,
        $lang = 'de',
        $search = '',
        array $type_ids = array(),
        array $category_ids = array(),
        array $presenter_ids = array()
    ) {
        $event_uid     = sanitize_text_field($event_uid);
        $lang          = $this->sanitize_language($lang);
        $search        = trim((string) $search);
        $type_ids      = array_values(array_unique(array_filter(array_map('absint', $type_ids))));
        $category_ids  = array_values(array_unique(array_filter(array_map('absint', $category_ids))));
        $presenter_ids = array_values(array_unique(array_filter(array_map('absint', $presenter_ids))));

        $where  = array('w.fky_event_uid = %s', 'w.ysn_online = 1');
        $params = array($event_uid);

        if (!empty($type_ids)) {
            $placeholders = implode(',', array_fill(0, count($type_ids), '%d'));
            $where[]      = "w.fky_workshop_type IN ({$placeholders})";
            $params       = array_merge($params, $type_ids);
        }

        if ($search !== '') {
            $like    = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = "(
                w.str_workshop_title_{$lang} LIKE %s
                OR w.mem_workshop_description_{$lang} LIKE %s
                OR w.mem_workshop_description_long_{$lang} LIKE %s
            )";
            $params  = array_merge($params, array($like, $like, $like));
        }

        $having = array();

        foreach ($category_ids as $category_id) {
            $having['category'][] = "FIND_IN_SET({$category_id}, category_ids)";
        }

        foreach ($presenter_ids as $presenter_id) {
            $having['presenter'][] = "FIND_IN_SET({$presenter_id}, presenter_ids)";
        }

        $having_sql = '';

        if (!empty($having)) {
            $having_groups = array_map(
                static fn($group) => '(' . implode(' OR ', $group) . ')',
                $having
            );
            $having_sql = 'HAVING ' . implode(' AND ', $having_groups);
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT
                w.id,
                w.str_workshop_title_{$lang} AS str_workshop_title,
                w.str_workshop_number,
                w.fky_workshop_type,
                GROUP_CONCAT(DISTINCT wc.fky_category_id) AS category_ids,
                GROUP_CONCAT(DISTINCT wpr.fky_person_id) AS presenter_ids
            FROM {$this->table_name} w
            LEFT JOIN {$this->workshops_categories_table} wc
                ON wc.fky_workshop_id = w.id
            LEFT JOIN {$this->workshops_presenters_table} wpr
                ON wpr.fky_workshop_id = w.id
            WHERE {$where_sql}
            GROUP BY w.id
            {$having_sql}
            ORDER BY w.str_workshop_number, str_workshop_title
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
    }

    public function get_workshop_by_id($workshop_id, $lang = 'de') {
        $workshop_id = absint($workshop_id);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT w.*,
                w.str_workshop_title_{$lang} AS str_workshop_title,
                w.mem_workshop_description_{$lang} AS mem_workshop_description,
                w.mem_workshop_description_long_{$lang} AS mem_workshop_description_long,
                wt.str_event_typename_{$lang} AS str_workshop_type_name
            FROM {$this->table_name} w
            LEFT JOIN {$this->workshop_types_table} wt
                ON wt.id = w.fky_workshop_type
            WHERE w.id = %d
            LIMIT 1
        ";

        return $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );
    }

    public function get_workshops_by_audience_id($audience_id, $event_uid, $lang = 'de') {
        $audience_id = absint($audience_id);
        $event_uid   = sanitize_text_field($event_uid);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT
                w.id,
                w.str_workshop_title_{$lang} AS str_workshop_title,
                w.str_workshop_number,
                w.mem_workshop_description_{$lang} AS mem_workshop_description,
                w.mem_workshop_description_long_{$lang} AS mem_workshop_description_long,
                wa.fky_audience_id,
                a.str_color,
                a.str_audience_{$lang} AS str_audience,
                w.fky_timezone_id,
                tz.dtm_day,
                tz.dtm_time_from,
                tz.dtm_time_to
            FROM {$this->workshops_audience_table} wa
            INNER JOIN {$this->table_name} w
                ON wa.fky_workshop_id = w.id
            INNER JOIN {$this->audience_table} a
                ON a.id = wa.fky_audience_id
            INNER JOIN {$this->time_zones_table} tz
                ON tz.id = w.fky_timezone_id
            WHERE wa.fky_audience_id = %d
              AND w.fky_event_uid = %s
            ORDER BY w.str_workshop_number
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $audience_id, $event_uid),
            ARRAY_A
        );
    }

    public function get_workshops_by_category_id($category_id, $event_uid, $lang = 'de') {
        $category_id = absint($category_id);
        $event_uid   = sanitize_text_field($event_uid);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT
                w.id,
                w.str_workshop_title_{$lang} AS str_workshop_title,
                w.str_workshop_number,
                w.mem_workshop_description_{$lang} AS mem_workshop_description,
                w.mem_workshop_description_long_{$lang} AS mem_workshop_description_long,
                wcg.fky_workshop_categoryid,
                w.fky_timezone_id,
                tz.dtm_day,
                tz.dtm_time_from,
                tz.dtm_time_to
            FROM {$this->workshops_categories_table} wcg
            INNER JOIN {$this->table_name} w
                ON wcg.fky_workshop_id = w.id
            INNER JOIN {$this->workshop_categories_table} c
                ON c.pky_workshop_category_id = wcg.fky_workshop_categoryid
            INNER JOIN {$this->time_zones_table} tz
                ON tz.id = w.fky_timezone_id
            WHERE wcg.fky_workshop_categoryid = %d
              AND w.fky_event_uid = %s
            ORDER BY w.str_workshop_number
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $category_id, $event_uid),
            ARRAY_A
        );
    }

    public function get_categories_by_workshop_id($workshop_id, $lang = 'de') {
        $workshop_id = absint($workshop_id);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT
                c.str_category_{$lang} AS str_category
            FROM {$this->categories_table} c
            INNER JOIN {$this->workshops_categories_table} wc
                ON wc.fky_category_id = c.id
            WHERE wc.fky_workshop_id = %d
        ";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $workshop_id),
            ARRAY_A
        );

        if (empty($rows)) {
            return '';
        }

        $categories = array();

        foreach ($rows as $row) {
            if (!empty($row['str_category'])) {
                $categories[] = $row['str_category'];
            }
        }

        return implode(' | ', $categories);
    }

    public function get_workshops_by_audience_id_pairs($event_uid = '') {
        $event_uid = sanitize_text_field($event_uid);

        $where_owner = '';
        $params      = array();

        if ($event_uid !== '') {
            $where_owner = 'AND w.fky_event_uid = %s';
            $params[]    = $event_uid;
        }

        $sql = "
            SELECT
                wa.fky_audience_id,
                CONVERT(GROUP_CONCAT(w.id) USING utf8) AS ids
            FROM {$this->workshops_audience_table} wa
            INNER JOIN {$this->table_name} w
                ON wa.fky_workshop_id = w.id
            INNER JOIN {$this->audience_table} a
                ON a.id = wa.fky_audience_id
            WHERE 1 = 1
              {$where_owner}
            GROUP BY wa.fky_audience_id
        ";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function get_workshops_for_filters($id_list) {
        $person_ids = $this->sanitize_ids($id_list);

        if (empty($person_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($person_ids), '%d'));

        $sql = "
            SELECT
                CONVERT(GROUP_CONCAT(w.id) USING utf8) AS ids,
                wp.fky_person_id
            FROM {$this->workshops_persons_table} wp
            INNER JOIN {$this->table_name} w
                ON wp.fky_workshop_id = w.id
            WHERE wp.fky_person_id IN ($placeholders)
            GROUP BY wp.fky_person_id
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $person_ids),
            ARRAY_A
        );
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }

    protected function sanitize_ids($ids) {
        return Event_Registration_Helpers::sanitize_ids($ids, false);
    }

    public function sync_registrations($event_uid) {
            $event_uid = sanitize_text_field((string) $event_uid);
    
            if ($event_uid === '') {
                return array('success' => false, 'checked' => 0, 'updated' => 0, 'errors' => array('event_uid is empty.'));
            }
    
            $sql = "
                UPDATE {$this->table_name} AS w
                LEFT JOIN (
                    SELECT fky_workshop_id, COUNT(*) AS registration_count
                    FROM {$this->registrations_workshops_table}
                    GROUP BY fky_workshop_id
                ) AS r ON r.fky_workshop_id = w.id
                SET w.int_number_of_registrations = COALESCE(r.registration_count, 0)
                WHERE w.fky_event_uid = %s
                AND ysn_no_registration_possible = 0
            ";
    
            $updated = $this->wpdb->query($this->wpdb->prepare($sql, $event_uid));
    
            if ($updated === false) {
                return array(
                    'success' => false,
                    'checked' => 0,
                    'updated' => 0,
                    'errors'  => array($this->wpdb->last_error),
                );
            }
    
            return array(
                'success' => true,
                'checked' => (int) $updated,
                'updated' => (int) $updated,
                'errors'  => array(),
            );
        }
    
        public function get_workshops_for_pdf_list($event_uid) {
            $event_uid = sanitize_text_field((string) $event_uid);
    
            $sql = "
                SELECT id, str_workshop_number, str_workshop_title_de
                FROM {$this->table_name}
                WHERE fky_event_uid = %s
                  AND COALESCE(ysn_no_registration_possible, 0) = 0
                ORDER BY str_workshop_number, str_workshop_title_de
            ";
    
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $event_uid),
                ARRAY_A
            );
    
            return is_array($rows) ? $rows : array();
        }
    
        public function workshop_value_ci($row, $key, $default = '') {
            foreach ((array) $row as $row_key => $value) {
                if (strcasecmp((string) $row_key, (string) $key) === 0) {
                    return is_scalar($value) ? trim((string) $value) : $default;
                }
            }
    
            return $default;
        }
    
        public function workshop_pdf_label($workshop) {
            return trim(
                $this->workshop_value_ci($workshop, 'str_workshop_number') . ' ' .
                $this->workshop_value_ci($workshop, 'str_workshop_title_de')
            );
        }
    
        public function workshop_pdf_file_name($workshop) {
            $title = $this->workshop_value_ci($workshop, 'str_workshop_title_de');
            $fallback = 'workshop-' . absint($this->workshop_value_ci($workshop, 'id'));
            $name = strtolower(sanitize_file_name($title !== '' ? $title : $fallback));
    
            return preg_match('/\.pdf$/i', $name) ? $name : $name . '.pdf';
        }
    
        public function get_workshops_without_pdf(array $workshops, $pdf_path) {
            $missing = array();
            $pdf_path = rtrim((string) $pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
            foreach ($workshops as $workshop) {
                $file_name = $this->workshop_pdf_file_name($workshop);
    
                if (!is_file($pdf_path . $file_name)) {
                    $workshop['expected_pdf_file'] = $file_name;
                    $missing[] = $workshop;
                }
            }
    
            return $missing;
        }
    
        public function get_workshop_presenters_text($workshop_id) {
            $workshop_id = absint($workshop_id);
    
            if ($workshop_id <= 0) {
                return '';
            }
    
            $tables = $this->get_pdf_tables();
            $presenters = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "
                    SELECT DISTINCT p.*
                    FROM {$tables['workshops_presenters']} AS wp
                    INNER JOIN {$tables['presenters']} AS p
                        ON p.id = wp.fky_person_id
                    WHERE wp.fky_workshop_id = %d
                    ORDER BY p.str_last_name, p.str_first_name, p.id
                    ",
                    $workshop_id
                ),
                ARRAY_A
            );
    
            if (!is_array($presenters) || empty($presenters)) {
                return '';
            }
    
            $names = array();
    
            foreach ($presenters as $presenter) {
                $first_name = $this->workshop_value_ci($presenter, 'str_first_name');
                $last_name  = $this->workshop_value_ci($presenter, 'str_last_name');
                $full_name  = trim($first_name . ' ' . $last_name);
    
                if ($full_name === '') {
                    $full_name = $this->workshop_value_ci($presenter, 'str_presenter_name');
                }
    
                if ($full_name === '') {
                    $full_name = $this->workshop_value_ci($presenter, 'str_name');
                }
    
                if ($full_name !== '') {
                    $names[] = $full_name;
                }
            }
    
            return implode(', ', array_unique($names));
        }
    
        public function get_workshop_registered_persons($workshop_id, $event_uid) {
            $workshop_id = absint($workshop_id);
            $event_uid   = sanitize_text_field((string) $event_uid);
    
            if ($workshop_id <= 0 || $event_uid === '') {
                return array();
            }
    
            $tables = $this->get_pdf_tables();
            $persons = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "
                    SELECT DISTINCT p.*
                    FROM {$tables['registrations_workshops']} AS rw
                    INNER JOIN {$tables['persons']} AS p ON p.id = rw.fky_person_id
                    WHERE rw.fky_workshop_id = %d
                      AND rw.fky_event_uid = %s
                    ORDER BY p.str_last_name, p.str_first_name, p.str_email
                    ",
                    $workshop_id,
                    $event_uid
                ),
                ARRAY_A
            );
    
            return is_array($persons) ? $persons : array();
        }
    
        protected function get_pdf_tables() {
            $prefix = isset($this->wpdb->prefix) && $this->wpdb->prefix !== '' ? $this->wpdb->prefix : 'wp_';
    
            return array(
                'workshops'               => $prefix . 'evtmgr_workshops',
                'registrations_workshops' => $prefix . 'evtmgr_registrations_workshops',
                'persons'                 => $prefix . 'evtmgr_persons',
                'workshops_presenters'    => $prefix . 'evtmgr_tbx_workshops_presenters',
                'presenters'              => $prefix . 'evtmgr_presenters',
            );
        }
}
