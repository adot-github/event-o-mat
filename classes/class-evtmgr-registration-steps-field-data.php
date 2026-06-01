<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Registration_Steps_Field_Data {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_registration_steps_field_data';
    }

    public function get_table_name() {
        return Event_Registration_Helpers::get_table_name($this->table_name);
    }

    public function insert_step_fields($fields, $customer_cookie) {
        $customer_cookie = sanitize_text_field((string) $customer_cookie);

        if ($customer_cookie === '' || empty($fields) || !is_array($fields)) {
            return false;
        }

        $customer_ip      = $this->get_client_ip();
        $customer_browser = $this->get_client_browser();
        $created_at       = current_time('mysql');
        $success          = true;

        foreach ($fields as $field_name => $field_value) {
            $field_name = $this->sanitize_field_name($field_name);

            if ($field_name === '') {
                continue;
            }

            if ($this->should_skip_field($field_name)) {
                continue;
            }

            $field_value = $this->prepare_field_value($field_name, $field_value);

            /*
             * Delete previous value for this cookie/field.
             * LOWER() makes this robust against older lowercase entries such as:
             * strfirstname vs str_first_name
             */
            $deleted = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->table_name}
                     WHERE str_customer_cookie = %s
                       AND LOWER(str_form_field_name) = LOWER(%s)",
                    $customer_cookie,
                    $field_name
                )
            );

            if ($deleted === false) {
                $success = false;
            }

            $inserted = $this->wpdb->insert(
                $this->table_name,
                array(
                    'str_form_field_name'  => $field_name,
                    'str_form_field_value' => $field_value,
                    'str_customer_cookie'  => $customer_cookie,
                    'str_customer_ip'      => $customer_ip,
                    'str_customer_browser' => $customer_browser,
                    'created_at'           => $created_at,
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($inserted === false) {
                $success = false;
            }
        }

        return $success;
    }

    public function get_all_data_for_current_cookie($customer_cookie) {
        $customer_cookie = sanitize_text_field((string) $customer_cookie);

        if ($customer_cookie === '') {
            return array();
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    str_form_field_name,
                    str_form_field_value,
                    str_customer_cookie,
                    str_customer_ip,
                    str_customer_browser,
                    created_at
                 FROM {$this->table_name}
                 WHERE str_customer_cookie = %s
                 ORDER BY id ASC",
                $customer_cookie
            ),
            ARRAY_A
        );
    }

    public function get_all_values_for_current_cookie($customer_cookie) {
        $values = array();
        $rows   = $this->get_all_data_for_current_cookie($customer_cookie);

        foreach ($rows as $row) {
            if (empty($row['str_form_field_name'])) {
                continue;
            }

            $field_name  = $row['str_form_field_name'];
            $field_value = $row['str_form_field_value'] ?? '';

            $values[$field_name] = $field_value;
        }

        return $values;
    }

    protected function prepare_field_value($field_name, $field_value) {
        /*
         * IMPORTANT:
         * person_program_data contains HTML from Step 1.
         * Do not sanitize_text_field() this value, otherwise all HTML tags are removed.
         *
         * We read it directly from $_POST to avoid any caller-side sanitizing.
         */
        if (in_array($field_name, array('person_program_data', 'person_billling_data'), true)) {
            $html = isset($_POST[$field_name])
                ? wp_unslash($_POST[$field_name])
                : wp_unslash($field_value);

            return $this->event_registration_minify_html($html);
        }

        if (is_array($field_value)) {
            $clean_values = array();

            foreach ($field_value as $key => $value) {
                $clean_key = sanitize_text_field((string) $key);

                if (is_array($value)) {
                    $clean_values[$clean_key] = array_map('sanitize_text_field', wp_unslash($value));
                } else {
                    $clean_values[$clean_key] = sanitize_text_field(wp_unslash($value));
                }
            }

            return wp_json_encode($clean_values, JSON_UNESCAPED_UNICODE);
        }

        return sanitize_text_field(wp_unslash((string) $field_value));
    }

    protected function event_registration_minify_html($html) {
        $html = (string) $html;

        /*
         * Keep HTML tags, but remove unnecessary whitespace.
         */
        $html = str_replace(array("\r", "\n", "\t"), '', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);

        return trim($html);
    }

    protected function sanitize_field_name($field_name) {
        /*
         * Do NOT use sanitize_key() here because it lowercases names.
         * We want to preserve names such as str_first_name and str_institution_Zip.
         */
        $field_name = wp_unslash((string) $field_name);
        $field_name = trim($field_name);

        return preg_replace('/[^A-Za-z0-9_\-]/', '', $field_name);
    }

    protected function should_skip_field($field_name) {
        $skip_fields = array(
            'registration_action',
            'registration_step',
            'current_step',
            'str_customer_cookie',
            'submit',
            '_wpnonce',
            '_wp_http_referer',
        );

        foreach ($skip_fields as $skip_field) {
            if (strtolower($field_name) === strtolower($skip_field)) {
                return true;
            }
        }

        return false;
    }

    protected function get_client_ip() {
        $keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );

        foreach ($keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash($_SERVER[$key]));

            if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($value, ',') !== false) {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }

            return $value;
        }

        return '';
    }

    protected function get_client_browser() {
        return !empty($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';
    }
}