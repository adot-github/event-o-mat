<?php

if (!defined('ABSPATH')) {
    exit;
}

$report_title = 'Umsatz-Report';
$report_table = 'wp_evtmgr_persons';

$report_fields = array(
    'id'                 => 'id',
    'str_first_name'     => 'Vorname',
    'str_last_name'      => 'Nachname',
    'str_email'          => 'E-Mail',
    'str_phone'          => 'Telefon',
    'str_address'        => 'Adresse',
    'str_zip'            => 'PLZ',
    'str_city'           => 'Ort',
    'str_institution'    => 'Organisation',
    'str_language'       => 'Sprache',
    'num_invoice_total'         => 'Betrag',
    'int_billing_status' => 'RG-Status',
);

$report_owner_column = 'fky_event_uid';

$report_order_by = array(
    'str_last_name'  => 'ASC',
    'str_first_name' => 'ASC',
);

$report_file_name = 'umsatz-report';

require __DIR__ . '/report-creation.php';
