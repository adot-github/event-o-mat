
<?php

/**
 * Caller file for PDF creation:
 * Rechnungen for participants.
 */

$type_of_pdf      = 'Rechnungen';
$type_of_pdf_sing = 'Rechnung';
$pdf_layout       = 'dachverband-rechnung.php';
$file_name_field  = 'str_invoice_pdf';
$subfolder_for_pdf = 'invoices';

/*
 * Optional callback before persons are loaded.
 * For invoices this populates wp_evtmgr_persons.str_invoice_pdf.
 */
$before_pdf_creation_callback = function($event_uid) {
    $persons_obj = new class_evtmgr_persons();

    if (method_exists($persons_obj, 'person_update_invoice_pdf')) {
        $persons_obj->person_update_invoice_pdf($event_uid);
    }
};

require __DIR__ . '/pdf-creation.php';
