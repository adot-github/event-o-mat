<?php

if (!defined('ABSPATH')) {
    exit;
}

class Evtmgr_Options {

    protected $wpdb;
    protected $table;
    protected $table_default;

    public function __construct() {
        global $wpdb;

        $this->wpdb          = $wpdb;
        $this->table         = 'wp_evtmgr_options';
        $this->table_default = 'wp_evtmgr_options_default';
    }

    /**
     * Copies all records from wp_evtmgr_options_default into wp_evtmgr_options
     * for the given event_uid, skipping any that already exist (matched by str_option_name).
     * If ysn_clone_value_on_copy is set, str_option_value is copied too; otherwise it is left empty.
     *
     * @return int  Number of newly inserted rows, or -1 if a required table is missing.
     */
    public function sync_default_options(string $event_uid): int {
        $event_uid = sanitize_text_field($event_uid);

        if ($event_uid === '') {
            return 0;
        }

        if (!$this->table_exists($this->table_default) || !$this->table_exists($this->table)) {
            return -1;
        }

        $defaults = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_default} ORDER BY id",
            ARRAY_A
        );

        if (empty($defaults)) {
            return 0;
        }

        $existing_names = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT str_option_name FROM {$this->table} WHERE fky_event_uid = %s",
                $event_uid
            )
        );

        if (!is_array($existing_names)) {
            $existing_names = array();
        }

        $inserted = 0;

        foreach ($defaults as $row) {
            $option_name = (string) ($row['str_option_name'] ?? '');

            if ($option_name === '' || in_array($option_name, $existing_names, true)) {
                continue;
            }

            $clone_value  = !empty($row['ysn_clone_value_on_copy']);
            $option_value = $clone_value ? (string) ($row['str_option_value'] ?? '') : '';

            $data = array(
                'fky_event_uid'    => $event_uid,
                'str_option_name'  => $option_name,
                'str_option_value' => $option_value,
            );

            // Copy remaining columns from the default row (skip id, already-set columns, and ysn_clone_value_on_copy)
            $skip = array('id', 'fky_event_uid', 'str_option_name', 'str_option_value', 'ysn_clone_value_on_copy');
            foreach ($row as $col => $val) {
                if (!in_array($col, $skip, true)) {
                    $data[$col] = $val;
                }
            }

            $result = $this->wpdb->insert($this->table, $data);

            if ($result !== false) {
                $inserted++;
                $existing_names[] = $option_name;
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

    public function get_option(string $event_uid, string $name): ?string {
        $event_uid = sanitize_text_field($event_uid);
        $name      = sanitize_text_field($name);

        if ($event_uid === '' || $name === '') {
            return null;
        }

        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT str_option_value FROM {$this->table} WHERE fky_event_uid = %s AND str_option_name = %s LIMIT 1",
                $event_uid,
                $name
            )
        );

        return $value !== null ? (string) $value : null;
    }

    public function get_all_options(string $event_uid): array {
        $event_uid = sanitize_text_field($event_uid);

        if ($event_uid === '') {
            return array();
        }

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT str_option_name, str_option_value FROM {$this->table} WHERE fky_event_uid = %s ORDER BY id",
                $event_uid
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        $result = array();
        foreach ($rows as $row) {
            $result[(string) $row['str_option_name']] = (string) $row['str_option_value'];
        }

        return $result;
    }

    /**
     * Returns true when DocRaptor should run in test mode (no credits consumed).
     * Defaults to test mode unless pdf_creation_mode = 'live'.
     */
    public static function is_pdf_test_mode(string $event_uid): bool {
        if ($event_uid === '') {
            return true;
        }
        return (new self())->get_option($event_uid, 'pdf_creation_mode') !== 'live';
    }

    public function set_option(string $event_uid, string $name, string $value): bool {
        $event_uid = sanitize_text_field($event_uid);
        $name      = sanitize_text_field($name);

        if ($event_uid === '' || $name === '') {
            return false;
        }

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE fky_event_uid = %s AND str_option_name = %s LIMIT 1",
                $event_uid,
                $name
            )
        );

        if ($existing !== null) {
            $result = $this->wpdb->update(
                $this->table,
                array('str_option_value' => $value),
                array('fky_event_uid' => $event_uid, 'str_option_name' => $name)
            );
        } else {
            $result = $this->wpdb->insert(
                $this->table,
                array('fky_event_uid' => $event_uid, 'str_option_name' => $name, 'str_option_value' => $value)
            );
        }

        return $result !== false;
    }
}
