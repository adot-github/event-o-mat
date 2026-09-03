<?php

$wp_load = dirname(__FILE__, 8) . '/wp-load.php';

if (!file_exists($wp_load)) {
    die('wp-load.php not found: ' . htmlspecialchars($wp_load, ENT_QUOTES, 'UTF-8'));
}

require_once $wp_load;

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('edit_posts')) {
    wp_die('Keine Berechtigung.');
}

require_once dirname(__DIR__) . '/../classes/class-event-registration.php';
require_once dirname(__DIR__) . '/../classes/class-pdf-creation.php';

$pdf_creator        = new Event_Registration_Pdf_Creation(__DIR__);
$event_registration = new Event_Registration_Context();
$event_uid          = $event_registration->get_cookie_event_uid(true);

global $wpdb;

$upload_dir   = wp_upload_dir();
$storage_base = rtrim((string) ($upload_dir['basedir'] ?? ''), '/\\') . DIRECTORY_SEPARATOR . sanitize_file_name($event_uid);

/*
 * Only the generated-PDF tree  <event_uid>/pdf/  is ever read or deleted here.
 * <event_uid>/assets/  and  <event_uid>/pdf-templates/  are never touched.
 */
$pdf_base      = $storage_base . DIRECTORY_SEPARATOR . 'pdf';
$pdf_base_real = is_dir($pdf_base) ? realpath($pdf_base) : '';

/* ── Helpers ─────────────────────────────────────────────────────────────── */

if (!function_exists('filestorage_cols_present')) {
    /** Column names from $wanted that actually exist on $table. */
    function filestorage_cols_present($table, array $wanted)
    {
        global $wpdb;

        $have = array_map('strtolower', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $table
        )));

        return array_values(array_filter(
            $wanted,
            static fn($c) => in_array(strtolower($c), $have, true)
        ));
    }
}

if (!function_exists('filestorage_pdf_whitelist')) {
    /**
     * Lower-cased PDF file names referenced anywhere in the DB for this event:
     * booklet (wp_evtmgr_events.str_event_pdf_*), workshop flyers / booking lists
     * (wp_evtmgr_workshops.str_workshop_pdf_*), per-person diploma / invoice /
     * program / ticket, plus the deterministic etiketten file name.
     *
     * @return array<string,bool>  name => true
     */
    function filestorage_pdf_whitelist($event_uid)
    {
        global $wpdb;

        $names = [];
        $add   = static function ($val) use (&$names) {
            $val = trim((string) $val);
            if ($val !== '') {
                $names[strtolower(basename($val))] = true;
            }
        };

        $sources = [
            [$wpdb->prefix . 'evtmgr_events',    'event_uid',     ['str_event_pdf_de', 'str_event_pdf_fr', 'str_event_pdf_it', 'str_event_pdf_en']],
            [$wpdb->prefix . 'evtmgr_workshops', 'fky_event_uid', ['str_workshop_pdf_de', 'str_workshop_pdf_fr', 'str_workshop_pdf_it', 'str_workshop_pdf_en']],
            [$wpdb->prefix . 'evtmgr_persons',   'fky_event_uid', ['str_diploma_pdf', 'str_invoice_pdf', 'str_program_pdf', 'str_ticket_pdf']],
        ];

        foreach ($sources as [$table, $key_col, $wanted]) {
            $cols = filestorage_cols_present($table, $wanted);
            if (empty($cols)) {
                continue;
            }

            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT ' . implode(', ', $cols) . " FROM {$table} WHERE {$key_col} = %s",
                $event_uid
            ), ARRAY_A);

            foreach ((array) $rows as $row) {
                foreach ($row as $v) {
                    $add($v);
                }
            }
        }

        $add('etiketten_' . sanitize_file_name($event_uid) . '.pdf');

        return $names;
    }
}

if (!function_exists('filestorage_scan_pdf_tree')) {
    /**
     * Scan  <event_uid>/pdf/  one level deep.
     *
     * @return array<string, array{path:string, files:array<int,array{name:string,obsolete:bool}>}>
     *         keyed by "pdf" / "pdf/<subfolder>"
     */
    function filestorage_scan_pdf_tree($pdf_base, array $whitelist)
    {
        $out = [];

        if (!is_dir($pdf_base)) {
            return $out;
        }

        $collect = static function ($dir, $label) use (&$out, $whitelist) {
            $files = [];

            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                if (is_file($dir . DIRECTORY_SEPARATOR . $f)) {
                    $files[] = [
                        'name'     => $f,
                        'obsolete' => !isset($whitelist[strtolower($f)]),
                    ];
                }
            }

            if (!empty($files)) {
                usort($files, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
                $out[$label] = ['path' => $dir, 'files' => $files];
            }
        };

        $collect($pdf_base, 'pdf');

        foreach (scandir($pdf_base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $sub = $pdf_base . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($sub)) {
                $collect($sub, 'pdf/' . $entry);
            }
        }

        ksort($out);

        return $out;
    }
}

if (!function_exists('filestorage_safe_unlink')) {
    /** unlink() only when $full_path resolves strictly inside $pdf_base_real. */
    function filestorage_safe_unlink($full_path, $pdf_base_real)
    {
        if ($pdf_base_real === '') {
            return false;
        }
        $real = realpath($full_path);
        if ($real === false || strpos($real, $pdf_base_real . DIRECTORY_SEPARATOR) !== 0) {
            return false;
        }
        return @unlink($full_path);
    }
}

/* ── Gather state ────────────────────────────────────────────────────────── */

$whitelist = filestorage_pdf_whitelist($event_uid);
$tree      = filestorage_scan_pdf_tree($pdf_base, $whitelist);

$count_tree = static function (array $tree) {
    $total = 0;
    $obsolete = 0;
    foreach ($tree as $g) {
        foreach ($g['files'] as $f) {
            $total++;
            if ($f['obsolete']) {
                $obsolete++;
            }
        }
    }
    return [$total, $obsolete];
};

[$total_files, $obsolete_count] = $count_tree($tree);

/* ── Actions ─────────────────────────────────────────────────────────────── */

$action  = ($_SERVER['REQUEST_METHOD'] === 'POST') ? sanitize_key(wp_unslash($_POST['action'] ?? '')) : '';
$deleted = [];
$errors  = [];
$did_delete = false;

if (in_array($action, ['delete_all', 'delete_obsolete'], true) && (string) ($_POST['confirm_delete'] ?? '') === '1') {
    check_admin_referer('filestorage_clean_' . $event_uid);

    $did_delete    = true;
    $obsolete_only = ($action === 'delete_obsolete');

    foreach ($tree as $label => $group) {
        foreach ($group['files'] as $file) {
            if ($obsolete_only && !$file['obsolete']) {
                continue;
            }

            $full = $group['path'] . DIRECTORY_SEPARATOR . $file['name'];

            if (filestorage_safe_unlink($full, $pdf_base_real)) {
                $deleted[] = $label . '/' . $file['name'];
            } else {
                $errors[] = $label . '/' . $file['name'];
            }
        }
    }

    $tree = filestorage_scan_pdf_tree($pdf_base, $whitelist);
    [$total_files, $obsolete_count] = $count_tree($tree);
}

$preview_obsolete = ($action === 'preview_obsolete');

/* ── Output ──────────────────────────────────────────────────────────────── */

$pdf_creator->show_page_header('Dateiablage bereinigen');

?>
<h1 class="mb-3">Dateiablage bereinigen</h1>

<p>
    <strong>Event:</strong> <?php echo esc_html($event_uid); ?><br>
    <span class="text-muted">Bereinigt wird ausschliesslich <code><?php echo esc_html($event_uid); ?>/pdf/</code>.
    <code>/assets</code> und <code>/pdf-templates</code> bleiben unberührt.</span>
</p>

<?php if ($did_delete) : ?>

    <?php if (!empty($deleted)) : ?>
        <div class="alert alert-success mb-3">
            <strong><?php echo count($deleted); ?> Datei(en) gelöscht.</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($deleted as $f) : ?>
                    <li><?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <div class="alert alert-danger mb-3">
            <strong><?php echo count($errors); ?> Datei(en) konnten nicht gelöscht werden:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $f) : ?>
                    <li><?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($deleted) && empty($errors)) : ?>
        <div class="alert alert-info mb-3">Nichts zu löschen.</div>
    <?php endif; ?>

    <p><a class="btn btn-secondary" href="<?php echo esc_url(remove_query_arg([])); ?>">Zurück zur Übersicht</a></p>

<?php elseif ($total_files === 0) : ?>

    <div class="alert alert-info mb-3">
        Keine generierten PDFs für <strong><?php echo esc_html($event_uid); ?></strong> vorhanden.
    </div>

<?php elseif ($preview_obsolete) : ?>

    <?php
    $obsolete_tree = [];
    foreach ($tree as $label => $group) {
        $obs = array_values(array_filter($group['files'], static fn($f) => $f['obsolete']));
        if (!empty($obs)) {
            $obsolete_tree[$label] = $obs;
        }
    }
    ?>

    <?php if (empty($obsolete_tree)) : ?>
        <div class="alert alert-success mb-3">Keine obsoleten Dateien – alles in der Datenbank referenziert.</div>
        <p><a class="btn btn-secondary" href="<?php echo esc_url(remove_query_arg([])); ?>">Zurück</a></p>
    <?php else : ?>
        <div class="alert alert-warning">
            Folgende <strong><?php echo esc_html($obsolete_count); ?> Datei(en)</strong> sind
            <strong>obsolet</strong> (kein passender Eintrag in der Datenbank) und werden gelöscht:
        </div>

        <table class="table table-bordered table-sm mb-4" style="max-width:900px;">
            <thead class="table-light">
                <tr><th>Unterordner</th><th>Datei</th><th style="width:110px;">Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($obsolete_tree as $label => $files) : ?>
                    <?php foreach ($files as $i => $file) : ?>
                        <tr>
                            <td style="white-space:nowrap;vertical-align:top;">
                                <?php echo $i === 0 ? '<strong>' . esc_html($event_uid . '/' . $label) . '</strong>' : ''; ?>
                            </td>
                            <td><?php echo esc_html($file['name']); ?></td>
                            <td><span class="badge bg-warning text-dark">obsolet</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" class="d-inline">
            <?php wp_nonce_field('filestorage_clean_' . $event_uid); ?>
            <input type="hidden" name="action" value="delete_obsolete">
            <input type="hidden" name="confirm_delete" value="1">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('<?php echo esc_js($obsolete_count); ?> obsolete Datei(en) endgültig löschen?')">
                Diese <?php echo esc_html($obsolete_count); ?> Datei(en) jetzt löschen
            </button>
        </form>
        <a class="btn btn-secondary" href="<?php echo esc_url(remove_query_arg([])); ?>">Abbrechen</a>
    <?php endif; ?>

<?php else : ?>

    <p>
        <strong><?php echo esc_html($total_files); ?></strong> Datei(en) in
        <strong><?php echo count($tree); ?></strong> Ordner(n) unter <code>pdf/</code>,
        davon <strong><?php echo esc_html($obsolete_count); ?></strong> obsolet.
    </p>

    <div class="d-flex gap-2 mb-4 flex-wrap">
        <form method="post" class="d-inline">
            <?php wp_nonce_field('filestorage_clean_' . $event_uid); ?>
            <input type="hidden" name="action" value="delete_all">
            <input type="hidden" name="confirm_delete" value="1">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Alle <?php echo esc_js($total_files); ?> Datei(en) unter «<?php echo esc_js($event_uid); ?>/pdf/» wirklich löschen?')">
                Sämtliche Dateien löschen (<?php echo esc_html($total_files); ?>)
            </button>
        </form>

        <form method="post" class="d-inline">
            <?php wp_nonce_field('filestorage_clean_' . $event_uid); ?>
            <input type="hidden" name="action" value="preview_obsolete">
            <button type="submit" class="btn btn-warning" <?php echo $obsolete_count === 0 ? 'disabled' : ''; ?>>
                Nur obsolete Dateien löschen (<?php echo esc_html($obsolete_count); ?>)
            </button>
        </form>
    </div>

    <table class="table table-bordered table-sm mb-4" style="max-width:900px;">
        <thead class="table-light">
            <tr><th>Unterordner</th><th>Datei</th><th style="width:110px;">Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tree as $label => $group) : ?>
                <?php foreach ($group['files'] as $i => $file) : ?>
                    <tr>
                        <td style="white-space:nowrap;vertical-align:top;">
                            <?php echo $i === 0 ? '<strong>' . esc_html($event_uid . '/' . $label) . '</strong>' : ''; ?>
                        </td>
                        <td><?php echo esc_html($file['name']); ?></td>
                        <td>
                            <?php if ($file['obsolete']) : ?>
                                <span class="badge bg-warning text-dark">obsolet</span>
                            <?php else : ?>
                                <span class="badge bg-success">in DB</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

<?php $pdf_creator->show_page_footer(); ?>
