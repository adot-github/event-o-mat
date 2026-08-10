<?php

$wp_load = realpath(__DIR__ . '/../../../../../../wp-load.php');

if (!$wp_load || !file_exists($wp_load)) {
    die('wp-load.php nicht gefunden. Pfad prüfen: ' . __DIR__);
}

require_once $wp_load;

global $wpdb;

if (!$wpdb) {
    die('$wpdb ist nicht verfügbar.');
}

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Keine Berechtigung.');
}

$event_uid   = 'fhnw-practice-day-2026';
$fky_slot_id = 739;

$timezones_table  = $wpdb->prefix . 'evtmgr_timezones';
$presenters_table = $wpdb->prefix . 'evtmgr_presenters';
$workshops_table  = $wpdb->prefix . 'evtmgr_workshops';
$link_table       = $wpdb->prefix . 'evtmgr_tbx_workshops_presenters';

$items = [
    [
        'from' => '10:00',
        'to' => '10:30',
        'first_name' => 'Richard',
        'last_name' => 'Geibel',
        'employer' => 'IU',
        'title' => 'Prof. Richard Geibel (IU)',
    ],
    [
        'from' => '10:40',
        'to' => '11:10',
        'first_name' => 'Knut',
        'last_name' => 'Hinkelmann',
        'employer' => 'FHNW',
        'title' => 'Prof. Knut Hinkelmann (FHNW)',
    ],
    [
        'from' => '11:20',
        'to' => '11:50',
        'first_name' => 'Marc',
        'last_name' => 'Peter',
        'employer' => 'HES-SO',
        'title' => 'Prof. Marc Peter (HES-SO)',
    ],
    [
        'from' => '13:30',
        'to' => '14:00',
        'first_name' => 'Mario',
        'last_name' => 'Fischer',
        'employer' => 'THWS Würzburg',
        'title' => 'Prof. Mario Fischer (THWS Würzburg)',
    ],
    [
        'from' => '14:10',
        'to' => '14:40',
        'first_name' => 'Falk',
        'last_name' => 'Uebernickel',
        'employer' => 'ZHAW',
        'title' => 'Prof. Falk Uebernickel (ZHAW)',
    ],
    [
        'from' => '15:10',
        'to' => '15:40',
        'first_name' => 'Martin',
        'last_name' => 'Stucki',
        'employer' => 'LOEB / Lüthy & Alter / FHNW',
        'title' => 'Martin Stucki (LOEB) Lüthy & Alter (FHNW)',
    ],
    [
        'from' => '15:50',
        'to' => '16:20',
        'first_name' => 'Christian',
        'last_name' => 'Lucas',
        'employer' => 'IU',
        'title' => 'Christian Lucas (IU)',
    ],
    [
        'from' => '16:30',
        'to' => '17:00',
        'first_name' => 'Cristina',
        'last_name' => 'Völkl Wolf',
        'employer' => 'THWS Würzburg',
        'title' => 'Prof. Cristina Völkl Wolf (THWS Würzburg)',
    ],
    [
        'from' => '17:10',
        'to' => '17:40',
        'first_name' => 'Academic',
        'last_name' => 'Slot',
        'employer' => '',
        'title' => 'Wer will den letzten Academic Slot?',
    ],
];

$wpdb->query('START TRANSACTION');

try {
    foreach ($items as $item) {
        // 1. Bestehende Timezone-ID suchen
        $timezone_id = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$timezones_table}
                WHERE fky_event_uid = %s
                  AND dtm_time_from = %s
                  AND dtm_time_to = %s
                LIMIT 1
                ",
                $event_uid,
                $item['from'],
                $item['to']
            )
        );

        if (!$timezone_id) {
            throw new Exception(
                'Keine passende Timezone gefunden für ' .
                $item['from'] . ' - ' . $item['to']
            );
        }

        // 2. Presenter einfügen
        $wpdb->insert(
            $presenters_table,
            [
                'str_first_name' => $item['first_name'],
                'str_last_name' => $item['last_name'],
                'str_employer' => $item['employer'],
                'fky_event_uid' => $event_uid,
            ],
            [
                '%s',
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
    }

    $wpdb->query('COMMIT');

    echo 'Alle Inserts für das zweite Dataset erfolgreich ausgeführt.';

} catch (Exception $e) {
    $wpdb->query('ROLLBACK');

    echo 'Fehler: ' . esc_html($e->getMessage());
}