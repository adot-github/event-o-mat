<?php

if (!defined('ABSPATH')) {
    exit;
}

class Event_Registration_Helpers {

    public static function delete_by_cookie($wpdb, $table_name, $customer_cookie, $event_uid = '') {
        $customer_cookie = sanitize_text_field((string) $customer_cookie);
        $event_uid       = sanitize_text_field((string) $event_uid);
        $table_name      = sanitize_text_field((string) $table_name);

        if ($customer_cookie === '' || $table_name === '') {
            return false;
        }

        $where = array(
            'str_registration_cookie' => $customer_cookie,
        );

        $formats = array('%s');

        if ($event_uid !== '') {
            $where['fky_event_uid'] = $event_uid;
            $formats[] = '%s';
        }

        return $wpdb->delete(
            $table_name,
            $where,
            $formats
        );
    }

    public static function get_registration_value($data, $field_name, $default = '', $encode_non_scalar = true) {
        if (!is_array($data)) {
            return $default;
        }

        $field_name_lower = strtolower((string) $field_name);

        foreach ($data as $key => $value) {
            if (strtolower((string) $key) === $field_name_lower) {
                if (is_scalar($value)) {
                    return (string) $value;
                }

                return $encode_non_scalar
                    ? wp_json_encode($value, JSON_UNESCAPED_UNICODE)
                    : $default;
            }
        }

        return $default;
    }

    public static function get_table_columns($wpdb, $table_name, $include_normalized_keys = false) {
        $table_name = sanitize_text_field((string) $table_name);

        if ($table_name === '') {
            return array();
        }

        if (!$include_normalized_keys) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}", 0);

            if (empty($columns)) {
                return array();
            }

            return array_fill_keys($columns, true);
        }

        $columns = array();
        $rows    = $wpdb->get_results("DESCRIBE {$table_name}", ARRAY_A);

        if (empty($rows)) {
            return $columns;
        }

        foreach ($rows as $row) {
            if (empty($row['Field'])) {
                continue;
            }

            $field = (string) $row['Field'];

            $columns[strtolower($field)] = $field;
            $columns[self::normalize_column_key($field)] = $field;
        }

        return $columns;
    }

    public static function get_table_name($table_name) {
        return (string) $table_name;
    }

    public static function sanitize_ids($ids, $allow_zero = false) {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (!is_array($ids)) {
            return array();
        }

        $ids = array_map('absint', $ids);

        $ids = array_filter($ids, static function($id) use ($allow_zero) {
            return $allow_zero ? $id >= 0 : $id > 0;
        });

        return array_values($ids);
    }

    public static function sanitize_language($lang) {
        $lang = sanitize_key((string) $lang);

        $allowed_languages = array('de', 'en', 'fr', 'it');

        return in_array($lang, $allowed_languages, true) ? $lang : 'de';
    }

    public static function value_ci(array $row, $field_name, $default = '') {
        $field_name_lower = strtolower((string) $field_name);

        foreach ($row as $key => $value) {
            if (strtolower((string) $key) === $field_name_lower) {
                return is_scalar($value) ? (string) $value : $default;
            }
        }

        return $default;
    }

    public static function normalize_column_key($field) {
        $field = strtolower((string) $field);
        $field = preg_replace('/[^a-z0-9]+/', '', $field);

        return (string) $field;
    }
}
