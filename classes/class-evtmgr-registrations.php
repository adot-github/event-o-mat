<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Registrations {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_registrations';
    }

    public function save_registration_after_person_save($person_id, $event_uid, $customer_cookie, $person_data) {
        $person_id       = absint($person_id);
        $event_uid       = sanitize_text_field($event_uid);
        $customer_cookie = sanitize_text_field($customer_cookie);

        if ($person_id <= 0 || $event_uid === '' || $event_uid === '' || $customer_cookie === '') {
            return false;
        }

        $event_obj = new Evtmgr_Events();
        $event     = $event_obj->get_events_by_event_uid($event_uid, 'de');

        if (empty($event) || empty($event['id'])) {
            return false;
        }

        $event_id = absint($event['id']);

        $registration_data = array(
            'fky_person_id'             => $person_id,
            'fky_event_id'           => $event_id,
            'mem_cgi_variables'         => $this->serialize_cgi_variables(),
            'mem_person_data'           => $this->serialize_person_data($person_data),
            'mem_program_data'          => $this->get_registration_value($person_data, 'person_program_data'),
            'mem_price_data'            => $this->get_registration_value($person_data, 'person_billling_data'),
            'fky_event_uid'              => $event_uid,
            'str_registration_cookie' => $customer_cookie,
        );

        $registration_data = $this->filter_existing_columns($registration_data);

        $existing_registration_id = $this->get_registration_id_by_cookie($customer_cookie, $event_uid);

        if ($existing_registration_id > 0) {
            $updated = $this->wpdb->update(
                $this->table_name,
                $registration_data,
                array(
                    'id' => $existing_registration_id,
                )
            );

            return $updated !== false ? $existing_registration_id : false;
        }

        $inserted = $this->wpdb->insert(
            $this->table_name,
            $registration_data
        );

        if (!$inserted) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }


    public function update_email_sent($registration_id, $email_html) {
        $registration_id = absint($registration_id);

        if ($registration_id <= 0) {
            return false;
        }

        $data = array();

        $columns = $this->get_table_columns();

        if (isset($columns['mem_email_sent'])) {
            $data['mem_email_sent'] = (string) $email_html;
        }

        if (isset($columns['dtm_email_sent'])) {
            $data['dtm_email_sent'] = current_time('mysql');
        }

        if (empty($data)) {
            return true;
        }

        $updated = $this->wpdb->update(
            $this->table_name,
            $data,
            array('id' => $registration_id)
        );

        return $updated !== false;
    }

    protected function get_table_columns() {
        return Event_Registration_Helpers::get_table_columns(
            $this->wpdb,
            $this->table_name,
            false
        );
    }

    public function get_registration_id_by_cookie($customer_cookie, $event_uid = '') {
        $customer_cookie = sanitize_text_field($customer_cookie);
        $event_uid       = sanitize_text_field($event_uid);

        if ($customer_cookie === '') {
            return 0;
        }

        if ($event_uid !== '') {
            $sql = "
                SELECT id
                FROM {$this->table_name}
                WHERE str_registration_cookie = %s
                  AND fky_event_uid = %s
                LIMIT 1
            ";

            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare($sql, $customer_cookie, $event_uid)
            );
        }

        $sql = "
            SELECT id
            FROM {$this->table_name}
            WHERE str_registration_cookie = %s
            LIMIT 1
        ";

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $customer_cookie)
        );
    }


    public function get_registration_value($data, $field_name, $default = '') {
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

    protected function filter_existing_columns($data) {
        $columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}", 0);

        if (empty($columns)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($columns));
    }
}