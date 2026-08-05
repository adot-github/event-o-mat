<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Wordings {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_wordings';
    }

    /**
     * Get all wording texts for one owner/event and language.
     *
     * Usage:
     * $wordings = $wordings_obj->get_wordings($lang, $event_uid);
     * echo $wordings['notwendige_angaben_zur_anmeldung_sind_nicht_verfuegbar_eventuell'] ?? '';
     *
     * @param string $lang      Language suffix, for example de, en, fr, it.
     * @param string $event_uid Event / owner Uid, for example xxxx-2026.
     * @return array Associative array: str_var_name => translated text.
     */
    public function get_wordings($lang = 'de', $event_uid = '') {
        $lang      = $this->sanitize_language($lang);
        $event_uid = sanitize_text_field((string) $event_uid);

        if ($event_uid === '') {
            return array();
        }

        $sql = "
            SELECT
                str_var_name,
                str_text_{$lang} AS text
            FROM {$this->table_name}
            WHERE fky_event_uid = %s
            ORDER BY str_var_name
        ";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );

        if (empty($rows)) {
            return array();
        }

        $wordings = array();

        foreach ($rows as $row) {
            $var_name = trim((string) ($row['str_var_name'] ?? ''));

            if ($var_name === '') {
                continue;
            }

            $wordings[$var_name] = isset($row['text']) ? $row['text'] : '';
        }

        return $wordings;
    }

    /**
     * Copies all records from wp_evtmgr_wordings_default into wp_evtmgr_wordings
     * for the given event_uid, skipping any that already exist (matched by str_var_name).
     * All column values are always copied (except id, fky_event_uid, dtm_date_*).
     *
     * @return int  Number of newly inserted rows, or -1 if a required table is missing.
     */
    public function sync_default_wordings(string $event_uid): int {
        $event_uid     = sanitize_text_field($event_uid);
        $table_default = 'wp_evtmgr_wordings_default';

        if ($event_uid === '') {
            return 0;
        }

        if (!$this->table_exists($table_default) || !$this->table_exists($this->table_name)) {
            return -1;
        }

        $defaults = $this->wpdb->get_results(
            "SELECT * FROM {$table_default} ORDER BY id",
            ARRAY_A
        );

        if (empty($defaults)) {
            return 0;
        }

        $existing_names = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT str_var_name FROM {$this->table_name} WHERE fky_event_uid = %s",
                $event_uid
            )
        );

        if (!is_array($existing_names)) {
            $existing_names = array();
        }

        $inserted = 0;

        foreach ($defaults as $row) {
            $var_name = (string) ($row['str_var_name'] ?? '');

            if ($var_name === '' || in_array($var_name, $existing_names, true)) {
                continue;
            }

            $data = array('fky_event_uid' => $event_uid);

            $skip = array('id', 'fky_event_uid', 'dtm_date_created', 'dtm_date_updated');
            foreach ($row as $col => $val) {
                if (!in_array($col, $skip, true)) {
                    $data[$col] = $val;
                }
            }

            $result = $this->wpdb->insert($this->table_name, $data);

            if ($result !== false) {
                $inserted++;
                $existing_names[] = $var_name;
            }
        }

        return $inserted;
    }

    protected function table_exists(string $table): bool {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );
        return $result === $table;
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}
