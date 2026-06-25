
<?php

if (!defined('ABSPATH')) {
    exit;
}

$report_title = 'Teilnehmenden-Report';
$report_table = 'wp_evtmgr_persons';

$report_fields = array(
    'id'     => 'id',
    'str_first_name'    => 'Vorname',
    'str_last_name'     => 'Nachname',
    'str_email'        => 'E-Mail',
    'str_phone'        => 'Telefon',
    'str_address'      => 'Adresse',
    'str_zip'          => 'PLZ',
    'str_city'         => 'Ort',
    'str_country'      => 'Land',
    'str_institution'  => 'Organisation',
    'str_language'     => 'Sprache',
    'fky_event_uid'      => 'Event Uid',
);

$report_filter_by_event_uid = true;
$report_owner_field = 'fky_event_uid';

$report_order_by  = 'str_last_name ASC, str_first_name ASC';
$report_file_name = 'teilnehmenden-report';

require __DIR__ . '/report-creation.php';
