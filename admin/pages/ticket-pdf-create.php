<?php

/**
 * Caller file for PDF creation: Tickets for participants.
 */

$type_of_pdf       = 'Tickets';
$type_of_pdf_sing  = 'Ticket';
$pdf_layout        = 'dachverband-ticket.php';
$file_name_field   = 'str_ticket_pdf';
$subfolder_for_pdf = 'tickets';

$before_pdf_creation_callback = function ($event_uid) {
    global $wpdb;

    $wpdb->query(
        "ALTER TABLE {$wpdb->prefix}evtmgr_persons
         ADD COLUMN IF NOT EXISTS str_ticket_pdf VARCHAR(255) NOT NULL DEFAULT ''"
    );

    $persons_obj = new class_evtmgr_persons();
    if (method_exists($persons_obj, 'person_update_ticket_pdf')) {
        $persons_obj->person_update_ticket_pdf($event_uid);
    }
};

require __DIR__ . '/pdf-creation.php';
