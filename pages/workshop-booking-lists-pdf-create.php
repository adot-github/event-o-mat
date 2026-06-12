
<?php

/**
 * Caller for workshop booking-list PDF creation.
 * Keep this filename: workshop-pdf-booking-lists.php
 */

$type_of_pdf       = 'Workshop-Buchungslisten';
$type_of_pdf_sing  = 'Workshop-Buchungsliste';
$pdf_layout        = 'dachverband-workshop-liste.php';
$subfolder_for_pdf = 'workshop-booking-lists';

$creator_file = __DIR__ . '/pdf-creation-workshops.php';

if (!file_exists($creator_file)) {
    $message = 'pdf-creation-workshops.php wurde nicht gefunden: ' . $creator_file;

    if (function_exists('wp_die')) {
        wp_die(esc_html($message));
    }

    die(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

require $creator_file;
