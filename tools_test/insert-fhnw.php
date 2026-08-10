<?php
global $wpdb;

$wp_load = realpath(__DIR__ . '/../../../../../../wp-load.php');

if (!$wp_load || !file_exists($wp_load)) {
    die('wp-load.php nicht gefunden. Pfad prüfen: ' . __DIR__);
}

require_once $wp_load;

global $wpdb;

if (!$wpdb) {
    die('$wpdb ist nicht verfügbar.');
}

$event_uid   = 'fhnw-practice-day-2026';
$str_slots   = '742';
$fky_slot_id = 738;

$timezones_table  = $wpdb->prefix . 'evtmgr_timezones';
$presenters_table = $wpdb->prefix . 'evtmgr_presenters';
$workshops_table  = $wpdb->prefix . 'evtmgr_workshops';
$link_table       = $wpdb->prefix . 'evtmgr_tbx_workshops_presenters';

$items = [
    [
        'from' => '10:00',
        'to' => '10:30',
        'label' => '10:00–10:30 Uhr',
        'first_name' => 'Ubaldo',
        'last_name' => 'Piccone',
        'employer' => 'QoQa',
        'title' => 'Ubaldo Piccone (QoQa)',
    ],
    [
        'from' => '10:40',
        'to' => '11:10',
        'label' => '10:40–11:10 Uhr',
        'first_name' => 'Fabian',
        'last_name' => 'Büchler',
        'employer' => 'Brack',
        'title' => 'Fabian Büchler (Brack)',
    ],
    [
        'from' => '11:20',
        'to' => '11:50',
        'label' => '11:20–11:50 Uhr',
        'first_name' => 'Sebastian',
        'last_name' => 'Paul',
        'employer' => 'Victorinox',
        'title' => 'Sebastian Paul (Victorinox)',
    ],
    [
        'from' => '13:30',
        'to' => '14:00',
        'label' => '13:30–14:00 Uhr',
        'first_name' => 'Nico',
        'last_name' => 'Bellabarba',
        'employer' => 'Headstart Collective',
        'title' => 'Nico Bellabarba (Headstart Collective)',
    ],
    [
        'from' => '14:10',
        'to' => '14:40',
        'label' => '14:10–14:40 Uhr',
        'first_name' => 'Yorck',
        'last_name' => 'v. Mirbach',
        'employer' => 'Amazon',
        'title' => 'Yorck v. Mirbach (Amazon)',
    ],
    [
        'from' => '15:10',
        'to' => '15:40',
        'label' => '15:10–15:40 Uhr',
        'first_name' => 'Pelin Anli',
        'last_name' => 'Bedirhanoglu',
        'employer' => 'Zalando',
        'title' => 'Pelin Anli Bedirhanoglu (Zalando)',
    ],
    [
        'from' => '15:50',
        'to' => '16:20',
        'label' => '15:50–16:20 Uhr',
        'first_name' => 'Philippe',
        'last_name' => 'Huwyler',
        'employer' => 'Coop Online',
        'title' => 'Philippe Huwyler (Coop Online)',
    ],
    [
        'from' => '16:30',
        'to' => '17:00',
        'label' => '16:30–17:00 Uhr',
        'first_name' => 'Nicolas',
        'last_name' => 'Hänny',
        'employer' => 'NIKIN',
        'title' => 'Nicolas Hänny (NIKIN)',
    ],
    [
        'from' => '17:10',
        'to' => '18:00',
        'label' => '17:10–18:00 Uhr',
        'first_name' => 'Podium zur e-ID',
        'last_name' => '',
        'employer' => 'Swisscom / BJ',
        'title' => 'Podium zur e-ID (Swisscom / BJ) & Verabschiedung durch Rico Travella',
    ],
];

$wpdb->query('START TRANSACTION');

try {
    $sort_order = 10;

    foreach ($items as $item) {
        // 1. Timezone / Zeit-Slot einfügen
        $wpdb->insert(
            $timezones_table,
            [
                'dtm_time_from' => $item['from'],
                'dtm_time_to' => $item['to'],
                'str_timezone_name_de' => $item['label'],
                'fky_event_uid' => $event_uid,
                'int_sort_order' => $sort_order,
                'str_slots' => $str_slots,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception('Fehler bei timezone insert: ' . $wpdb->last_error);
        }

        $timezone_id = $wpdb->insert_id;

        // 2. Presenter einfügen
        $wpdb->insert(
            $presenters_table,
            [
                'str_first_name' => $item['first_name'],
                'str_last_name' => $item['last_name'],
                'str_employer' => $item['employer'],
            ],
            [
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception('Fehler bei presenter insert: ' . $wpdb->last_error);
        }

        $presenter_id = $wpdb->insert_id;

        // 3. Workshop einfügen
        $wpdb->insert(
            $workshops_table,
            [
                'str_workshop_title_de' => $item['title'],
                'fky_slot_id' => $fky_slot_id,
                'fky_timezone_id' => $timezone_id,
                'fky_event_uid' => $event_uid,
            ],
            [
                '%s',
                '%d',
                '%d',
                '%s',
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception('Fehler bei workshop insert: ' . $wpdb->last_error);
        }

        $workshop_id = $wpdb->insert_id;

        // 4. Workshop mit Presenter verknüpfen
        $wpdb->insert(
            $link_table,
            [
                'fky_workshop_id' => $workshop_id,
                'fky_person_id' => $presenter_id,
            ],
            [
                '%d',
                '%d',
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception('Fehler bei workshop-presenter link insert: ' . $wpdb->last_error);
        }

        $sort_order += 10;
    }

    $wpdb->query('COMMIT');

    echo 'Alle Inserts erfolgreich ausgeführt.';

} catch (Exception $e) {
    $wpdb->query('ROLLBACK');

    echo 'Fehler: ' . esc_html($e->getMessage());
}