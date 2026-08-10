<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Wordings {

    protected $wpdb;
    protected $table_name;
    protected $table_events;

    public function __construct() {
        global $wpdb;

        $this->wpdb         = $wpdb;
        $this->table_name   = 'wp_evtmgr_wordings';
        $this->table_events = 'wp_evtmgr_events';
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
     * Synchronizes wp_evtmgr_wordings with wp_evtmgr_wordings_default for every existing event:
     * - inserts wordings that are missing (matched by str_var_name),
     * - updates existing wordings with the default's current column values,
     *   except str_text_de / str_text_en / str_text_fr / str_text_it (translations are never overwritten),
     * - deletes wordings that no longer exist in wp_evtmgr_wordings_default.
     *
     * @return int  Number of newly inserted rows (across all events), or -1 if a required table is missing.
     */
    public function sync_default_wordings(): int {
        $table_default = 'wp_evtmgr_wordings_default';

        if (!$this->table_exists($table_default) || !$this->table_exists($this->table_name) || !$this->table_exists($this->table_events)) {
            return -1;
        }

        $defaults = $this->wpdb->get_results(
            "SELECT * FROM {$table_default} ORDER BY id",
            ARRAY_A
        );

        if (empty($defaults)) {
            return 0;
        }

        $event_uids = $this->wpdb->get_col("SELECT event_uid FROM {$this->table_events}");

        if (!is_array($event_uids) || empty($event_uids)) {
            return 0;
        }

        $inserted = 0;

        foreach ($event_uids as $event_uid) {
            $event_uid = sanitize_text_field((string) $event_uid);

            if ($event_uid === '') {
                continue;
            }

            $inserted += $this->sync_default_wordings_for_event($event_uid, $defaults);
        }

        return $inserted;
    }

    /**
     * Copies missing default wordings into wp_evtmgr_wordings for a single event,
     * updates existing ones (except translation fields) and deletes obsolete ones.
     */
    protected function sync_default_wordings_for_event(string $event_uid, array $defaults): int {
        $existing_rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE fky_event_uid = %s",
                $event_uid
            ),
            ARRAY_A
        );

        $existing_by_name = array();
        foreach ((array) $existing_rows as $existing_row) {
            $existing_var_name = (string) ($existing_row['str_var_name'] ?? '');
            if ($existing_var_name !== '') {
                $existing_by_name[$existing_var_name] = $existing_row;
            }
        }

        $skip_insert = array('id', 'fky_event_uid', 'dtm_date_created', 'dtm_date_updated');
        $skip_update = array_merge($skip_insert, array(
            'str_var_name', 'str_text_de', 'str_text_en', 'str_text_fr', 'str_text_it',
        ));

        $inserted     = 0;
        $default_names = array();

        foreach ($defaults as $row) {
            $var_name = (string) ($row['str_var_name'] ?? '');

            if ($var_name === '') {
                continue;
            }

            $default_names[$var_name] = true;

            if (isset($existing_by_name[$var_name])) {
                $data = array();
                foreach ($row as $col => $val) {
                    if (!in_array($col, $skip_update, true)) {
                        $data[$col] = $val;
                    }
                }

                if (!empty($data)) {
                    $this->wpdb->update(
                        $this->table_name,
                        $data,
                        array(
                            'fky_event_uid' => $event_uid,
                            'str_var_name'  => $var_name,
                        )
                    );
                }

                continue;
            }

            $data = array('fky_event_uid' => $event_uid);
            foreach ($row as $col => $val) {
                if (!in_array($col, $skip_insert, true)) {
                    $data[$col] = $val;
                }
            }

            $result = $this->wpdb->insert($this->table_name, $data);

            if ($result !== false) {
                $inserted++;
            }
        }

        $obsolete_names = array_diff(array_keys($existing_by_name), array_keys($default_names));

        foreach ($obsolete_names as $obsolete_name) {
            $this->wpdb->delete(
                $this->table_name,
                array(
                    'fky_event_uid' => $event_uid,
                    'str_var_name'  => $obsolete_name,
                )
            );
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
