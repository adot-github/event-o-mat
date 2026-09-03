
<?php

/**
 * Caller for workshop booking-list PDF creation.
 * Keep this filename: workshop-pdf-booking-lists.php
 */

$type_of_pdf       = 'Workshop-Flyer';
$type_of_pdf_sing  = 'Workshop-Flyer';
$pdf_layout        = 'workshop-flyer-2.php';
$subfolder_for_pdf = 'workshop-flyer';

/*
 * Before the workshops are listed / generated: fill
 * wp_evtmgr_workshops.str_workshop_pdf_<lang> for every workshop of the event
 * where it is still empty – a web-safe file name from the title plus "-id-<id>".
 * That stored value is what workshop_pdf_file_name() then uses for the flyer PDF.
 */
$before_pdf_creation_callback = function ($event_uid) {
    if (!class_exists('Evtmgr_Workshops')) {
        return;
    }

    $workshops_obj = new Evtmgr_Workshops();

    if (method_exists($workshops_obj, 'workshop_update_pdf_filenames')) {
        $workshops_obj->workshop_update_pdf_filenames($event_uid);
    }
};

$creator_file = __DIR__ . '/pdf-creation-by-workshops.php';

if (!file_exists($creator_file)) {
    $message = 'pdf-creation-by-workshops.php wurde nicht gefunden: ' . $creator_file;

    if (function_exists('wp_die')) {
        wp_die(esc_html($message));
    }

    die(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

require $creator_file;
