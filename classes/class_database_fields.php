<?php

if (!defined('ABSPATH')) {
    exit;
}

class Evtmgr_Database_Fields {

    protected $wpdb;
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = 'wp_evtmgr_database_fields';
    }

    /**
     * Scans all tables matching *evtmgr* and inserts any field not yet
     * present in wp_evtmgr_database_fields (keyed by str_table_name + str_frm_field_name).
     *
     * @return array{inserted:int, skipped:int, details:array}
     */
    public function extract(): array {
        $result = [
            'inserted' => 0,
            'skipped'  => 0,
            'details'  => [],
        ];

        $tables = $this->wpdb->get_col("SHOW TABLES LIKE '%evtmgr%'");

        if (empty($tables)) {
            return $result;
        }

        // Load all existing combinations once for performance
        $existing_raw = $this->wpdb->get_results(
            "SELECT str_table_name, str_frm_field_name FROM {$this->table}",
            ARRAY_A
        );

        $existing = [];
        foreach ((array) $existing_raw as $row) {
            $existing[$row['str_table_name'] . '||' . $row['str_frm_field_name']] = true;
        }

        foreach ($tables as $table_name) {
            if ($table_name === $this->table) {
                continue;
            }

            $columns = $this->wpdb->get_results(
                "SHOW COLUMNS FROM `{$table_name}`",
                ARRAY_A
            );

            if (empty($columns)) {
                continue;
            }

            foreach ($columns as $col) {
                $field_name = (string) $col['Field'];
                $key        = $table_name . '||' . $field_name;

                if (isset($existing[$key])) {
                    $result['skipped']++;
                    $result['details'][] = [
                        'table'  => $table_name,
                        'field'  => $field_name,
                        'status' => 'skipped',
                    ];
                    continue;
                }

                $inserted = $this->wpdb->insert(
                    $this->table,
                    [
                        'str_table_name'     => $table_name,
                        'str_frm_field_name' => $field_name,
                    ],
                    ['%s', '%s']
                );

                if ($inserted !== false) {
                    $existing[$key] = true;
                    $result['inserted']++;
                    $result['details'][] = [
                        'table'  => $table_name,
                        'field'  => $field_name,
                        'status' => 'inserted',
                    ];
                } else {
                    $result['details'][] = [
                        'table'  => $table_name,
                        'field'  => $field_name,
                        'status' => 'error',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Returns an associative array of field labels for a given table.
     * Key   = str_frm_field_name
     * Value = str_label_de (falls back to str_frm_field_name if empty)
     *
     * @param string $str_table_name  e.g. 'wp_evtmgr_options'
     * @return array<string, string>
     */
    public function get_labels(string $str_table_name): array {
        $str_table_name = sanitize_text_field($str_table_name);

        if ($str_table_name === '') {
            return [];
        }

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE str_table_name = %s",
                $str_table_name
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        // Detect available label column from the first row
        $first       = $rows[0];
        $label_col   = null;
        $candidates  = ['str_field_label_de', 'str_label_de', 'str_label', 'str_description', 'str_frm_field_label'];
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $first)) {
                $label_col = $candidate;
                break;
            }
        }

        $labels = [];
        foreach ($rows as $row) {
            $key          = (string) $row['str_frm_field_name'];
            $value        = $label_col !== null && $row[$label_col] !== '' && $row[$label_col] !== null
                ? (string) $row[$label_col]
                : $key;
            $labels[$key] = $value;
        }

        return $labels;
    }

    /**
     * Copies str_field_label_de from wp_evtmgr_database_fields_labels into
     * wp_evtmgr_database_fields for every row whose label is still empty.
     * Matching key: str_frm_field_name.
     *
     * @return array{updated:int, skipped:int}
     */
    public function sync_labels_from_reference(): array {
        $ref_table = 'wp_evtmgr_database_fields_labels';

        $updated = $this->wpdb->query("
            UPDATE {$this->table} df
            INNER JOIN {$ref_table} lbl ON lbl.str_frm_field_name = df.str_frm_field_name
            SET df.str_field_label_de = lbl.str_field_label_de
            WHERE (df.str_field_label_de IS NULL OR df.str_field_label_de = '')
              AND lbl.str_field_label_de IS NOT NULL
              AND lbl.str_field_label_de != ''
        ");

        $total = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");

        return [
            'updated' => $updated === false ? 0 : (int) $updated,
            'skipped' => $total - ($updated === false ? 0 : (int) $updated),
        ];
    }

    public function get_all(): array {
        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY str_table_name, str_frm_field_name",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }
}
