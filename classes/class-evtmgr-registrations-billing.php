<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';
require_once __DIR__ . '/class-evtmgr-registrations.php';

class Evtmgr_Registrations_Billing {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_registrations_billing';
    }

    public function save_registration_billing($person_id, $event_uid, $customer_cookie, $registration_values, $lang = 'de') {
        $person_id       = absint($person_id);
        $event_uid       = sanitize_text_field($event_uid);
        $customer_cookie = sanitize_text_field($customer_cookie);
        $lang            = $this->sanitize_language($lang);

        if ($person_id <= 0 || $event_uid === '' || $customer_cookie === '') {
            return false;
        }

        if (!is_array($registration_values)) {
            $registration_values = array();
        }

        $registrations_obj = new Evtmgr_Registrations();

        $pricing_group = $registrations_obj->get_registration_value($registration_values, 'pricing_group');
        $selected_workshops = $registrations_obj->get_registration_value($registration_values, 'selected_workshops', '');
        $selected_workshop_ids = Event_Registration_Helpers::sanitize_ids((string) $selected_workshops);

        if (trim((string) $pricing_group) === '') {
            return false;
        }

        $event_obj = new Evtmgr_Events();
        $event     = $event_obj->get_events_by_event_uid($event_uid, $lang);

        if (empty($event) || empty($event['id'])) {
            return false;
        }

        $event_id = absint($event['id']);

        $pricing_obj = new Evtmgr_Pricing();
        $pricing_option = $pricing_obj->find_registration_pricing_option(
            $event_uid,
            $lang,
            $selected_workshop_ids,
            $pricing_group
        );

        if (empty($pricing_option) || empty($pricing_option['lines']) || !is_array($pricing_option['lines'])) {
            return false;
        }

        /*
         * Update strategy:
         * Remove existing billing rows for this cookie/person/event,
         * then insert the current billing rows again.
         */
        $this->wpdb->delete(
            $this->table_name,
            array(
                'fky_person_id'           => $person_id,
                'fky_event_id'            => $event_id,
                'fky_event_uid'           => $event_uid,
                'str_registration_cookie' => $customer_cookie,
            ),
            array('%d', '%d', '%s', '%s')
        );

        $inserted_ids = array();

        foreach ($pricing_option['lines'] as $line) {
            if (!empty($line['is_total'])) {
                continue;
            }

            $billing_id = !empty($line['billing_id']) ? absint($line['billing_id']) : 0;

            if ($billing_id <= 0) {
                continue;
            }

            $billing_text = (string) ($line['label'] ?? '');
            $billing_text_detail = (string) ($line['description'] ?? '');
            $price = $this->normalize_price($line['amount'] ?? 0);

            $inserted = $this->wpdb->insert(
                $this->table_name,
                array(
                    'str_billing_text'        => $billing_text,
                    'str_billing_text_detail' => $billing_text_detail,
                    'int_price'               => $price,
                    'fky_person_id'           => $person_id,
                    'fky_event_id'            => $event_id,
                    'fky_billing_id'          => $billing_id,
                    'fky_event_uid'           => $event_uid,
                    'dtm_date_created'        => current_time('mysql'),
                    'str_registration_cookie' => $customer_cookie,
                ),
                array('%s', '%s', '%f', '%d', '%d', '%d', '%s', '%s', '%s')
            );

            if ($inserted === false) {
                return false;
            }

            $inserted_ids[] = (int) $this->wpdb->insert_id;
        }

        if (empty($inserted_ids)) {
            return false;
        }

        return $inserted_ids;
    }

    public function delete_by_cookie($customer_cookie, $event_uid = '') {
        return Event_Registration_Helpers::delete_by_cookie(
            $this->wpdb,
            $this->table_name,
            $customer_cookie,
            $event_uid
        );
    }


    protected function normalize_price($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        $value = str_replace(array('CHF', 'chf', ' '), '', $value);

        /*
         * Supports:
         * 1200.50
         * 1200,50
         * 1'200.50
         * 1’200.50
         */
        $value = str_replace(array("'", '’'), '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}