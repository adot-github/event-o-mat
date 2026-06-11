<link rel='stylesheet' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/pages/assets/dashboard.css' media='all' />

<?php

if (!defined('ABSPATH')) {
    exit;
}

$report_title = 'Speaker-Report';
$report_table = 'wp_evtmgr_presenters';

$report_fields = array(
    'id'     => 'id',
    'str_first_name'    => 'Vorname',
    'str_last_name'     => 'Nachname',
    'str_email'        => 'E-Mail',
    'str_phone'        => 'Telefon',
    'str_address'      => 'Adresse',
    'str_zip'          => 'PLZ',
    'str_city'         => 'Ort',
    'str_institution_de'  => 'Organisation',
    'str_language'     => 'Sprache',
);

$report_order_by = array(
    'str_last_name'  => 'ASC',
    'str_first_name' => 'ASC',
);

$report_file_name = 'presenter-report';

require __DIR__ . '/report-creation.php';
