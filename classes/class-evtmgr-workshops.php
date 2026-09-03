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
    protected $persons_table;
    protected $events_table;

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
        $this->persons_table               = 'wp_evtmgr_persons';
        $this->events_table                = $wpdb->prefix . 'evtmgr_events';
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

        // str_type_name_plural = str_event_typename_plural_<lang> (de/fr/it/en all
        // have the column – see db-updates.sql), falling back to the singular
        // when the plural value is empty.
        $sql = "
            SELECT
                id,
                str_event_typename_{$lang} AS str_type_name,
                COALESCE(NULLIF(str_event_typename_plural_{$lang}, ''), str_event_typename_{$lang}) AS str_type_name_plural
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

            $orphan_delete_sql = "
                DELETE rw FROM {$this->registrations_workshops_table} AS rw
                LEFT JOIN {$this->persons_table} AS p ON p.id = rw.fky_person_id
                LEFT JOIN {$this->events_table} AS e ON e.id = rw.fky_event_id
                WHERE rw.fky_event_uid = %s
                AND (p.id IS NULL OR e.id IS NULL)
            ";

            $orphans_deleted = $this->wpdb->query($this->wpdb->prepare($orphan_delete_sql, $event_uid));

            if ($orphans_deleted === false) {
                return array(
                    'success' => false,
                    'checked' => 0,
                    'updated' => 0,
                    'errors'  => array($this->wpdb->last_error),
                );
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
                'success'         => true,
                'checked'         => (int) $updated,
                'updated'         => (int) $updated,
                'orphans_deleted' => (int) $orphans_deleted,
                'errors'          => array(),
            );
        }
    
        public function get_workshops_for_pdf_list($event_uid) {
            $event_uid = sanitize_text_field((string) $event_uid);
    
            $sql = "
                SELECT id, str_workshop_number, str_workshop_title_de,
                       str_workshop_pdf_de, str_workshop_pdf_fr, str_workshop_pdf_it, str_workshop_pdf_en
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
    
        /**
         * File name for a workshop's generated PDF.
         *
         * Prefers the stored, pre-generated name in str_workshop_pdf_<lang>
         * (falling back to str_workshop_pdf_de) – see workshop_update_pdf_filenames().
         * Only when that field is empty it falls back to the legacy scheme
         * "<sanitized German title>.pdf".
         */
        public function workshop_pdf_file_name($workshop, $lang = 'de') {
            $lang = in_array($lang, array('de', 'fr', 'it', 'en'), true) ? $lang : 'de';

            $stored = $this->workshop_value_ci($workshop, 'str_workshop_pdf_' . $lang);
            if ($stored === '' && $lang !== 'de') {
                $stored = $this->workshop_value_ci($workshop, 'str_workshop_pdf_de');
            }
            if ($stored !== '') {
                $name = strtolower(sanitize_file_name($stored));
                return preg_match('/\.pdf$/i', $name) ? $name : $name . '.pdf';
            }

            $title = $this->workshop_value_ci($workshop, 'str_workshop_title_de');
            $fallback = 'workshop-' . absint($this->workshop_value_ci($workshop, 'id'));
            $name = strtolower(sanitize_file_name($title !== '' ? $title : $fallback));

            return preg_match('/\.pdf$/i', $name) ? $name : $name . '.pdf';
        }

        /**
         * Web-safe PDF file name from a workshop title, made unique with "-id-<id>"
         * (a title can in theory occur twice). E.g. "KI im Klassenzimmer" (#42)
         * -> "ki-im-klassenzimmer-id-42.pdf".
         */
        public function build_pdf_filename($title, $workshop_id) {
            $workshop_id = absint($workshop_id);

            $slug = trim((string) $title);
            if (function_exists('remove_accents')) {
                $slug = remove_accents($slug);
            }
            $slug = strtolower($slug);
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

            if ($slug === '') {
                $slug = 'workshop';
            }

            return $slug . '-id-' . $workshop_id . '.pdf';
        }

        /**
         * Fill wp_evtmgr_workshops.str_workshop_pdf_<lang> for every workshop of
         * the event where it is still empty: a web-safe file name built from the
         * workshop title of that language (German title as fallback) plus
         * "-id-<id>". Languages without their own title are left empty (the PDF
         * generation then falls back to the German file name).
         *
         * @param string          $event_uid
         * @param array<int,string> $langs  Only de/fr/it – the languages that have
         *                                  a str_workshop_title_<lang> column.
         * @return array{checked:int,updated:int,files:array<int,array{id:int,lang:string,file_name:string}>}
         */
        public function workshop_update_pdf_filenames($event_uid, array $langs = array('de', 'fr', 'it')) {
            $event_uid = sanitize_text_field((string) $event_uid);
            $langs     = array_values(array_intersect($langs, array('de', 'fr', 'it')));

            $summary = array('checked' => 0, 'updated' => 0, 'files' => array());

            if ($event_uid === '' || empty($langs)) {
                return $summary;
            }

            $cols = array('id', 'str_workshop_title_de');
            foreach ($langs as $lang) {
                $cols[] = "str_workshop_title_{$lang}";
                $cols[] = "str_workshop_pdf_{$lang}";
            }
            $select = implode(', ', array_values(array_unique($cols)));

            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT {$select} FROM {$this->table_name} WHERE fky_event_uid = %s",
                    $event_uid
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $summary['checked']++;

                $workshop_id = absint($row['id'] ?? 0);
                if ($workshop_id <= 0) {
                    continue;
                }

                $update  = array();
                $formats = array();

                foreach ($langs as $lang) {
                    if (trim((string) ($row["str_workshop_pdf_{$lang}"] ?? '')) !== '') {
                        continue;
                    }

                    $title = trim((string) ($row["str_workshop_title_{$lang}"] ?? ''));
                    if ($title === '') {
                        if ($lang !== 'de') {
                            continue;
                        }
                        $title = trim((string) ($row['str_workshop_title_de'] ?? ''));
                    }

                    $file_name = $this->build_pdf_filename($title, $workshop_id);

                    $update["str_workshop_pdf_{$lang}"] = $file_name;
                    $formats[] = '%s';
                    $summary['files'][] = array('id' => $workshop_id, 'lang' => $lang, 'file_name' => $file_name);
                }

                if (empty($update)) {
                    continue;
                }

                $updated = $this->wpdb->update(
                    $this->table_name,
                    $update,
                    array('id' => $workshop_id),
                    $formats,
                    array('%d')
                );

                if ($updated) {
                    $summary['updated']++;
                }
            }

            return $summary;
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

        /**
         * Presenters of a workshop, formatted as
         *   <img class="presenter-photo" …>            (round photo, if str_person_image is set)
         *   <strong>akad. Titel Vorname Nachname</strong><br>Funktion, Firma
         * one block per presenter (blocks separated by <br><br>).
         * Returns HTML (already escaped) or '' when none.
         */
        public function get_workshop_presenters_detailed($workshop_id, $lang = 'de') {
            $workshop_id = absint($workshop_id);

            if ($workshop_id <= 0) {
                return '';
            }

            $lang   = in_array($lang, array('de', 'fr', 'it'), true) ? $lang : 'de';
            $tables = $this->get_pdf_tables();

            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "
                    SELECT DISTINCT
                        p.str_academic_title,
                        p.str_first_name,
                        p.str_last_name,
                        p.str_person_image,
                        p.str_job_title_{$lang}   AS str_job_title,
                        p.str_institution_{$lang} AS str_institution,
                        p.str_employer
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

            if (!is_array($rows) || empty($rows)) {
                return '';
            }

            $blocks = array();

            foreach ($rows as $r) {
                $name = trim(implode(' ', array_filter(array(
                    rtrim(trim((string) ($r['str_academic_title'] ?? '')), " ,;"),
                    trim((string) ($r['str_first_name'] ?? '')),
                    trim((string) ($r['str_last_name'] ?? '')),
                ), 'strlen')));

                if ($name === '') {
                    continue;
                }

                $company = trim((string) ($r['str_institution'] ?? ''));
                if ($company === '') {
                    $company = trim((string) ($r['str_employer'] ?? ''));
                }

                $meta = array();
                foreach (array((string) ($r['str_job_title'] ?? ''), $company) as $part) {
                    $part = rtrim(trim((string) $part), " ,;");
                    if ($part !== '') {
                        $meta[] = $part;
                    }
                }

                $block = $this->presenter_photo_img((string) ($r['str_person_image'] ?? ''));
                $block .= '<strong>' . esc_html($name) . '</strong>';
                if (!empty($meta)) {
                    $block .= '<br>' . esc_html(implode(', ', $meta));
                }

                $blocks[] = $block;
            }

            return implode('<br><br>', $blocks);
        }

        /**
         * <img> tag (base64 data URI) for a presenter photo, or '' when the file
         * is missing. str_person_image may be a bare file name (uploads root) or
         * a path relative to the uploads dir
         * ("<event>/assets/presenter-images/foo.jpg").
         */
        protected function presenter_photo_img($file_name) {
            $file_name = trim((string) $file_name);

            if ($file_name === '' || !function_exists('wp_upload_dir')) {
                return '';
            }

            $basedir = rtrim((string) (wp_upload_dir()['basedir'] ?? ''), '/\\');
            $path    = '';

            foreach (array($file_name, basename($file_name)) as $candidate) {
                $try = $basedir . DIRECTORY_SEPARATOR
                    . ltrim(str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR);
                if (is_file($try) && is_readable($try)) {
                    $path = $try;
                    break;
                }
            }

            if ($path === '') {
                return '';
            }

            $data = file_get_contents($path);
            if ($data === false) {
                return '';
            }

            $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';
            if ($mime === '') {
                $ext  = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
            }

            return '<img class="presenter-photo" src="data:' . $mime . ';base64,'
                . base64_encode($data) . '" alt="">';
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

        /**
         * Human readable "day, from–to Uhr" string for a workshop, taken from
         * the linked timezone row. Returns '' when nothing is available.
         */
        public function get_workshop_schedule_text($workshop_id) {
            $workshop_id = absint($workshop_id);

            if ($workshop_id <= 0) {
                return '';
            }

            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "
                    SELECT tz.dtm_day, tz.dtm_time_from, tz.dtm_time_to
                    FROM {$this->table_name} w
                    INNER JOIN {$this->time_zones_table} tz ON tz.id = w.fky_timezone_id
                    WHERE w.id = %d
                    LIMIT 1
                    ",
                    $workshop_id
                ),
                ARRAY_A
            );

            if (empty($row)) {
                return '';
            }

            $parts = array();

            if (!empty($row['dtm_day'])) {
                $timestamp = strtotime((string) $row['dtm_day']);
                if ($timestamp) {
                    $parts[] = wp_date('j.n.Y', $timestamp);
                }
            }

            $time_from = substr((string) ($row['dtm_time_from'] ?? ''), 0, 5);
            $time_to   = substr((string) ($row['dtm_time_to'] ?? ''), 0, 5);

            if ($time_from !== '' && $time_from !== '00:00') {
                $parts[] = ($time_to !== '' && $time_to !== '00:00')
                    ? $time_from . '–' . $time_to . ' Uhr'
                    : $time_from . ' Uhr';
            }

            return implode(', ', $parts);
        }

        /**
         * Turn a plain-text (or lightly HTML'd) description into flyer-ready
         * paragraph markup.
         */
        protected function flyer_rich_text($value) {
            $value = trim((string) $value);

            if ($value === '') {
                return '';
            }

            if (preg_match('/<[a-z][\s\S]*>/i', $value)) {
                return wp_kses_post($value);
            }

            $value = esc_html($value);
            $value = str_replace(array("\r\n", "\r"), "\n", $value);
            $value = preg_replace('/ {2,}/', "\n\n", $value);

            $blocks = preg_split('/\n{2,}/', $value);
            $blocks = array_filter(array_map('trim', (array) $blocks), 'strlen');

            if (empty($blocks)) {
                return '';
            }

            $blocks = array_map(
                static fn($block) => '<p>' . nl2br($block) . '</p>',
                $blocks
            );

            return implode('', $blocks);
        }

        /**
         * Build the {token} => value map for one workshop flyer.
         *
         * All flyer generation logic lives here so the PDF template file stays
         * a pure template. $workshop is the (possibly slim) row from the
         * selection list; full details are re-loaded by id.
         *
         * Raw tokens (value only, template controls the markup):
         *   {workshop_title} {workshop_type} {workshop_subtitle}
         *   {workshop_leadtext} {workshop_presenters} {workshop_bodytext} {workshop_meta}
         * Block tokens (value pre-wrapped in <div class="…">, or "" when empty):
         *   {workshop_type_block} {workshop_subtitle_block} {workshop_leadtext_block}
         *   {workshop_presenters_block} {workshop_bodytext_block} {workshop_meta_block}
         */
        public function get_workshop_flyer_replacements(array $workshop, string $event_uid, string $lang = 'de'): array {
            $lang        = $this->sanitize_language($lang);
            $workshop_id = absint($this->workshop_value_ci($workshop, 'id'));

            $full = $workshop_id > 0 ? $this->get_workshop_by_id($workshop_id, $lang) : null;
            $full = is_array($full) ? array_merge($workshop, $full) : $workshop;

            $title = $this->workshop_value_ci($full, 'str_workshop_title_' . $lang);
            if ($title === '') {
                $title = $this->workshop_value_ci($full, 'str_workshop_title');
            }
            if ($title === '') {
                $title = $this->workshop_value_ci($full, 'str_workshop_title_de');
            }

            $subtitle = $this->workshop_value_ci($full, 'str_workshop_subtitle_' . $lang);
            if ($subtitle === '') {
                $subtitle = $this->workshop_value_ci($full, 'str_workshop_subtitle_de');
            }
            if ($subtitle === '') {
                $subtitle = $this->workshop_value_ci($full, 'str_workshop_type');
            }

            $lead = $this->workshop_value_ci($full, 'mem_workshop_description_' . $lang);
            if ($lead === '') {
                $lead = $this->workshop_value_ci($full, 'mem_workshop_description');
            }
            if ($lead === '') {
                $lead = $this->workshop_value_ci($full, 'mem_workshop_description_de');
            }

            $body = $this->workshop_value_ci($full, 'mem_workshop_description_long_' . $lang);
            if ($body === '') {
                $body = $this->workshop_value_ci($full, 'mem_workshop_description_long');
            }
            if ($body === '') {
                $body = $this->workshop_value_ci($full, 'mem_workshop_description_long_de');
            }

            /* "akad. Titel Vorname Nachname, Funktion, Firma" – one presenter per line. */
            $presenters_html = $this->get_workshop_presenters_detailed($workshop_id, $lang);
            if ($presenters_html === '') {
                $print = trim((string) $this->workshop_value_ci($full, 'str_presenters_print'));
                if ($print !== '') {
                    $presenters_html = nl2br(esc_html($print));
                }
            }
            $presenters = $presenters_html;

            $categories = $this->get_categories_by_workshop_id($workshop_id, $lang);

            $type_name = $this->workshop_value_ci($full, 'str_workshop_type_name');
            if ($type_name === '') {
                $type_name = $this->workshop_value_ci($full, 'str_workshop_type');
            }

            $number   = $this->workshop_value_ci($full, 'str_workshop_number');
            $schedule = $this->workshop_value_ci($full, 'str_time_print');
            if ($schedule === '') {
                $schedule = $this->get_workshop_schedule_text($workshop_id);
            }
            $room = $this->workshop_value_ci($full, 'str_room_print');

            $meta_rows = array();
            if ($number !== '') {
                $meta_rows[] = '<strong>Workshop-Nr.:</strong> ' . esc_html($number);
            }
            if ($schedule !== '') {
                $meta_rows[] = '<strong>Zeit:</strong> ' . esc_html($schedule);
            }
            if ($room !== '') {
                $meta_rows[] = '<strong>Raum:</strong> ' . esc_html($room);
            }
            if ($categories !== '') {
                $meta_rows[] = '<strong>Themenpfad:</strong> ' . esc_html($categories);
            }

            $lead_html = $this->flyer_rich_text($lead);
            $body_html = $this->flyer_rich_text($body);
            $meta_html = !empty($meta_rows) ? implode('<br>', $meta_rows) : '';

            return array(
                /* Raw tokens - value only, no wrapper. The template decides the markup. */
                '{workshop_title}'           => esc_html($title),
                '{workshop_type}'            => esc_html($type_name),
                '{workshop_subtitle}'        => esc_html($subtitle),
                '{workshop_leadtext}'        => $lead_html,
                '{workshop_presenters}'      => $presenters,
                '{workshop_bodytext}'        => $body_html,
                '{workshop_meta}'            => $meta_html,

                /* Block tokens - value wrapped in a <div class="…">, or "" when empty. */
                '{workshop_type_block}'      => $type_name !== ''
                    ? '<div class="workshop-type">' . esc_html($type_name) . '</div>'
                    : '',
                '{workshop_subtitle_block}'  => $subtitle !== ''
                    ? '<div class="subtitle">' . esc_html($subtitle) . '</div>'
                    : '',
                '{workshop_leadtext_block}'  => $lead_html !== ''
                    ? '<div class="lead">' . $lead_html . '</div>'
                    : '',
                '{workshop_presenters_block}' => $presenters !== ''
                    ? '<div class="presenters">' . $presenters . '</div>'
                    : '',
                '{workshop_bodytext_block}'  => $body_html !== ''
                    ? '<div class="text">' . $body_html . '</div>'
                    : '',
                '{workshop_meta_block}'      => $meta_html !== ''
                    ? '<div class="meta">' . $meta_html . '</div>'
                    : '',
            );
        }
}
