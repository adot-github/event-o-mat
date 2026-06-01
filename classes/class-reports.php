<?php

if (!defined('ABSPATH')) {
    exit;
}

class Event_Registration_Reports {

    protected $wpdb;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;
    }

    public function get_report_rows($args) {
        $args = wp_parse_args($args, array(
            'table'           => '',
            'fields'          => array(),
            'custom_sql'      => '',
            'event_uid'       => '',
            'owner_column'    => 'fky_event_uid',
            'order_by'        => '',
            'order_direction' => 'ASC',
        ));

        $event_uid = sanitize_text_field((string) $args['event_uid']);

        if ($event_uid === '') {
            return array();
        }

        if (!empty($args['custom_sql'])) {
            return $this->get_report_rows_from_custom_sql($args);
        }

        return $this->get_report_rows_from_table($args);
    }

    protected function get_report_rows_from_table($args) {
        $table        = $this->sanitize_identifier($args['table']);
        $owner_column = $this->sanitize_identifier($args['owner_column']);
        $event_uid    = sanitize_text_field((string) $args['event_uid']);

        if ($table === '' || $owner_column === '') {
            return array();
        }

        $fields = $this->sanitize_field_list($args['fields']);

        if (empty($fields)) {
            $select = '*';
        } else {
            $select = implode(', ', array_map(function($field) {
                return '`' . $field . '`';
            }, array_keys($fields)));
        }

        $order_sql = $this->build_order_sql(
            $args['order_by'],
            $args['order_direction']
        );

        $sql = "
            SELECT {$select}
            FROM `{$table}`
            WHERE `{$owner_column}` = %s
            {$order_sql}
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    protected function get_report_rows_from_custom_sql($args) {
        $custom_sql   = trim((string) $args['custom_sql']);
        $owner_column = $this->sanitize_identifier($args['owner_column']);
        $event_uid    = sanitize_text_field((string) $args['event_uid']);

        if ($custom_sql === '' || $owner_column === '' || $event_uid === '') {
            return array();
        }

        $custom_sql = $this->normalize_custom_sql($custom_sql);

        if (!$this->is_safe_select_sql($custom_sql)) {
            return array();
        }

        /*
         * The custom SQL must expose the owner column, for example:
         * r.fky_event_uid
         */
        $sql = "
            SELECT *
            FROM (
                {$custom_sql}
            ) AS report_source
            WHERE report_source.`{$owner_column}` = %s
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    protected function normalize_custom_sql($sql) {
        $sql = trim((string) $sql);

        /*
         * Remove trailing semicolons because the query is wrapped
         * inside a subquery.
         */
        $sql = preg_replace('/;+\s*$/', '', $sql);

        return $sql;
    }

    protected function is_safe_select_sql($sql) {
        $sql_trimmed = ltrim((string) $sql);

        if (stripos($sql_trimmed, 'SELECT') !== 0) {
            return false;
        }

        $blocked = array(
            'INSERT ',
            'UPDATE ',
            'DELETE ',
            'DROP ',
            'ALTER ',
            'TRUNCATE ',
            'CREATE ',
            'REPLACE ',
            'GRANT ',
            'REVOKE ',
        );

        $sql_upper = strtoupper($sql_trimmed);

        foreach ($blocked as $word) {
            if (strpos($sql_upper, $word) !== false) {
                return false;
            }
        }

        return true;
    }

    protected function build_order_sql($order_by, $default_direction = 'ASC') {
        if (empty($order_by)) {
            return '';
        }

        $order_parts = array();

        /*
         * Preferred format:
         *
         * $report_order_by = array(
         *     'str_last_name'  => 'ASC',
         *     'str_first_name' => 'ASC',
         * );
         */
        if (is_array($order_by)) {
            foreach ($order_by as $field => $direction) {
                $field = $this->sanitize_identifier($field);

                if ($field === '') {
                    continue;
                }

                $direction = strtoupper((string) $direction);
                $direction = $direction === 'DESC' ? 'DESC' : 'ASC';

                $order_parts[] = "`{$field}` {$direction}";
            }
        } else {
            /*
             * Backwards-compatible string format:
             *
             * $report_order_by = 'str_last_name ASC, str_first_name ASC';
             * $report_order_by = 'str_last_name, str_first_name';
             */
            $items = explode(',', (string) $order_by);

            foreach ($items as $item) {
                $item = trim($item);

                if ($item === '') {
                    continue;
                }

                $pieces = preg_split('/\s+/', $item);

                $field = $this->sanitize_identifier($pieces[0] ?? '');

                if ($field === '') {
                    continue;
                }

                $direction = strtoupper($pieces[1] ?? $default_direction);
                $direction = $direction === 'DESC' ? 'DESC' : 'ASC';

                $order_parts[] = "`{$field}` {$direction}";
            }
        }

        if (empty($order_parts)) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', $order_parts);
    }

    protected function sanitize_identifier($identifier) {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return '';
        }

        return preg_replace('/[^A-Za-z0-9_]/', '', $identifier);
    }

    protected function sanitize_field_list($fields) {
        if (!is_array($fields)) {
            return array();
        }

        $clean = array();

        foreach ($fields as $field => $label) {
            $field = $this->sanitize_identifier($field);

            if ($field === '') {
                continue;
            }

            $clean[$field] = sanitize_text_field((string) $label);
        }

        return $clean;
    }

    public function render_table($rows, $fields = array(), $fields_as_list = array()) {
        if (empty($rows)) {
            echo '<div class="alert alert-warning">Keine Daten gefunden.</div>';
            return;
        }

        if (empty($fields)) {
            $first_row = reset($rows);
            $fields = array();

            foreach (array_keys($first_row) as $field_name) {
                $fields[$field_name] = $field_name;
            }
        }

        echo '<div class="event-report-table-scroll js-report-table-scroll">';
        echo '<table class="table table-striped table-hover table-bordered align-middle event-report-table" id="event-report-table">';

        echo '<thead>';
        echo '<tr>';

        foreach ($fields as $field => $label) {
            echo '<th>' . esc_html($label) . '</th>';
        }

        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';

        foreach ($rows as $row) {
            echo '<tr>';

            foreach ($fields as $field => $label) {
                $value = $row[$field] ?? '';

                if ($this->is_list_field($field, $fields_as_list)) {
                    echo '<td>' . $this->render_list_value($value) . '</td>';
                } else {
                    echo '<td>' . esc_html($value) . '</td>';
                }
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    protected function is_list_field($field, $fields_as_list) {
        if (!is_array($fields_as_list)) {
            return false;
        }

        foreach ($fields_as_list as $list_field) {
            if (strtolower((string) $list_field) === strtolower((string) $field)) {
                return true;
            }
        }

        return false;
    }

    protected function render_list_value($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $items = explode('¦', $value);
        $items = array_map('trim', $items);
        $items = array_filter($items, static function($item) {
            return $item !== '';
        });

        if (empty($items)) {
            return '';
        }

        $html = '<ul class="mb-0 ps-3">';

        foreach ($items as $item) {
            $html .= '<li>' . esc_html($item) . '</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}