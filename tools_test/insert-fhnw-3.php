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
$fky_slot_id = 740; // ggf. anpassen

$timezones_table  = $wpdb->prefix . 'evtmgr_timezones';
$presenters_table = $wpdb->prefix . 'evtmgr_presenters';
$workshops_table  = $wpdb->prefix . 'evtmgr_workshops';
$link_table       = $wpdb->prefix . 'evtmgr_tbx_workshops_presenters';

$items = [
    [
        'from' => '10:00',
        'to' => '10:30',
        'title' => 'Sara Schwartz (Betty Bossi) & V. Wurster',
        'presenters' => [
            [
                'first_name' => 'Sara',
                'last_name' => 'Schwartz',
                'employer' => 'Betty Bossi',
            ],
            [
                'first_name' => 'V.',
                'last_name' => 'Wurster',
                'employer' => '',
            ],
        ],
    ],
    [
        'from' => '10:40',
        'to' => '11:10',
        'title' => 'Gabi Troxler (Okitah)',
        'presenters' => [
            [
                'first_name' => 'Gabi',
                'last_name' => 'Troxler',
                'employer' => 'Okitah',
            ],
        ],
    ],
    [
        'from' => '11:20',
        'to' => '11:50',
        'title' => 'Michael Ammann (Boxalino)',
        'presenters' => [
            [
                'first_name' => 'Michael',
                'last_name' => 'Ammann',
                'employer' => 'Boxalino',
            ],
        ],
    ],
    [
        'from' => '13:30',
        'to' => '14:00',
        'title' => 'Vito Critti (CONVOTIS)',
        'presenters' => [
            [
                'first_name' => 'Vito',
                'last_name' => 'Critti',
                'employer' => 'CONVOTIS',
            ],
        ],
    ],
    [
        'from' => '14:10',
        'to' => '14:40',
        'title' => 'Kai Jesse (BSI & Walbusch)',
        'presenters' => [
            [
                'first_name' => 'Kai',
                'last_name' => 'Jesse',
                'employer' => 'BSI & Walbusch',
            ],
        ],
    ],
    [
        'from' => '15:10',
        'to' => '15:40',
        'title' => 'Wer will den letzten AI Slot?',
        'presenters' => [
            [
                'first_name' => 'AI',
                'last_name' => 'Slot',
                'employer' => '',
            ],
        ],
    ],
    [
        'from' => '15:50',
        'to' => '16:20',
        'title' => 'Mario Laubi (Import Parfümerie)',
        'presenters' => [
            [
                'first_name' => 'Mario',
                'last_name' => 'Laubi',
                'employer' => 'Import Parfümerie',
            ],
        ],
    ],
    [
        'from' => '16:30',
        'to' => '17:00',
        'title' => 'Julia Herz (dreamleap)',
        'presenters' => [
            [
                'first_name' => 'Julia',
                'last_name' => 'Herz',
                'employer' => 'dreamleap',
            ],
        ],
    ],
    [
        'from' => '17:10',
        'to' => '17:40',
        'title' => 'Christopher Geisler (Ochsner Sport)',
        'presenters' => [
            [
                'first_name' => 'Christopher',
                'last_name' => 'Geisler',
                'employer' => 'Ochsner Sport',
            ],
        ],
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

        // 2. Workshop einfügen
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

        // 3. Presenter einfügen und mit Workshop verknüpfen
        foreach ($item['presenters'] as $presenter) {
            $wpdb->insert(
                $presenters_table,
                [
                    'str_first_name' => $presenter['first_name'],
                    'str_last_name' => $presenter['last_name'],
                    'str_employer' => $presenter['employer'],
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

            $person_id = $wpdb->insert_id;

            $wpdb->insert(
                $link_table,
                [
                    'fky_workshop_id' => $workshop_id,
                    'fky_person_id' => $person_id,
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
    }

    $wpdb->query('COMMIT');

    echo 'Alle Inserts für das AI-Dataset erfolgreich ausgeführt.';

} catch (Exception $e) {
    $wpdb->query('ROLLBACK');

    echo 'Fehler: ' . esc_html($e->getMessage());
}