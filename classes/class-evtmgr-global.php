<?php

if (!defined('ABSPATH')) {
    exit;
}

class Evtmgr_Global {

    protected $wpdb;

    /**
     * Tables that are directly event-bound.
     * wp_evtmgr_events is handled separately because its event key is event_uid.
     */
    protected $event_tables = array(
        'wp_evtmgr_audience',
        'wp_evtmgr_pricing',
        'wp_evtmgr_presenters',
        'wp_evtmgr_rooms',
        'wp_evtmgr_slots',
        'wp_evtmgr_timezones',
        'wp_evtmgr_wordings',
        'wp_evtmgr_workshops',
        'wp_evtmgr_registrations_workshops',
    );

    /**
     * FK fields inside duplicated event tables that must point to cloned records.
     * Key: table being duplicated. Value: FK column => source table whose ID map should be used.
     */
    protected $foreign_key_remaps = array(
        'wp_evtmgr_workshops' => array(
            'fky_timezone_id' => 'wp_evtmgr_timezones',
            'fky_slot_id'      => 'wp_evtmgr_slots',
            'fky_room_id'      => 'wp_evtmgr_rooms',
        ),
    );

    /**
     * Self-referencing FK fields that can only be corrected after the table was copied.
     */
    protected $self_reference_remaps = array(
        'wp_evtmgr_timezones' => array(
            'fky_parent_timezone_id' => 'wp_evtmgr_timezones',
        ),
    );

    /**
     * Relation tables. Rows are duplicated only if their FK values were cloned.
     */
    protected $relation_tables = array(
        'wp_evtmgr_tbx_timezones_presenters' => array(
            'fky_timezone_id' => 'wp_evtmgr_timezones',
            'fky_person_id'   => 'wp_evtmgr_presenters',
        ),
        'wp_evtmgr_tbx_workshops_presenters' => array(
            'fky_workshop_id' => 'wp_evtmgr_workshops',
            'fky_person_id'   => 'wp_evtmgr_presenters',
        ),
        'wp_evtmgr_tbx_workshops_audience' => array(
            'fky_workshop_id' => 'wp_evtmgr_workshops',
            'fky_audience_id' => 'wp_evtmgr_audience',
        ),
    );

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Return all events for the first step dropdown.
     */
    public function get_events_for_duplicate_dropdown() {
        $table = $this->table_name('wp_evtmgr_events');

        if (!$this->table_exists($table)) {
            return array();
        }

        $label_column = $this->column_exists($table, 'str_event_name_de') ? 'str_event_name_de' : 'event_uid';

        $sql = "
            SELECT event_uid, {$label_column} AS str_event_name
            FROM {$table}
            ORDER BY {$label_column}
        ";

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    /**
     * Clone one complete event.
     *
     * @param string $old_event_uid Existing event_uid / fky_event_uid.
     * @param string $new_event_uid New event_uid / fky_event_uid.
     * @return array Summary.
     */
    public function duplicate_event($old_event_uid, $new_event_uid) {
        $old_event_uid = sanitize_text_field((string) $old_event_uid);
        $new_event_uid = sanitize_text_field((string) $new_event_uid);

        $summary = array(
            'success'       => false,
            'old_event_uid' => $old_event_uid,
            'new_event_uid' => $new_event_uid,
            'tables'        => array(),
            'relations'     => array(),
            'id_maps'       => array(),
            'errors'        => array(),
        );

        if ($old_event_uid === '' || $new_event_uid === '') {
            $summary['errors'][] = 'Old and new event UID are required.';
            return $summary;
        }

        if ($old_event_uid === $new_event_uid) {
            $summary['errors'][] = 'Old and new event UID must be different.';
            return $summary;
        }

        $events_table = $this->table_name('wp_evtmgr_events');

        if (!$this->table_exists($events_table)) {
            $summary['errors'][] = 'Events table does not exist: ' . $events_table;
            return $summary;
        }

        if (!$this->event_exists($old_event_uid)) {
            $summary['errors'][] = 'Old event UID does not exist: ' . $old_event_uid;
            return $summary;
        }

        if ($this->event_exists($new_event_uid)) {
            $summary['errors'][] = 'New event UID already exists: ' . $new_event_uid;
            return $summary;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            $event_result = $this->duplicate_single_row_by_key(
                $events_table,
                'event_uid',
                $old_event_uid,
                array(
                    'event_uid' => $new_event_uid,
                )
            );

            if (empty($event_result['inserted'])) {
                throw new RuntimeException('Could not duplicate event row.');
            }

            $summary['tables']['wp_evtmgr_events'] = $event_result;

            foreach ($this->event_tables as $base_table) {
                $table = $this->table_name($base_table);

                if (!$this->table_exists($table)) {
                    $summary['tables'][$base_table] = array(
                        'skipped' => true,
                        'reason'  => 'Table does not exist.',
                    );
                    continue;
                }

                if (!$this->column_exists($table, 'fky_event_uid')) {
                    $summary['tables'][$base_table] = array(
                        'skipped' => true,
                        'reason'  => 'Column fky_event_uid does not exist.',
                    );
                    continue;
                }

                $result = $this->duplicate_rows_by_event_uid(
                    $table,
                    $old_event_uid,
                    $new_event_uid,
                    $summary['id_maps'],
                    $base_table
                );
                $summary['tables'][$base_table] = $result;

                if (!empty($result['id_map'])) {
                    $summary['id_maps'][$base_table] = $result['id_map'];
                }
            }

            foreach ($this->relation_tables as $base_table => $fk_map) {
                $table = $this->table_name($base_table);

                if (!$this->table_exists($table)) {
                    $summary['relations'][$base_table] = array(
                        'skipped' => true,
                        'reason'  => 'Table does not exist.',
                    );
                    continue;
                }

                $result = $this->duplicate_relation_table($table, $fk_map, $summary['id_maps']);
                $summary['relations'][$base_table] = $result;
            }

            $this->wpdb->query('COMMIT');
            $summary['success'] = true;
        } catch (Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            $summary['success']  = false;
            $summary['errors'][] = $e->getMessage();
        }

        return $summary;
    }

    protected function event_exists($event_uid) {
        $table = $this->table_name('wp_evtmgr_events');

        $count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE event_uid = %s",
                $event_uid
            )
        );

        return $count > 0;
    }

    protected function duplicate_single_row_by_key($table, $key_column, $old_value, array $overrides) {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$key_column} = %s LIMIT 1",
                $old_value
            ),
            ARRAY_A
        );

        if (empty($row)) {
            return array(
                'inserted' => false,
                'reason'   => 'Source row not found.',
            );
        }

        $row = $this->prepare_row_for_insert($table, $row, array_keys($overrides));

        foreach ($overrides as $column => $value) {
            if ($this->column_exists($table, $column)) {
                $row[$column] = $value;
            }
        }

        $inserted = $this->wpdb->insert($table, $row);

        if ($inserted === false) {
            throw new RuntimeException('Database insert failed for ' . $table . ': ' . $this->wpdb->last_error);
        }

        return array(
            'inserted' => true,
            'count'    => 1,
        );
    }

    protected function duplicate_rows_by_event_uid($table, $old_event_uid, $new_event_uid, array $id_maps = array(), $base_table = '') {
        $primary_key = $this->get_primary_key($table);
        $base_table  = $base_table !== '' ? $base_table : $this->base_table_name($table);

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE fky_event_uid = %s",
                $old_event_uid
            ),
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return array(
                'count'  => 0,
                'id_map' => array(),
            );
        }

        $id_map = array();
        $count  = 0;
        $self_reference_updates = array();

        foreach ($rows as $row) {
            $source_row = $row;
            $old_id = '';

            if ($primary_key !== '' && array_key_exists($primary_key, $row)) {
                $old_id = (string) $row[$primary_key];
            }

            $row = $this->prepare_row_for_insert($table, $row, array('fky_event_uid'));
            $row['fky_event_uid'] = $new_event_uid;
            $row = $this->apply_foreign_key_remaps($base_table, $row, $id_maps);

            $inserted = $this->wpdb->insert($table, $row);

            if ($inserted === false) {
                throw new RuntimeException('Database insert failed for ' . $table . ': ' . $this->wpdb->last_error);
            }

            $new_id = $this->wpdb->insert_id;

            if ($primary_key !== '' && $old_id !== '') {
                if ($new_id <= 0 && isset($row[$primary_key])) {
                    $new_id = $row[$primary_key];
                }

                if ((string) $new_id !== '' && (int) $new_id > 0) {
                    $id_map[(string) $old_id] = (string) $new_id;
                    $self_reference_updates[] = array(
                        'old_id'     => (string) $old_id,
                        'new_id'     => (string) $new_id,
                        'source_row' => $source_row,
                    );
                }
            }

            $count++;
        }

        $this->apply_self_reference_remaps($table, $base_table, $primary_key, $self_reference_updates, $id_map);

        return array(
            'count'       => $count,
            'primary_key' => $primary_key,
            'id_map'      => $id_map,
        );
    }

    protected function duplicate_relation_table($table, array $fk_map, array $id_maps) {
        $primary_key = $this->get_primary_key($table);

        foreach ($fk_map as $fk_column => $source_base_table) {
            if (!$this->column_exists($table, $fk_column)) {
                return array(
                    'skipped' => true,
                    'reason'  => 'Missing FK column: ' . $fk_column,
                );
            }

            if (empty($id_maps[$source_base_table])) {
                return array(
                    'skipped' => true,
                    'reason'  => 'Missing ID map for: ' . $source_base_table,
                );
            }
        }

        $where_parts = array();
        $params      = array();

        foreach ($fk_map as $fk_column => $source_base_table) {
            $old_ids = array_keys($id_maps[$source_base_table]);

            if (empty($old_ids)) {
                return array(
                    'count' => 0,
                );
            }

            $placeholders = implode(',', array_fill(0, count($old_ids), '%d'));
            $where_parts[] = "{$fk_column} IN ({$placeholders})";
            $params = array_merge($params, array_map('absint', $old_ids));
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where_parts);
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);

        if (!is_array($rows) || empty($rows)) {
            return array(
                'count' => 0,
            );
        }

        $count = 0;

        foreach ($rows as $row) {
            $row = $this->prepare_relation_row_for_insert($table, $row, array_keys($fk_map));

            foreach ($fk_map as $fk_column => $source_base_table) {
                $old_fk = (string) $row[$fk_column];
                $row[$fk_column] = $id_maps[$source_base_table][$old_fk];
            }

            $inserted = $this->wpdb->insert($table, $row);

            if ($inserted === false) {
                throw new RuntimeException('Relation insert failed for ' . $table . ': ' . $this->wpdb->last_error);
            }

            $count++;
        }

        return array(
            'count' => $count,
        );
    }

    protected function apply_foreign_key_remaps($base_table, array $row, array $id_maps) {
        if (empty($this->foreign_key_remaps[$base_table])) {
            return $row;
        }

        foreach ($this->foreign_key_remaps[$base_table] as $fk_column => $source_base_table) {
            if (!array_key_exists($fk_column, $row)) {
                continue;
            }

            $old_fk = (string) $row[$fk_column];

            if ($old_fk === '' || $old_fk === '0') {
                continue;
            }

            if (!empty($id_maps[$source_base_table][$old_fk])) {
                $row[$fk_column] = $id_maps[$source_base_table][$old_fk];
            }
        }

        return $row;
    }

    protected function apply_self_reference_remaps($table, $base_table, $primary_key, array $self_reference_updates, array $id_map) {
        if ($primary_key === '' || empty($self_reference_updates) || empty($this->self_reference_remaps[$base_table])) {
            return;
        }

        foreach ($this->self_reference_remaps[$base_table] as $fk_column => $source_base_table) {
            if (!$this->column_exists($table, $fk_column)) {
                continue;
            }

            foreach ($self_reference_updates as $update_info) {
                $source_row = $update_info['source_row'];

                if (!array_key_exists($fk_column, $source_row)) {
                    continue;
                }

                $old_fk = (string) $source_row[$fk_column];

                if ($old_fk === '' || $old_fk === '0' || empty($id_map[$old_fk])) {
                    continue;
                }

                $updated = $this->wpdb->update(
                    $table,
                    array(
                        $fk_column => $id_map[$old_fk],
                    ),
                    array(
                        $primary_key => $update_info['new_id'],
                    ),
                    array('%d'),
                    array('%d')
                );

                if ($updated === false) {
                    throw new RuntimeException('Self reference update failed for ' . $table . ': ' . $this->wpdb->last_error);
                }
            }
        }
    }

    /**
     * Prepare normal duplicated rows.
     * All primary key columns are removed unless explicitly preserved.
     * This fixes composite primary keys such as wp_evtmgr_events where id must not be copied.
     */
    protected function prepare_row_for_insert($table, array $row, array $preserve_primary_key_columns = array()) {
        $primary_keys = $this->get_primary_key_columns($table);
        $preserve_primary_key_columns = array_map('strtolower', $preserve_primary_key_columns);

        foreach ($primary_keys as $primary_key) {
            if (in_array(strtolower($primary_key), $preserve_primary_key_columns, true)) {
                continue;
            }

            unset($row[$primary_key]);
        }

        return $row;
    }

    /**
     * Prepare relation rows.
     * Composite primary keys made from FK columns must stay, because the FK values are remapped.
     * Auto-increment primary keys are removed.
     */
    protected function prepare_relation_row_for_insert($table, array $row, array $preserve_columns = array()) {
        $primary_keys = $this->get_primary_key_columns($table);
        $preserve_columns = array_map('strtolower', $preserve_columns);

        foreach ($primary_keys as $primary_key) {
            if (in_array(strtolower($primary_key), $preserve_columns, true)) {
                continue;
            }

            if ($this->is_auto_increment($table, $primary_key)) {
                unset($row[$primary_key]);
            }
        }

        return $row;
    }

    protected function base_table_name($table) {
        $table = (string) $table;
        $prefix = (string) $this->wpdb->prefix;

        if ($prefix !== '' && strpos($table, $prefix) === 0) {
            return 'wp_' . substr($table, strlen($prefix));
        }

        return $table;
    }

    protected function table_name($base_table) {
        $base_table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $base_table);

        if (strpos($base_table, 'wp_') === 0 && $this->wpdb->prefix !== 'wp_') {
            return $this->wpdb->prefix . substr($base_table, 3);
        }

        return $base_table;
    }

    protected function table_exists($table) {
        $table_name = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return $table_name === $table;
    }

    protected function column_exists($table, $column) {
        $column = sanitize_key($column);

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column)
        );

        return !empty($result);
    }

    protected function get_primary_key($table) {
        $rows = $this->wpdb->get_results("SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'", ARRAY_A);

        if (!empty($rows[0]['Column_name'])) {
            return $rows[0]['Column_name'];
        }

        return '';
    }

    protected function get_primary_key_columns($table) {
        $rows = $this->wpdb->get_results("SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'", ARRAY_A);

        if (empty($rows)) {
            return array();
        }

        $columns = array();

        foreach ($rows as $row) {
            if (!empty($row['Column_name'])) {
                $columns[] = $row['Column_name'];
            }
        }

        return array_values(array_unique($columns));
    }

    protected function is_auto_increment($table, $column) {
        $column = sanitize_key($column);

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column),
            ARRAY_A
        );

        return !empty($row['Extra']) && stripos($row['Extra'], 'auto_increment') !== false;
    }


    /**
     * Delete one registered person by ID for the selected event and clean orphaned records.
     *
     * @param int|string $person_id Person table ID.
     * @param string $event_uid Current event UID.
     * @return array Summary.
     */
    public function delete_registration_by_person_id($person_id, $event_uid) {
        $person_id = absint($person_id);
        $event_uid = sanitize_text_field((string) $event_uid);

        $summary = array(
            'success'   => false,
            'person_id' => $person_id,
            'event_uid' => $event_uid,
            'person'    => array(),
            'deleted'   => 0,
            'cleanup'   => array(),
            'errors'    => array(),
        );

        if ($person_id <= 0) {
            $summary['errors'][] = 'Person ID is required.';
            return $summary;
        }

        if ($event_uid === '') {
            $summary['errors'][] = 'Event UID is required.';
            return $summary;
        }

        $persons_table = $this->table_name('wp_evtmgr_persons');

        if (!$this->table_exists($persons_table)) {
            $summary['errors'][] = 'Persons table does not exist: ' . $persons_table;
            return $summary;
        }

        if (!$this->column_exists($persons_table, 'id')) {
            $summary['errors'][] = 'Column id does not exist in ' . $persons_table . '.';
            return $summary;
        }

        if (!$this->column_exists($persons_table, 'fky_event_uid')) {
            $summary['errors'][] = 'Column fky_event_uid does not exist in ' . $persons_table . '.';
            return $summary;
        }

        $person = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM `{$persons_table}` WHERE `id` = %d AND `fky_event_uid` = %s LIMIT 1",
                $person_id,
                $event_uid
            ),
            ARRAY_A
        );

        if (empty($person)) {
            $summary['errors'][] = 'Person was not found for this event.';
            return $summary;
        }

        $summary['person'] = array(
            'id'              => $person_id,
            'str_first_name'  => isset($person['str_first_name']) ? (string) $person['str_first_name'] : '',
            'str_last_name'   => isset($person['str_last_name']) ? (string) $person['str_last_name'] : '',
            'str_email'       => isset($person['str_email']) ? (string) $person['str_email'] : '',
        );

        $this->wpdb->query('START TRANSACTION');

        try {
            $deleted = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM `{$persons_table}` WHERE `id` = %d AND `fky_event_uid` = %s",
                    $person_id,
                    $event_uid
                )
            );

            if ($deleted === false) {
                throw new RuntimeException('Delete failed for ' . $persons_table . ': ' . $this->wpdb->last_error);
            }

            $summary['deleted'] = (int) $deleted;
            $summary['cleanup'] = $this->clean_database();

            if (empty($summary['cleanup']['success'])) {
                $errors = !empty($summary['cleanup']['errors']) && is_array($summary['cleanup']['errors'])
                    ? $summary['cleanup']['errors']
                    : array('Database cleanup reported an error.');

                throw new RuntimeException(implode(' ', $errors));
            }

            $this->wpdb->query('COMMIT');
            $summary['success'] = true;
        } catch (Throwable $e) {
            $this->wpdb->query('ROLLBACK');

            $summary['success']  = false;
            $summary['errors'][] = $e->getMessage();
        }

        return $summary;
    }

    /**
     * Delete one complete event by UID and clean orphaned database records afterwards.
     *
     * @param string $event_uid Existing event_uid / fky_event_uid.
     * @return array Summary.
     */
    public function delete_event_by_uid($event_uid) {
        $event_uid = sanitize_text_field((string) $event_uid);

        $summary = array(
            'success'   => false,
            'event_uid' => $event_uid,
            'tables'    => array(),
            'event'     => array(),
            'cleanup'   => array(),
            'errors'    => array(),
        );

        if ($event_uid === '') {
            $summary['errors'][] = 'Event UID is required.';
            return $summary;
        }

        $events_table = $this->table_name('wp_evtmgr_events');

        if (!$this->table_exists($events_table)) {
            $summary['errors'][] = 'Events table does not exist: ' . $events_table;
            return $summary;
        }

        if (!$this->event_exists($event_uid)) {
            $summary['errors'][] = 'Event UID does not exist: ' . $event_uid;
            return $summary;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            $event_bound_tables = $this->get_tables_with_column('fky_event_uid');

            foreach ($event_bound_tables as $table) {
                /*
                 * The event master table is deleted separately below.
                 */
                if ($table === $events_table) {
                    continue;
                }

                $deleted = $this->wpdb->query(
                    $this->wpdb->prepare(
                        "DELETE FROM `{$table}` WHERE `fky_event_uid` = %s",
                        $event_uid
                    )
                );

                if ($deleted === false) {
                    throw new RuntimeException('Delete failed for ' . $table . ': ' . $this->wpdb->last_error);
                }

                $summary['tables'][$this->base_table_name($table)] = array(
                    'deleted' => (int) $deleted,
                );
            }

            $event_uid_column = $this->get_event_uid_column($events_table);

            if ($event_uid_column === '') {
                throw new RuntimeException('No event UID column found in ' . $events_table . '.');
            }

            $deleted_event = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM `{$events_table}` WHERE `{$event_uid_column}` = %s",
                    $event_uid
                )
            );

            if ($deleted_event === false) {
                throw new RuntimeException('Delete failed for ' . $events_table . ': ' . $this->wpdb->last_error);
            }

            $summary['event'] = array(
                'table'   => $this->base_table_name($events_table),
                'column'  => $event_uid_column,
                'deleted' => (int) $deleted_event,
            );

            $summary['cleanup'] = $this->clean_database();

            $this->wpdb->query('COMMIT');

            $summary['success'] = true;
        } catch (Throwable $e) {
            $this->wpdb->query('ROLLBACK');

            $summary['success']  = false;
            $summary['errors'][] = $e->getMessage();
        }

        return $summary;
    }

    /**
     * Clean orphaned rows after event deletion.
     *
     * Important:
     * Some FK columns store numeric IDs, some store string UIDs such as event_uid.
     * Therefore cleanup must not use numeric-only checks such as <> 0.
     *
     * @return array Cleanup summary.
     */
    public function clean_database() {
        $rules = array(
            array('wp_evtmgr_pricing', 'fky_pricing_parent_id', 'wp_evtmgr_pricing', 'id'),

            array('wp_evtmgr_registrations', 'fky_person_id', 'wp_evtmgr_persons', 'id'),

            array('wp_evtmgr_registrations_billing', 'fky_person_id', 'wp_evtmgr_persons', 'id'),
            array('wp_evtmgr_registrations_billing', 'fky_billing_id', 'wp_evtmgr_pricing', 'id'),

            array('wp_evtmgr_registrations_workshops', 'fky_person_id', 'wp_evtmgr_persons', 'id'),
            array('wp_evtmgr_registrations_workshops', 'fky_workshop_id', 'wp_evtmgr_workshops', 'id'),

            array('wp_evtmgr_tbx_timezones_presenters', 'fky_timezone_id', 'wp_evtmgr_timezones', 'id'),
            array('wp_evtmgr_tbx_timezones_presenters', 'fky_person_id', 'wp_evtmgr_presenters', 'id'),

            array('wp_evtmgr_tbx_workshops_audience', 'fky_workshop_id', 'wp_evtmgr_workshops', 'id'),
            array('wp_evtmgr_tbx_workshops_audience', 'fky_audience_id', 'wp_evtmgr_audience', 'id'),

            array('wp_evtmgr_tbx_workshops_presenters', 'fky_workshop_id', 'wp_evtmgr_workshops', 'id'),
            array('wp_evtmgr_tbx_workshops_presenters', 'fky_person_id', 'wp_evtmgr_presenters', 'id'),

            /*
             * Defensive support for the originally listed table/field combination.
             * This will be skipped automatically if the column does not exist.
             */
            array('wp_evtmgr_tbx_workshops_audience', 'fky_person_id', 'wp_evtmgr_presenters', 'id'),

            array('wp_evtmgr_timezones', 'fky_parent_timezone_id', 'wp_evtmgr_timezones', 'id'),

            array('wp_evtmgr_workshops', 'fky_parent_timezone_id', 'wp_evtmgr_timezones', 'id'),
            array('wp_evtmgr_workshops', 'fky_room_id', 'wp_evtmgr_rooms', 'id'),
            array('wp_evtmgr_workshops', 'fky_slot_id', 'wp_evtmgr_slots', 'id'),

            /*
             * Event UID based cleanup.
             * These columns store string event UIDs, not numeric event IDs.
             */
            array('wp_evtmgr_audience', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_persons', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_presenters', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_pricing', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_registrations', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_registrations_billing', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_registrations_workshops', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_rooms', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_slots', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_timezones', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_wordings', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
            array('wp_evtmgr_workshops', 'fky_event_uid', 'wp_evtmgr_events', 'event_uid'),
        );

        $summary = array(
            'success' => true,
            'rules'   => array(),
            'errors'  => array(),
        );

        foreach ($rules as $rule) {
            list($base_table, $fk_column, $base_reference_table, $reference_column) = $rule;

            $table           = $this->table_name($base_table);
            $reference_table = $this->table_name($base_reference_table);

            $rule_key = $base_table . '.' . $fk_column . ' -> ' . $base_reference_table . '.' . $reference_column;

            if (!$this->table_exists($table)) {
                $summary['rules'][$rule_key] = array(
                    'skipped' => true,
                    'reason'  => 'Table does not exist.',
                );
                continue;
            }

            if (!$this->table_exists($reference_table)) {
                $summary['rules'][$rule_key] = array(
                    'skipped' => true,
                    'reason'  => 'Reference table does not exist.',
                );
                continue;
            }

            if (!$this->column_exists($table, $fk_column)) {
                $summary['rules'][$rule_key] = array(
                    'skipped' => true,
                    'reason'  => 'FK column does not exist.',
                );
                continue;
            }

            if (!$this->column_exists($reference_table, $reference_column)) {
                $summary['rules'][$rule_key] = array(
                    'skipped' => true,
                    'reason'  => 'Reference column does not exist.',
                );
                continue;
            }

            $sql = "
                DELETE t
                FROM `{$table}` AS t
                LEFT JOIN `{$reference_table}` AS r
                    ON t.`{$fk_column}` = r.`{$reference_column}`
                WHERE t.`{$fk_column}` IS NOT NULL
                  AND TRIM(CAST(t.`{$fk_column}` AS CHAR)) <> ''
                  AND TRIM(CAST(t.`{$fk_column}` AS CHAR)) <> '0'
                  AND r.`{$reference_column}` IS NULL
            ";

            $deleted = $this->wpdb->query($sql);

            if ($deleted === false) {
                $summary['success']  = false;
                $summary['errors'][] = 'Cleanup failed for ' . $rule_key . ': ' . $this->wpdb->last_error;
                $summary['rules'][$rule_key] = array(
                    'success' => false,
                    'error'   => $this->wpdb->last_error,
                );
                continue;
            }

            $summary['rules'][$rule_key] = array(
                'deleted' => (int) $deleted,
            );
        }

        return $summary;
    }

    protected function get_tables_with_column($column_name) {
        $column_name = sanitize_key((string) $column_name);

        if ($column_name === '') {
            return array();
        }

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND COLUMN_NAME = %s
                ORDER BY TABLE_NAME
                ",
                $column_name
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return array();
        }

        $tables = array();

        foreach ($rows as $row) {
            if (!empty($row['TABLE_NAME'])) {
                $tables[] = (string) $row['TABLE_NAME'];
            }
        }

        return array_values(array_unique($tables));
    }

    protected function get_event_uid_column($events_table) {
        if ($this->column_exists($events_table, 'event_uid')) {
            return 'event_uid';
        }

        if ($this->column_exists($events_table, 'pky_event_uid')) {
            return 'pky_event_uid';
        }

        return '';
    }


}
