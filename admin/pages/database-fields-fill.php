<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/../classes/class_database_fields.php';

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dbf_fill_action'])) {
    if (!isset($_POST['dbf_fill_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dbf_fill_nonce'])), 'dbf_fill')) {
        $errors[] = 'Sicherheitsprüfung fehlgeschlagen.';
    } else {
        $result = (new Evtmgr_Database_Fields())->sync_labels_from_reference();
    }
}
?>

<div class="container-xxl py-4">
    <h1 class="h3 mb-4">Database Fields: Labels befüllen</h1>

    <p class="text-muted mb-4">
        Liest <code>str_field_label_de</code> aus der Referenztabelle
        <code>wp_evtmgr_database_fields_labels</code> und trägt den Wert in
        <code>wp_evtmgr_database_fields</code> ein — nur für Zeilen, bei denen das Feld noch leer ist.
        Schlüssel: <code>str_frm_field_name</code>.
    </p>

    <?php if (!empty($errors)) : ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error) : ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($result !== null) : ?>
        <div class="alert alert-success">
            <strong><?php echo (int) $result['updated']; ?></strong> Labels übernommen &nbsp;·&nbsp;
            <strong><?php echo (int) $result['skipped']; ?></strong> bereits befüllt / kein Match
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <div class="card-body">
            <?php wp_nonce_field('dbf_fill', 'dbf_fill_nonce'); ?>
            <input type="hidden" name="dbf_fill_action" value="1">
            <button type="submit" class="btn btn-primary rounded-pill">
                Labels befüllen
            </button>
        </div>
    </form>
</div>
