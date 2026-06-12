
<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-evtmgr-global.php';

$global_obj = new Evtmgr_Global();
$events     = $global_obj->get_events_for_duplicate_dropdown();
$result     = null;
$errors     = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_event_action'])) {
    if (!isset($_POST['duplicate_event_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['duplicate_event_nonce'])), 'duplicate_event')) {
        $errors[] = 'Sicherheitsprüfung fehlgeschlagen.';
    } else {
        $old_event_uid = isset($_POST['old_event_uid']) ? sanitize_text_field(wp_unslash($_POST['old_event_uid'])) : '';
        $new_event_uid = isset($_POST['new_event_uid']) ? sanitize_text_field(wp_unslash($_POST['new_event_uid'])) : '';

        $result = $global_obj->duplicate_event($old_event_uid, $new_event_uid);

        if (empty($result['success']) && !empty($result['errors'])) {
            $errors = array_merge($errors, $result['errors']);
        }
    }
}
?>

<div class="container-xxl py-4">
    <h1 class="h3 mb-4">Event duplizieren</h1>

    <?php if (!empty($errors)) : ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error) : ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($result['success'])) : ?>
        <div class="alert alert-success">
            Event wurde erfolgreich dupliziert:<br>
            <strong><?php echo esc_html($result['old_event_uid']); ?></strong>
            →
            <strong><?php echo esc_html($result['new_event_uid']); ?></strong>
        </div>

        <div class="accordion mb-4" id="duplicateEventSummary">
            <div class="accordion-item">
                <h2 class="accordion-header" id="duplicateSummaryHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#duplicateSummaryCollapse" aria-expanded="false" aria-controls="duplicateSummaryCollapse">
                        Details anzeigen
                    </button>
                </h2>
                <div id="duplicateSummaryCollapse" class="accordion-collapse collapse" aria-labelledby="duplicateSummaryHeading">
                    <div class="accordion-body">
                        <pre class="mb-0"><?php echo esc_html(print_r($result, true)); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <div class="card-body">
            <?php wp_nonce_field('duplicate_event', 'duplicate_event_nonce'); ?>
            <input type="hidden" name="duplicate_event_action" value="1">

            <div class="mb-3">
                <label for="old_event_uid" class="form-label fw-semibold">Bestehendes Event</label>
                <select id="old_event_uid" name="old_event_uid" class="form-select" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($events as $event) : ?>
                        <?php
                        $uid   = $event['event_uid'] ?? '';
                        $title = $event['str_event_name'] ?? '';
                        ?>
                        <option value="<?php echo esc_attr($uid); ?>">
                            <?php echo esc_html(trim($uid . ' — ' . $title, ' —')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="new_event_uid" class="form-label fw-semibold">Neue Event UID</label>
                <input type="text" id="new_event_uid" name="new_event_uid" class="form-control" required>
                <div class="form-text">Diese UID wird als neuer <code>event_uid</code> und als neue <code>fky_event_uid</code> in den kopierten Datensätzen verwendet.</div>
            </div>

            <button type="submit" class="btn btn-primary rounded-pill">Event duplizieren</button>
        </div>
    </form>
</div>
