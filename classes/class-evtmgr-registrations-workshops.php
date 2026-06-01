<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';
require_once __DIR__ . '/class-evtmgr-registrations.php';

class Evtmgr_Registrations_Workshops {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_registrations_workshops';
    }

    public function save_registration_workshops($person_id, $event_uid, $customer_cookie, $registration_values) {
        $person_id       = absint($person_id);
        $event_uid       = sanitize_text_field($event_uid);
        $customer_cookie = sanitize_text_field($customer_cookie);

        if ($person_id <= 0 || $event_uid === '' || $event_uid === '' || $customer_cookie === '') {
            return false;
        }

        if (!is_array($registration_values)) {
            $registration_values = array();
        }

        $registrations_obj = new Evtmgr_Registrations();

        $selected_workshops = $registrations_obj->get_registration_value(
            $registration_values,
            'selected_workshops'
        );

        if ($selected_workshops === '') {
            return false;
        }

        $workshop_ids = array_filter(array_map('absint', explode(',', $selected_workshops)));
        $workshop_ids = array_values(array_unique($workshop_ids));

        if (empty($workshop_ids)) {
            return false;
        }

        $event_obj = new Evtmgr_Events();
        $event     = $event_obj->get_events_by_event_uid($event_uid, 'de');

        if (empty($event) || empty($event['id'])) {
            return false;
        }

        $event_id = absint($event['id']);

        /*
         * Update strategy:
         * Delete existing workshop rows for this cookie/person/event,
         * then insert the current selection again.
         */
        $this->wpdb->delete(
            $this->table_name,
            array(
                'fky_person_id'             => $person_id,
                'fky_event_id'           => $event_id,
                'fky_event_uid'              => $event_uid,
                'str_registration_cookie' => $customer_cookie,
            ),
            array('%d', '%d', '%s', '%s')
        );

        $success = true;

        foreach ($workshop_ids as $workshop_id) {
            $inserted = $this->wpdb->insert(
                $this->table_name,
                array(
                    'fky_person_id'             => $person_id,
                    'fky_workshop_id'           => $workshop_id,
                    'fky_event_id'           => $event_id,
                    'fky_event_uid'              => $event_uid,
                    'dtm_date_created'          => current_time('mysql'),
                    'str_registration_cookie' => $customer_cookie,
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s')
            );

            if ($inserted === false) {
                $success = false;
            }
        }

        return $success;
    }

    public function delete_by_cookie($customer_cookie, $event_uid = '') {
        return Event_Registration_Helpers::delete_by_cookie(
            $this->wpdb,
            $this->table_name,
            $customer_cookie,
            $event_uid
        );
    }

}