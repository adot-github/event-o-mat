
<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-evtmgr-global.php';

$global_obj = new Evtmgr_Global();
$events     = $global_obj->get_events_for_duplicate_dropdown();
$result     = null;
$errors     = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_action'])) {
    if (!isset($_POST['delete_event_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['delete_event_nonce'])), 'delete_event')) {
        $errors[] = 'Sicherheitsprüfung fehlgeschlagen.';
    } else {
        $event_uid = isset($_POST['event_uid']) ? sanitize_text_field(wp_unslash($_POST['event_uid'])) : '';
        $confirm   = isset($_POST['confirm_delete']) ? sanitize_text_field(wp_unslash($_POST['confirm_delete'])) : '';

        if ($event_uid === '') {
            $errors[] = 'Bitte wählen Sie ein Event aus.';
        }

        if ($confirm !== 'DELETE') {
            $errors[] = 'Bitte bestätigen Sie das Löschen mit DELETE.';
        }

        if (empty($errors)) {
            $result = $global_obj->delete_event_by_uid($event_uid);

            if (empty($result['success']) && !empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }

            /*
             * Refresh dropdown after successful deletion.
             */
            if (!empty($result['success'])) {
                $events = $global_obj->get_events_for_duplicate_dropdown();
            }
        }
    }
}
?>

<div class="container-xxl py-4">
    <h1 class="h3 mb-4">Event löschen</h1>

    <div class="alert alert-warning">
        <strong>Achtung:</strong> Diese Aktion löscht das gewählte Event und alle Datensätze,
        die über <code>fky_event_uid</code> mit diesem Event verbunden sind. Danach wird die
        Datenbank von verwaisten Datensätzen bereinigt.
    </div>

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
            Event wurde erfolgreich gelöscht:
            <strong><?php echo esc_html($result['event_uid']); ?></strong>
        </div>

        <div class="accordion mb-4" id="deleteEventSummary">
            <div class="accordion-item">
                <h2 class="accordion-header" id="deleteSummaryHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#deleteSummaryCollapse" aria-expanded="false" aria-controls="deleteSummaryCollapse">
                        Details anzeigen
                    </button>
                </h2>
                <div id="deleteSummaryCollapse" class="accordion-collapse collapse" aria-labelledby="deleteSummaryHeading">
                    <div class="accordion-body">
                        <pre class="mb-0"><?php echo esc_html(print_r($result, true)); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <div class="card-body">
            <?php wp_nonce_field('delete_event', 'delete_event_nonce'); ?>
            <input type="hidden" name="delete_event_action" value="1">

            <div class="mb-3">
                <label for="event_uid" class="form-label fw-semibold">Event auswählen</label>
                <select id="event_uid" name="event_uid" class="form-select" required>
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
                <label for="confirm_delete" class="form-label fw-semibold">Bestätigung</label>
                <input type="text" id="confirm_delete" name="confirm_delete" class="form-control" required>
                <div class="form-text">
                    Geben Sie <code>DELETE</code> ein, um das Löschen zu bestätigen.
                </div>
            </div>

            <button type="submit" class="btn btn-danger rounded-pill">
                Event endgültig löschen
            </button>
        </div>
    </form>
</div>
