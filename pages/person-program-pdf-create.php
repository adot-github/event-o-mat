
<?php

/**
 * Caller file for PDF creation:
 * Programme for participants.
 */

$type_of_pdf      = 'Programme';
$type_of_pdf_sing = 'Programm';
$pdf_layout       = 'dachverband-program.php';
$file_name_field  = 'str_program_pdf';
$subfolder_for_pdf = 'programs';

/*
 * Optional callback before persons are loaded.
 * For participation confirmations this populates wp_evtmgr_persons.str_diploma_pdf.
 */
$before_pdf_creation_callback = function($event_uid) {
    $persons_obj = new class_evtmgr_persons();

    if (method_exists($persons_obj, 'person_update_program_pdf')) {
        $persons_obj->person_update_program_pdf($event_uid);
    }
};

require __DIR__ . '/pdf-creation.php';
