<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class class_evtmgr_persons {

    protected $wpdb;
    protected $person_table;

    public function __construct() {
        global $wpdb;

        $this->wpdb         = $wpdb;
        $this->person_table = 'wp_evtmgr_persons';
    }

    public function get_persons_registered($event_uid) {
        $event_uid = sanitize_text_field($event_uid);

        $sql = "
            SELECT *
            FROM {$this->person_table}
            WHERE fky_event_uid = %s
            ORDER BY str_country, str_last_name, str_first_name
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    /**
     * Update str_diploma_pdf for all persons of this event where the field is empty.
     * The event file-name part is built internally from wp_evtmgr_events.str_event_name_*.
     *
     * @param string $event_uid Event / owner Uid.
     * @return array Update summary.
     */
    public function person_update_diploma_pdf($event_uid) {
        return $this->person_update_pdf_field(
            'str_diploma_pdf',
            $event_uid,
            'diploma'
        );
    }

    /**
     * Update str_invoice_pdf for all persons of this event where the field is empty.
     * The event file-name part is built internally from wp_evtmgr_events.str_event_name_*.
     *
     * @param string $event_uid Event / owner Uid.
     * @return array Update summary.
     */
    public function person_update_invoice_pdf($event_uid) {
        return $this->person_update_pdf_field(
            'str_invoice_pdf',
            $event_uid,
            'invoice'
        );
    }

    /**
     * Update str_program_pdf for all persons of this event where the field is empty.
     * The event file-name part is built internally from wp_evtmgr_events.str_event_name_*.
     *
     * @param string $event_uid Event / owner Uid.
     * @return array Update summary.
     */
    public function person_update_program_pdf($event_uid) {
        return $this->person_update_pdf_field(
            'str_program_pdf',
            $event_uid,
            'program'
        );
    }

    /**
     * Update str_ticket_pdf for all persons of this event where the field is empty.
     *
     * @param string $event_uid Event / owner Uid.
     * @return array Update summary.
     */
    public function person_update_ticket_pdf($event_uid) {
        return $this->person_update_pdf_field(
            'str_ticket_pdf',
            $event_uid,
            'ticket'
        );
    }

    protected function person_update_pdf_field($target_field, $event_uid, $type) {
        $event_uid = sanitize_text_field((string) $event_uid);

        $summary = array(
            'success'             => true,
            'field'               => $target_field,
            'event_uid'           => $event_uid,
            'event_name_file'  => '',
            'checked'             => 0,
            'updated'             => 0,
            'skipped'             => 0,
            'errors'              => array(),
            'files'               => array(),
        );

        if ($event_uid === '') {
            $summary['success']  = false;
            $summary['errors'][] = 'event_uid is empty.';
            return $summary;
        }

        if (!in_array($target_field, array('str_diploma_pdf', 'str_invoice_pdf', 'str_program_pdf', 'str_ticket_pdf'), true)) {
            $summary['success']  = false;
            $summary['errors'][] = 'Invalid target field.';
            return $summary;
        }

        $sql = "
            SELECT *
            FROM {$this->person_table}
            WHERE fky_event_uid = %s
              AND ({$target_field} IS NULL OR {$target_field} = '')
            ORDER BY str_country, str_last_name, str_first_name
        ";

        $persons = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );

        if (empty($persons)) {
            return $summary;
        }

        foreach ($persons as $person) {
            $summary['checked']++;

            $person_id        = $this->get_person_id($person);
            $person_id_column = $this->get_person_id_column($person);

            if ($person_id === '' || $person_id_column === '') {
                $summary['success']  = false;
                $summary['skipped']++;
                $summary['errors'][] = 'Could not determine person id for one record.';
                continue;
            }

            $language = $this->normalize_language($this->value_ci($person, 'str_language'));
            $event = $this->get_events_by_event_uid($event_uid, $language);

            if (empty($event)) {
                $summary['success']  = false;
                $summary['skipped']++;
                $summary['errors'][] = 'Could not load event for owner Uid ' . $event_uid . ' and language ' . $language . '.';
                continue;
            }

            $str_event_name_File = $this->build_event_name_file($event, $language);

            if ($str_event_name_File === '') {
                $summary['success']  = false;
                $summary['skipped']++;
                $summary['errors'][] = 'Could not build event file name part for owner Uid ' . $event_uid . ' and language ' . $language . '.';
                continue;
            }

            if (empty($summary['event_name_file'])) {
                $summary['event_name_file'] = $str_event_name_File;
            }

            $file_name = $this->build_pdf_file_name($person, $str_event_name_File, $type);

            if ($file_name === '') {
                $summary['success']  = false;
                $summary['skipped']++;
                $summary['errors'][] = 'Could not build file name for person id ' . $person_id . '.';
                continue;
            }

            $updated = $this->wpdb->update(
                $this->person_table,
                array(
                    $target_field => $file_name,
                ),
                array(
                    $person_id_column => $person_id,
                ),
                array('%s'),
                array('%d')
            );

            if ($updated === false) {
                $summary['success']  = false;
                $summary['skipped']++;
                $summary['errors'][] = 'Database update failed for person id ' . $person_id . ': ' . $this->wpdb->last_error;
                continue;
            }

            $summary['updated']++;
            $summary['files'][] = array(
                'person_id' => $person_id,
                'file_name' => $file_name,
            );
        }

        return $summary;
    }

    protected function get_events_by_event_uid($event_uid, $language = 'de') {
        $event_uid = sanitize_text_field((string) $event_uid);
        $language  = $this->normalize_language($language);

        if ($event_uid === '') {
            return array();
        }

        /*
         * Prefer the existing event class if it has already been included.
         * Different admin/public versions have used different class names, so we support all known names.
         */
        if (class_exists('Evtmgr_Events')) {
            $event_obj = new Evtmgr_Events();

            if (method_exists($event_obj, 'get_events_by_event_uid')) {
                return (array) $event_obj->get_events_by_event_uid($event_uid, $language);
            }
        }

        if (class_exists('Evtmgr_Admin_Event')) {
            $event_obj = new Evtmgr_Admin_Event();

            if (method_exists($event_obj, 'get_events_by_event_uid')) {
                return (array) $event_obj->get_events_by_event_uid($event_uid, $language);
            }
        }

        if (class_exists('Evtmgr_Events')) {
            $event_obj = new Evtmgr_Events();

            if (method_exists($event_obj, 'get_events_by_event_uid')) {
                return (array) $event_obj->get_events_by_event_uid($event_uid, $language);
            }
        }

        if (class_exists('class_evtmgr_event')) {
            $event_obj = new class_evtmgr_event();

            if (method_exists($event_obj, 'get_events_by_event_uid')) {
                return (array) $event_obj->get_events_by_event_uid($event_uid, $language);
            }
        }

        /* Fallback: direct table query. */
        $sql = "
            SELECT *
            FROM {$this->wpdb->prefix}evtmgr_events
            WHERE event_uid = %s
            LIMIT 1
        ";

        $event = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );

        return is_array($event) ? $event : array();
    }

    protected function build_event_name_file(array $event, $language = 'de') {
        $language = $this->normalize_language($language);

        $candidates = array(
            'str_event_name',
            'str_event_name_' . $language,
            'str_event_name_' . strtoupper($language),
            'str_event_name_' . ucfirst($language),
            'str_event_name_de',
            'str_event_name_DE',
        );

        $title = '';

        foreach ($candidates as $candidate) {
            $title = $this->value_ci($event, $candidate);

            if ($title !== '') {
                break;
            }
        }

        if ($title === '') {
            foreach ($event as $key => $value) {
                if (stripos((string) $key, 'str_event_name_') === 0 && is_scalar($value) && trim((string) $value) !== '') {
                    $title = (string) $value;
                    break;
                }
            }
        }

        return $this->safe_file_part($title);
    }

    protected function build_pdf_file_name(array $person, $str_event_name_File, $type) {
        $person_id  = $this->get_person_id($person);
        $first_name = $this->value_ci($person, 'str_first_name');
        $last_name  = $this->value_ci($person, 'str_last_name');
        $language   = $this->normalize_language($this->value_ci($person, 'str_language'));

        if ($type === 'invoice') {
            $last_part = $this->get_invoice_file_suffix($language);
        } elseif ($type === 'program') {
            $last_part = $this->get_program_file_suffix($language);
        } elseif ($type === 'ticket') {
            $last_part = $this->get_ticket_file_suffix($language);
        } else {
            $last_part = $this->get_diploma_file_suffix($language);
        }

        $parts = array_filter(array(
            $this->safe_file_part($first_name),
            $this->safe_file_part($last_name),
            $this->safe_file_part($person_id),
            $this->safe_file_part($str_event_name_File),
            $this->safe_file_part($last_part),
        ));

        if (empty($parts)) {
            return '';
        }

        return strtolower(implode('-', $parts) . '.pdf');
    }

    protected function get_ticket_file_suffix($language) {
        $map = array(
            'de' => 'ticket',
            'fr' => 'ticket',
            'it' => 'ticket',
            'en' => 'ticket',
        );

        return $map[$language] ?? 'ticket';
    }

    protected function get_diploma_file_suffix($language) {
        $map = array(
            'de' => 'teilnahmebestaetigung',
            'fr' => 'attestation_de_participation',
            'it' => 'conferma_di_partecipazione',
            'en' => 'confirmation_of_participatio',
        );

        return $map[$language] ?? $map['de'];
    }

    protected function get_invoice_file_suffix($language) {
        $map = array(
            'de' => 'rechnung',
            'fr' => 'facture',
            'it' => 'fattura',
            'en' => 'invoice',
        );

        return $map[$language] ?? $map['de'];
    }

    protected function get_program_file_suffix($language) {
        $map = array(
            'de' => 'programm',
            'fr' => 'programme',
            'it' => 'programma',
            'en' => 'program',
        );

        return $map[$language] ?? $map['de'];
    }

    protected function normalize_language($language) {
        $language = strtolower(sanitize_key((string) $language));

        if (!in_array($language, array('de', 'fr', 'it', 'en'), true)) {
            return 'de';
        }

        return $language;
    }

    protected function get_person_id(array $person) {
        $value = $this->value_ci($person, 'id');

        if ($value === '') {
            $value = $this->value_ci($person, 'id');
        }

        return (string) absint($value);
    }

    protected function get_person_id_column(array $person) {
        foreach ($person as $key => $value) {
            if (strtolower((string) $key) === strtolower('id')) {
                return (string) $key;
            }
        }

        foreach ($person as $key => $value) {
            if (strtolower((string) $key) === strtolower('id')) {
                return (string) $key;
            }
        }

        return '';
    }

    protected function value_ci(array $row, $field_name, $default = '') {
        return Event_Registration_Helpers::value_ci($row, $field_name, $default);
    }

    protected function safe_file_part($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        } else {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);

        return trim($value, '-_');
    }

    /**
     * Save the person data collected during registration into wp_evtmgr_persons.
     * This replaces the old save_registration_after_person_save() flow.
     *
     * @return array Result details including person_id.
     */
    public function save_person_after_registration($data, $event_uid = '', $customer_cookie = '', $lang = 'de') {
        if (empty($data) || !is_array($data)) {
            return $this->result(false, '', 0, 'No registration data available.');
        }

        $event_uid       = sanitize_text_field((string) $event_uid);
        $customer_cookie = sanitize_text_field((string) $customer_cookie);
        $lang            = $this->normalize_language($lang);
        $columns         = $this->get_table_columns();

        if (empty($columns)) {
            return $this->result(false, '', 0, 'Could not read table columns for wp_evtmgr_persons.', $this->wpdb->last_error);
        }

        $primary_key = $this->get_column_name($columns, array('id'));

        if ($primary_key === '') {
            return $this->result(false, '', 0, 'Primary key id was not found in wp_evtmgr_persons.');
        }

        $record = array();

        $this->add_record_value($record, $columns, array('fky_event_uid'), $event_uid);
        $this->add_record_value($record, $columns, array('str_language'), $lang);
        $this->add_record_value($record, $columns, array('str_registration_cookie', 'str_customer_cookie'), $customer_cookie);

        $event_id = $this->get_event_id_by_uid($event_uid);
        if ($event_id > 0) {
            $this->add_record_value($record, $columns, array('fky_event_id'), $event_id);
        }

        $this->add_record_value_raw($record, $columns, array('mem_cgi_variables'), $this->serialize_cgi_variables());
        $this->add_record_value_raw($record, $columns, array('mem_person_data'), $this->serialize_person_data($data));
        $this->add_record_value_raw($record, $columns, array('mem_program_data'), $this->get_registration_value($data, 'person_program_data'));
        $this->add_record_value_raw($record, $columns, array('mem_price_data'), $this->get_registration_value($data, 'person_billling_data'));

        $this->add_record_value($record, $columns, array('int_billing_address', 'int_type_of_address'), (int) $this->get_data_value($data, 'int_billing_address', 1));
        $this->add_record_value($record, $columns, array('str_salutation'), $this->get_data_value($data, 'str_salutation'));
        $this->add_record_value($record, $columns, array('str_academic_title'), $this->get_data_value($data, 'str_academic_title'));
        $this->add_record_value($record, $columns, array('str_first_name'), $this->get_data_value($data, 'str_first_name'));
        $this->add_record_value($record, $columns, array('str_last_name'), $this->get_data_value($data, 'str_last_name'));
        $this->add_record_value($record, $columns, array('str_address'), $this->get_data_value($data, 'str_address'));
        $this->add_record_value($record, $columns, array('str_zip'), $this->get_data_value($data, 'str_zip'));
        $this->add_record_value($record, $columns, array('str_city'), $this->get_data_value($data, 'str_city'));
        $this->add_record_value($record, $columns, array('str_country'), $this->get_data_value($data, 'str_country'));
        $this->add_record_value($record, $columns, array('str_job_title'), $this->get_data_value($data, 'str_job_title'));
        $this->add_record_value($record, $columns, array('str_email'), $this->get_data_value($data, 'str_email'));
        $this->add_record_value($record, $columns, array('str_phone'), $this->get_data_value($data, 'str_phone'));

        $this->add_record_value($record, $columns, array('str_institution'), $this->get_data_value($data, 'str_institution'));
        $this->add_record_value($record, $columns, array('str_institution_division'), $this->get_data_value($data, 'str_institution_Division'));
        $this->add_record_value($record, $columns, array('str_institution_address'), $this->get_data_value($data, 'str_institution_Address'));
        $this->add_record_value($record, $columns, array('str_institution_zip'), $this->get_data_value($data, 'str_institution_Zip'));
        $this->add_record_value($record, $columns, array('str_institution_city'), $this->get_data_value($data, 'str_institution_City'));

        $now = current_time('mysql');
        $this->add_record_value($record, $columns, array('dtm_date_updated', 'updated_at'), $now);

        $record = array_filter($record, static function($value) {
            return $value !== null;
        });

        if (empty($record)) {
            return $this->result(false, '', 0, 'No matching columns found for wp_evtmgr_persons.');
        }

        $existing_person_id = $this->find_existing_person_id(
            $columns,
            $primary_key,
            $event_uid,
            $customer_cookie,
            $this->get_data_value($data, 'str_email')
        );

        if ($existing_person_id > 0 && $this->person_exists($primary_key, $existing_person_id)) {
            $updated = $this->wpdb->update(
                $this->person_table,
                $record,
                array($primary_key => $existing_person_id),
                null,
                array('%d')
            );

            return array(
                'success'   => $updated !== false,
                'action'    => 'updated',
                'person_id' => $existing_person_id,
                'message'   => $updated !== false ? 'Person updated.' : 'Person update failed.',
                'error'     => $this->wpdb->last_error,
                'data'      => $record,
                'query'     => $this->wpdb->last_query,
            );
        }

        $this->add_record_value($record, $columns, array('dtm_date_created', 'created_at'), $now);

        $inserted = $this->wpdb->insert($this->person_table, $record);

        if ($inserted === false) {
            return array(
                'success'   => false,
                'action'    => 'insert_failed',
                'person_id' => 0,
                'message'   => 'Person insert failed.',
                'error'     => $this->wpdb->last_error,
                'data'      => $record,
                'query'     => $this->wpdb->last_query,
            );
        }

        $person_id = (int) $this->wpdb->insert_id;

        if ($person_id <= 0) {
            $person_id = $this->find_existing_person_id(
                $columns,
                $primary_key,
                $event_uid,
                $customer_cookie,
                $this->get_data_value($data, 'str_email')
            );
        }

        if ($person_id <= 0 || !$this->person_exists($primary_key, $person_id)) {
            return array(
                'success'   => false,
                'action'    => 'inserted_but_not_found',
                'person_id' => $person_id,
                'message'   => 'Person insert did not produce a verifiable id.',
                'error'     => $this->wpdb->last_error,
                'data'      => $record,
                'query'     => $this->wpdb->last_query,
            );
        }

        return array(
            'success'   => true,
            'action'    => 'inserted',
            'person_id' => $person_id,
            'message'   => 'Person inserted.',
            'error'     => '',
            'data'      => $record,
            'query'     => $this->wpdb->last_query,
        );
    }

    public function update_email_sent($person_id, $email_html) {
        $person_id = absint($person_id);

        if ($person_id <= 0) {
            return false;
        }

        $columns = $this->get_table_columns();
        $primary_key = $this->get_column_name($columns, array('id'));

        if ($primary_key === '') {
            return false;
        }

        $data = array();
        $this->add_record_value_raw($data, $columns, array('mem_email_sent'), (string) $email_html);
        $this->add_record_value($data, $columns, array('dtm_email_sent'), current_time('mysql'));

        if (empty($data)) {
            return true;
        }

        $updated = $this->wpdb->update(
            $this->person_table,
            $data,
            array($primary_key => $person_id),
            null,
            array('%d')
        );

        return $updated !== false;
    }

    protected function result($success, $action, $person_id, $message, $error = '') {
        return array(
            'success'   => (bool) $success,
            'action'    => (string) $action,
            'person_id' => absint($person_id),
            'message'   => (string) $message,
            'error'     => (string) $error,
            'data'      => array(),
            'query'     => $this->wpdb->last_query,
        );
    }

    protected function get_table_columns() {
        return Event_Registration_Helpers::get_table_columns(
            $this->wpdb,
            $this->person_table,
            true
        );
    }

    protected function get_column_name($columns, $possible_column_names) {
        foreach ($possible_column_names as $column_name) {
            $lookup_keys = array(
                strtolower((string) $column_name),
                $this->normalize_column_key($column_name),
            );

            foreach ($lookup_keys as $lookup_key) {
                if (isset($columns[$lookup_key])) {
                    return $columns[$lookup_key];
                }
            }
        }

        return '';
    }

    protected function add_record_value(&$record, $columns, $possible_column_names, $value) {
        $column = $this->get_column_name($columns, $possible_column_names);

        if ($column === '') {
            return;
        }

        $record[$column] = is_string($value)
            ? sanitize_text_field($value)
            : $value;
    }

    protected function add_record_value_raw(&$record, $columns, $possible_column_names, $value) {
        $column = $this->get_column_name($columns, $possible_column_names);

        if ($column === '') {
            return;
        }

        $record[$column] = is_scalar($value) ? (string) $value : '';
    }

    protected function get_registration_value($data, $field_name, $default = '') {
        return Event_Registration_Helpers::get_registration_value(
            $data,
            $field_name,
            $default,
            true
        );
    }

    protected function serialize_person_data($person_data) {
        if (!is_array($person_data)) {
            $person_data = array();
        }

        return wp_json_encode($person_data, JSON_UNESCAPED_UNICODE);
    }

    protected function serialize_cgi_variables() {
        $cgi_data = array(
            'REMOTE_ADDR'          => $_SERVER['REMOTE_ADDR'] ?? '',
            'HTTP_USER_AGENT'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'HTTP_ACCEPT'          => $_SERVER['HTTP_ACCEPT'] ?? '',
            'HTTP_ACCEPT_LANGUAGE' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'HTTP_ACCEPT_ENCODING' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            'HTTP_HOST'            => $_SERVER['HTTP_HOST'] ?? '',
            'REQUEST_METHOD'       => $_SERVER['REQUEST_METHOD'] ?? '',
            'REQUEST_URI'          => $_SERVER['REQUEST_URI'] ?? '',
            'QUERY_STRING'         => $_SERVER['QUERY_STRING'] ?? '',
            'HTTP_REFERER'         => $_SERVER['HTTP_REFERER'] ?? '',
            'SERVER_NAME'          => $_SERVER['SERVER_NAME'] ?? '',
            'SERVER_PORT'          => $_SERVER['SERVER_PORT'] ?? '',
            'HTTPS'                => $_SERVER['HTTPS'] ?? '',
            'REQUEST_TIME'         => $_SERVER['REQUEST_TIME'] ?? '',
            'REQUEST_TIME_FLOAT'   => $_SERVER['REQUEST_TIME_FLOAT'] ?? '',
        );

        foreach ($cgi_data as $key => $value) {
            $cgi_data[$key] = is_scalar($value)
                ? sanitize_text_field((string) $value)
                : '';
        }

        return wp_json_encode($cgi_data, JSON_UNESCAPED_UNICODE);
    }

    protected function get_event_id_by_uid($event_uid) {
        $event_uid = sanitize_text_field((string) $event_uid);

        if ($event_uid === '') {
            return 0;
        }

        if (class_exists('Evtmgr_Events')) {
            $event_obj = new Evtmgr_Events();
            $event = $event_obj->get_events_by_event_uid($event_uid, 'de');

            if (!empty($event['id'])) {
                return absint($event['id']);
            }
        }

        $event = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}evtmgr_events WHERE event_uid = %s LIMIT 1",
                $event_uid
            ),
            ARRAY_A
        );

        return !empty($event['id']) ? absint($event['id']) : 0;
    }

    protected function get_data_value($data, $field_name, $default = '') {
        $wanted = array(
            strtolower((string) $field_name),
            $this->normalize_column_key($field_name),
        );

        foreach ($data as $key => $value) {
            $candidate = array(
                strtolower((string) $key),
                $this->normalize_column_key($key),
            );

            if (array_intersect($wanted, $candidate)) {
                return is_array($value) ? $default : (string) $value;
            }
        }

        return $default;
    }

    protected function find_existing_person_id($columns, $primary_key, $event_uid, $customer_cookie, $email) {
        $event_uid       = sanitize_text_field((string) $event_uid);
        $customer_cookie = sanitize_text_field((string) $customer_cookie);
        $email           = sanitize_email((string) $email);

        $event_column = $this->get_column_name($columns, array('fky_event_uid'));
        $event_sql    = '';
        $event_args   = array();

        if ($event_uid !== '' && $event_column !== '') {
            $event_sql    = " AND `{$event_column}` = %s";
            $event_args[] = $event_uid;
        }

        $cookie_column = $this->get_column_name($columns, array('str_registration_cookie', 'str_customer_cookie'));

        if ($customer_cookie !== '' && $cookie_column !== '') {
            $sql  = "SELECT `{$primary_key}` FROM {$this->person_table} WHERE `{$cookie_column}` = %s{$event_sql} LIMIT 1";
            $args = array_merge(array($customer_cookie), $event_args);
            $id   = (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $args));

            if ($id > 0 && $this->person_exists($primary_key, $id)) {
                return $id;
            }

            // Cookie provided but not in DB → fresh session (e.g. "weitere Anmeldung").
            // Do NOT fall back to email, so a genuinely new registration is created.
            return 0;
        }

        // No cookie at all → try email (e.g. user lost cookie mid-registration and restarted).
        $email_column = $this->get_column_name($columns, array('str_email'));

        if ($email !== '' && $email_column !== '') {
            $sql  = "SELECT `{$primary_key}` FROM {$this->person_table} WHERE `{$email_column}` = %s{$event_sql} LIMIT 1";
            $args = array_merge(array($email), $event_args);
            $id   = (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $args));

            if ($id > 0 && $this->person_exists($primary_key, $id)) {
                return $id;
            }
        }

        return 0;
    }

    protected function person_exists($primary_key, $person_id) {
        $person_id = absint($person_id);

        if ($primary_key === '' || $person_id <= 0) {
            return false;
        }

        $exists = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->person_table} WHERE `{$primary_key}` = %d",
                $person_id
            )
        );

        return $exists > 0;
    }

    protected function normalize_column_key($key) {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $key));
    }

}

