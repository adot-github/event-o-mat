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
$fky_slot_id = 741;

$timezones_table  = $wpdb->prefix . 'evtmgr_timezones';
$presenters_table = $wpdb->prefix . 'evtmgr_presenters';
$workshops_table  = $wpdb->prefix . 'evtmgr_workshops';
$link_table       = $wpdb->prefix . 'evtmgr_tbx_workshops_presenters';

$items = [
    [
        'from' => '10:00',
        'to' => '10:30',
        'title' => 'Worldline',
        'presenters' => [
            [
                'first_name' => 'Worldline',
                'last_name' => '',
                'employer' => 'Worldline',
            ],
        ],
    ],
    [
        'from' => '10:40',
        'to' => '11:10',
        'title' => 'Boris Krstic (Actindo) Powerfood / ELCO',
        'presenters' => [
            [
                'first_name' => 'Boris',
                'last_name' => 'Krstic',
                'employer' => 'Actindo',
            ],
        ],
    ],
    [
        'from' => '11:20',
        'to' => '11:50',
        'title' => 'TWINT',
        'presenters' => [
            [
                'first_name' => 'TWINT',
                'last_name' => '',
                'employer' => 'TWINT',
            ],
        ],
    ],
    [
        'from' => '13:30',
        'to' => '14:00',
        'title' => 'Michel Janz (CRIF) & Patrik Zimmerli (Meier Tobler)',
        'presenters' => [
            [
                'first_name' => 'Michel',
                'last_name' => 'Janz',
                'employer' => 'CRIF',
            ],
            [
                'first_name' => 'Patrik',
                'last_name' => 'Zimmerli',
                'employer' => 'Meier Tobler',
            ],
        ],
    ],
    [
        'from' => '14:10',
        'to' => '14:40',
        'title' => 'Kristel Heim (DIVISA)',
        'presenters' => [
            [
                'first_name' => 'Kristel',
                'last_name' => 'Heim',
                'employer' => 'DIVISA',
            ],
        ],
    ],
    [
        'from' => '15:10',
        'to' => '15:40',
        'title' => 'Ingo Schegk (Ochsner Shoes)',
        'presenters' => [
            [
                'first_name' => 'Ingo',
                'last_name' => 'Schegk',
                'employer' => 'Ochsner Shoes',
            ],
        ],
    ],
    [
        'from' => '15:50',
        'to' => '16:20',
        'title' => 'Martin Jungfer (Galaxus)',
        'presenters' => [
            [
                'first_name' => 'Martin',
                'last_name' => 'Jungfer',
                'employer' => 'Galaxus',
            ],
        ],
    ],
    [
        'from' => '16:30',
        'to' => '17:00',
        'title' => 'Thomas Frierss (XeroGrafiX) & Schwarzenberg',
        'presenters' => [
            [
                'first_name' => 'Thomas',
                'last_name' => 'Frierss',
                'employer' => 'XeroGrafiX',
            ],
            [
                'first_name' => 'Schwarzenberg',
                'last_name' => '',
                'employer' => '',
            ],
        ],
    ],
    [
        'from' => '17:10',
        'to' => '17:40',
        'title' => 'Shopware',
        'presenters' => [
            [
                'first_name' => 'Shopware',
                'last_name' => '',
                'employer' => 'Shopware',
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

    echo 'Alle Inserts für Slot 741 erfolgreich ausgeführt.';

} catch (Exception $e) {
    $wpdb->query('ROLLBACK');

    echo 'Fehler: ' . esc_html($e->getMessage());
}