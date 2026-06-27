
<?php

/**
 * Caller file for PDF creation:
 * Teilnahmebestätigungen for participants.
 */

$type_of_pdf      = 'Teilnahmebestätigungen';
$type_of_pdf_sing = 'Teilnahmebestätigung';
$pdf_layout       = 'dachverband-teilnahmenbestaetigung.php';
$file_name_field  = 'str_diploma_pdf';
$subfolder_for_pdf = 'diplomas';

/*
 * Optional callback before persons are loaded.
 * For participation confirmations this populates wp_evtmgr_persons.str_diploma_pdf.
 */
$before_pdf_creation_callback = function($event_uid) {
    $persons_obj = new class_evtmgr_persons();

    if (method_exists($persons_obj, 'person_update_diploma_pdf')) {
        $persons_obj->person_update_diploma_pdf($event_uid);
    }
};

require __DIR__ . '/pdf-creation.php';
