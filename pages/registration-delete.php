<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-event-registration.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-persons.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-global.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-workshops.php';

if (!defined('EVTMGR_REGISTRATION_DELETE_PAGE_SLUG')) {
    define('EVTMGR_REGISTRATION_DELETE_PAGE_SLUG', 'registration-delete');
}

function evtmgr_registration_delete_value_ci($row, $key, $default = '') {
    if (is_array($row)) {
        foreach ($row as $row_key => $value) {
            if (strcasecmp((string) $row_key, (string) $key) === 0) {
                return is_scalar($value) ? (string) $value : $default;
            }
        }
    } elseif (is_object($row)) {
        foreach (get_object_vars($row) as $row_key => $value) {
            if (strcasecmp((string) $row_key, (string) $key) === 0) {
                return is_scalar($value) ? (string) $value : $default;
            }
        }
    }

    return $default;
}

function evtmgr_registration_delete_person_id($person) {
    return absint(evtmgr_registration_delete_value_ci($person, 'id'));
}

function evtmgr_registration_delete_person_label($person) {
    $first_name = evtmgr_registration_delete_value_ci($person, 'str_first_name');
    $last_name  = evtmgr_registration_delete_value_ci($person, 'str_last_name');
    $zip        = evtmgr_registration_delete_value_ci($person, 'str_zip');
    $city       = evtmgr_registration_delete_value_ci($person, 'str_city');
    $email      = evtmgr_registration_delete_value_ci($person, 'str_email');
    $person_id  = evtmgr_registration_delete_person_id($person);

    $label = trim($first_name . ' ' . $last_name);
    $place = trim($zip . ' ' . $city);

    if ($place !== '') {
        $label .= ' — ' . $place;
    }

    if ($email !== '') {
        $label .= ' — ' . $email;
    }

    if ($person_id > 0) {
        $label .= ' (ID ' . $person_id . ')';
    }

    return $label !== '' ? $label : 'Person ID ' . $person_id;
}

function evtmgr_registration_delete_sync_workshops($event_uid) {
    $event_uid = sanitize_text_field((string) $event_uid);

    if ($event_uid === '') {
        return array(
            'success' => false,
            'checked' => 0,
            'updated' => 0,
            'errors'  => array('event_uid is empty.'),
        );
    }

    if (class_exists('Evtmgr_Workshops')) {
        $workshops_obj = new Evtmgr_Workshops();

        if (method_exists($workshops_obj, 'sync_registrations')) {
            return $workshops_obj->sync_registrations($event_uid);
        }
    }
    return array(
        'success' => false,
        'checked' => 0,
        'updated' => 0,
        'errors'  => array('sync_registrations() is not available.'),
    );
}

$event_registration = new Event_Registration_Context();
$event_uid = $event_registration->get_cookie_event_uid(true);

$persons_obj = new class_evtmgr_persons();
$global_obj  = new Evtmgr_Global();

$persons = $persons_obj->get_persons_registered($event_uid);
$result = null;
$sync_result = null;
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_registration_action'])) {
    if (!isset($_POST['delete_registration_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['delete_registration_nonce'])), 'delete_registration')) {
        $errors[] = 'Sicherheitsprüfung fehlgeschlagen.';
    } else {
        $person_id = isset($_POST['person_id']) ? absint(wp_unslash($_POST['person_id'])) : 0;
        $confirm   = isset($_POST['confirm_delete']) ? sanitize_text_field(wp_unslash($_POST['confirm_delete'])) : '';

        if ($person_id <= 0) {
            $errors[] = 'Bitte wählen Sie eine Anmeldung aus.';
        }

        if ($confirm !== 'DELETE') {
            $errors[] = 'Bitte bestätigen Sie das Löschen mit DELETE.';
        }

        if (empty($errors)) {
            $result = $global_obj->delete_registration_by_person_id($person_id, $event_uid);

            if (empty($result['success']) && !empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }

            if (!empty($result['success'])) {
                $sync_result = evtmgr_registration_delete_sync_workshops($event_uid);

                if (empty($sync_result['success']) && !empty($sync_result['errors'])) {
                    $errors[] = 'Workshop-Anmeldezahlen konnten nicht synchronisiert werden.';
                }

                $persons = $persons_obj->get_persons_registered($event_uid);
            }
        }
    }
}
?>

<div class="container-xxl py-4">
    <h1 class="h3 mb-4">Anmeldung löschen</h1>

    <div class="alert alert-warning">
        <strong>Achtung:</strong> Diese Aktion löscht die gewählte Person aus <code>wp_evtmgr_persons</code>.
        Danach wird <code>clean_database()</code> ausgeführt, damit verknüpfte Registrierungs-, Rechnungs- und Workshop-Datensätze entfernt werden.
        Zum Schluss werden die Workshop-Anmeldezahlen synchronisiert.
    </div>

    <p class="text-muted">
        Aktueller Event:
        <strong><?php echo esc_html($event_uid); ?></strong>
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

    <?php if (!empty($result['success'])) : ?>
        <div class="alert alert-success">
            Anmeldung wurde erfolgreich gelöscht:
            <strong><?php echo esc_html(trim(($result['person']['str_first_name'] ?? '') . ' ' . ($result['person']['str_last_name'] ?? ''))); ?></strong>
            <?php if (!empty($result['person_id'])) : ?>
                <span class="text-muted">(ID <?php echo esc_html((string) $result['person_id']); ?>)</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($sync_result)) : ?>
            <?php if (!empty($sync_result['success'])) : ?>
                <div class="alert alert-info">
                    Workshop-Anmeldezahlen wurden synchronisiert.
                    Aktualisiert: <?php echo esc_html((string) ($sync_result['updated'] ?? 0)); ?>
                </div>
            <?php else : ?>
                <div class="alert alert-warning">
                    Die Anmeldung wurde gelöscht, aber die Workshop-Anmeldezahlen konnten nicht synchronisiert werden.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="accordion mb-4" id="deleteRegistrationSummary">
            <div class="accordion-item">
                <h2 class="accordion-header" id="deleteRegistrationSummaryHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#deleteRegistrationSummaryCollapse" aria-expanded="false" aria-controls="deleteRegistrationSummaryCollapse">
                        Details anzeigen
                    </button>
                </h2>
                <div id="deleteRegistrationSummaryCollapse" class="accordion-collapse collapse" aria-labelledby="deleteRegistrationSummaryHeading">
                    <div class="accordion-body">
                        <h3 class="h6">Löschresultat</h3>
                        <pre><?php echo esc_html(print_r($result, true)); ?></pre>

                        <h3 class="h6">Synchronisierung</h3>
                        <pre class="mb-0"><?php echo esc_html(print_r($sync_result, true)); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($persons)) : ?>
        <div class="alert alert-warning">
            Für den aktuellen Event wurden keine angemeldeten Personen gefunden.
        </div>
    <?php else : ?>
        <form method="post" class="card shadow-sm">
            <div class="card-body">
                <?php wp_nonce_field('delete_registration', 'delete_registration_nonce'); ?>
                <input type="hidden" name="delete_registration_action" value="1">

                <div class="mb-3">
                    <label for="person_id" class="form-label fw-semibold">Anmeldung auswählen</label>
                    <select id="person_id" name="person_id" class="form-select" required style="width:800px; max-width:100%;">
                        <option value="">Bitte auswählen</option>
                        <?php foreach ($persons as $person) : ?>
                            <?php $person_id = evtmgr_registration_delete_person_id($person); ?>
                            <?php if ($person_id > 0) : ?>
                                <option value="<?php echo esc_attr($person_id); ?>">
                                    <?php echo esc_html(evtmgr_registration_delete_person_label($person)); ?>
                                </option>
                            <?php endif; ?>
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

                <button type="submit" class="btn btn-danger">
                    Anmeldung endgültig löschen
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
