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

/*
 * Überträgt wp_bgf_workshops.Themenpfad nach wp_evtmgr_tbx_workshops_categories.
 *
 * Join-Logik:
 *   - wp_bgf_workshops.Titel      <-> wp_evtmgr_workshops.str_workshop_title_de (über den Titel)
 *   - wp_bgf_workshops.Themenpfad <-> wp_evtmgr_categories.str_category_de      (über den Kategorienamen)
 *
 * Die Titel weichen in ein paar Fällen durch eingebettete Zeilenumbrüche oder
 * ein verirrtes NBSP-Zeichen (0xC2 0xA0) voneinander ab, darum wird für den
 * Vergleich normalisiert (Whitespace zusammengefasst, getrimmt).
 *
 * Bereits vorhandene Zuordnungen (gleiches Event + Workshop) werden übersprungen,
 * das Skript kann also gefahrlos mehrfach ausgeführt werden.
 */

function evtmgr_norm_title_sql($column_ref, $nbsp_placeholder) {
    return "TRIM(REGEXP_REPLACE(REPLACE(REPLACE({$column_ref}, CHAR(10), ' '), {$nbsp_placeholder}, ' '), '[[:space:]]+', ' '))";
}

$event_uid = 'fhnw-bgf-2026';
$nbsp      = "\xC2\xA0";

$workshops_table  = $wpdb->prefix . 'evtmgr_workshops';
$bgf_table        = $wpdb->prefix . 'bgf_workshops';
$categories_table = $wpdb->prefix . 'evtmgr_categories';
$link_table       = $wpdb->prefix . 'evtmgr_tbx_workshops_categories';

$norm_w = evtmgr_norm_title_sql('w.str_workshop_title_de', '%s');
$norm_b = evtmgr_norm_title_sql('b.Titel', '%s');

$wpdb->query('START TRANSACTION');

try {
    // ── 1. bgf-Workshops ohne passenden Titel in wp_evtmgr_workshops (zur Kontrolle) ──
    $unmatched = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT b.id, b.Titel, b.Themenpfad
            FROM {$bgf_table} b
            LEFT JOIN {$workshops_table} w
                ON w.fky_event_uid = %s
                AND {$norm_w} = {$norm_b}
            WHERE w.id IS NULL
            ",
            $event_uid,
            $nbsp,
            $nbsp
        )
    );

    if ($wpdb->last_error) {
        throw new Exception('Fehler bei Unmatched-Check: ' . $wpdb->last_error);
    }

    // ── 2. Zuordnungen einfügen ──────────────────────────────────────────────
    $inserted = $wpdb->query(
        $wpdb->prepare(
            "
            INSERT INTO {$link_table} (fky_event_uid, fky_workshop_id, fky_category_id)
            SELECT DISTINCT w.fky_event_uid, w.id, c.id
            FROM {$workshops_table} w
            INNER JOIN {$bgf_table} b
                ON {$norm_w} = {$norm_b}
            INNER JOIN {$categories_table} c
                ON c.fky_event_uid = w.fky_event_uid
                AND c.str_category_de = b.Themenpfad
            WHERE w.fky_event_uid = %s
              AND NOT EXISTS (
                  SELECT 1 FROM {$link_table} existing
                  WHERE existing.fky_event_uid = w.fky_event_uid
                    AND existing.fky_workshop_id = w.id
              )
            ",
            $nbsp,
            $nbsp,
            $event_uid
        )
    );

    if ($wpdb->last_error) {
        throw new Exception('Fehler beim Insert: ' . $wpdb->last_error);
    }

    $wpdb->query('COMMIT');

    echo '<p>' . (int) $inserted . ' Zuordnungen in ' . esc_html($link_table) . ' eingefügt.</p>';

    if (!empty($unmatched)) {
        echo '<p>Ohne passenden Workshop-Titel (übersprungen):</p><ul>';
        foreach ($unmatched as $row) {
            echo '<li>#' . (int) $row->id . ' – ' . esc_html($row->Titel) . ' (Themenpfad: ' . esc_html((string) $row->Themenpfad) . ')</li>';
        }
        echo '</ul>';
    }

} catch (Exception $e) {
    $wpdb->query('ROLLBACK');

    echo 'Fehler: ' . esc_html($e->getMessage());
}
