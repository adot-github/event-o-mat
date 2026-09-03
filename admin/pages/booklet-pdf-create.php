<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page: generate the multi-page event booklet PDF.
 *
 * All content logic lives in Evtmgr_Pdf_Content, all assembly in Evtmgr_Booklet.
 * The per-event layout is defined in
 *   uploads/<event_uid>/pdf-templates/booklet/index.php
 */

$_pdf_classes_dir = dirname(__DIR__) . '/../classes/';
require_once $_pdf_classes_dir . 'class-helpers.php';
require_once $_pdf_classes_dir . 'class-evtmgr-events.php';
require_once $_pdf_classes_dir . 'class-evtmgr-booklet.php';

require_once __DIR__ . '/vendor/autoload.php';

$events_obj = new Evtmgr_Events();
$event_uid  = $events_obj->get_current_event_uid(true);
$event      = $events_obj->get_current_event('de', true);

if (empty($event)) {
    echo '<div class="alert alert-danger">Kein Event geladen. Bitte zuerst im Dashboard ein Event auswählen.</div>';
    return;
}

// One-time: make sure the event URL column exists (MySQL 8 has no ADD COLUMN IF NOT EXISTS).
global $wpdb;
$has_event_url_col = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = %s
        AND COLUMN_NAME = 'str_event_url'",
    $wpdb->prefix . 'evtmgr_events'
));
if ($has_event_url_col === 0) {
    $wpdb->query(
        "ALTER TABLE {$wpdb->prefix}evtmgr_events
         ADD COLUMN str_event_url VARCHAR(255) NOT NULL DEFAULT '' AFTER dtm_registration_closed"
    );
}

// Fill wp_evtmgr_events.str_event_pdf_<lang> ("booklet-<slug of the event title>.pdf")
// where it is still empty – Evtmgr_Booklet::generate() takes the PDF file name from there.
if (method_exists($events_obj, 'event_update_booklet_pdf_filenames')) {
    $events_obj->event_update_booklet_pdf_filenames($event_uid);
}

$booklet = new Evtmgr_Booklet($event_uid, 'de');

// ── Actions ─────────────────────────────────────────────────────────────────

$generate_result = null;
$want_html_debug = false;

if (isset($_POST['booklet_generate_action'])) {
    $nonce = sanitize_text_field(wp_unslash($_POST['booklet_generate_nonce'] ?? ''));

    if (wp_verify_nonce($nonce, 'booklet_generate_action')) {
        $want_html_debug = !empty($_POST['booklet_html_debug']);
        $generate_result = $booklet->generate($want_html_debug);
    } else {
        $generate_result = array('ok' => false, 'error' => 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.');
    }
}

$str_event_url = trim((string) ($wpdb->get_var($wpdb->prepare(
    "SELECT str_event_url FROM {$wpdb->prefix}evtmgr_events WHERE event_uid = %s LIMIT 1",
    $event_uid
)) ?? ''));

$existing_pdfs = $booklet->existing_pdfs();
?>

<div class="container-fluid pt-3">
    <h2 class="mt-0">PDF Generierung – Booklet</h2>

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1"><strong>Event:</strong> <?php echo esc_html($event['str_event_name'] ?? ''); ?></p>
            <p class="mb-1"><strong>UID:</strong> <?php echo esc_html($event_uid); ?></p>
            <p class="mb-3">
                <strong>Event-URL:</strong>
                <?php if ($str_event_url !== '') : ?>
                    <?php echo esc_html($str_event_url); ?>
                <?php else : ?>
                    <span class="text-warning">nicht gesetzt – Feld <code>str_event_url</code> im Event-Formular pflegen
                    (QR-Code und Footer-Link bleiben sonst leer)</span>
                <?php endif; ?>
            </p>

            <?php if (is_array($generate_result)) : ?>
                <?php if (!empty($generate_result['ok'])) : ?>
                    <div class="alert alert-success">
                        <?php echo $want_html_debug ? 'HTML-Vorschau erstellt' : 'Booklet-PDF erstellt'; ?>
                        (<?php echo esc_html((string) $generate_result['page_count']); ?> Seiten<?php
                            echo $want_html_debug ? '' : ($generate_result['test_mode'] ? ', Testmodus' : ', Live-Modus'); ?>):
                        <a href="<?php echo esc_url($generate_result['url']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($generate_result['filename']); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <div class="alert alert-danger">
                        Fehler: <?php echo esc_html((string) ($generate_result['error'] ?: 'Unbekannter Fehler')); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" action="" id="booklet-generate-form" class="d-flex align-items-center gap-3 flex-wrap">
                <?php wp_nonce_field('booklet_generate_action', 'booklet_generate_nonce'); ?>
                <input type="hidden" name="booklet_generate_action" value="1">

                <button type="submit" name="booklet_generate" value="1"
                        id="booklet-generate-button" class="btn btn-primary">
                    <span class="booklet-btn-text">Booklet-PDF jetzt generieren</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none"
                          id="booklet-generate-spinner" role="status" aria-hidden="true"></span>
                </button>

                <label class="form-check-label d-inline-flex align-items-center gap-1">
                    <input type="checkbox" class="form-check-input mt-0" name="booklet_html_debug" value="1">
                    <span>nur HTML-Vorschau (kein PDF)</span>
                </label>
            </form>
        </div>
    </div>

    <?php if (!empty($existing_pdfs)) : ?>
        <div class="card mb-4">
            <div class="card-header">Bestehende Booklets</div>
            <div class="list-group list-group-flush">
                <?php foreach ($existing_pdfs as $file) : ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between gap-3"
                       href="<?php echo esc_url($file['file_url']); ?>" target="_blank" rel="noopener">
                        <span class="text-break"><?php echo esc_html($file['file_name']); ?></span>
                        <small class="text-muted text-nowrap">
                            <?php echo esc_html(wp_date('d.m.Y H:i', (int) $file['mtime'])); ?>
                            · <?php echo esc_html(size_format((int) $file['size'])); ?>
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form    = document.getElementById('booklet-generate-form');
    var button  = document.getElementById('booklet-generate-button');
    var spinner = document.getElementById('booklet-generate-spinner');

    if (form && button && spinner) {
        form.addEventListener('submit', function () {
            button.disabled = true;
            spinner.classList.remove('d-none');
        });
    }
});
</script>
