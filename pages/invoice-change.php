<link rel='stylesheet' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/pages/assets/dashboard.css' media='all' />

<?php

$wp_load = dirname(__FILE__, 7) . '/wp-load.php';

if (file_exists($wp_load)) {
    require_once $wp_load;
}

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-event-registration.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-persons.php';

global $wpdb;

$prefix = isset($wpdb->prefix) && $wpdb->prefix !== '' ? $wpdb->prefix : 'wp_';

if (!defined('EVTMGR_PERSON_BILLING_PAGE_SLUG')) {
    define('EVTMGR_PERSON_BILLING_PAGE_SLUG', 'invoice-change');
}

$tables = array(
    'registrations_billing' => $prefix . 'evtmgr_registrations_billing',
    'event'              => $prefix . 'evtmgr_events',
);

function evtmgr_person_billing_value_ci($row, $key, $default = '') {
    $items = is_object($row) ? get_object_vars($row) : (is_array($row) ? $row : array());

    foreach ($items as $row_key => $value) {
        if (strcasecmp((string) $row_key, (string) $key) === 0) {
            return is_scalar($value) ? (string) $value : $default;
        }
    }

    return $default;
}

function evtmgr_person_billing_person_id($person) {
    foreach (array('id', 'id', 'person_id', 'id') as $key) {
        $value = evtmgr_person_billing_value_ci($person, $key);

        if ($value !== '') {
            return absint($value);
        }
    }

    return 0;
}

function evtmgr_person_billing_person_label($person) {
    $first_name = evtmgr_person_billing_value_ci($person, 'str_first_name');
    $last_name  = evtmgr_person_billing_value_ci($person, 'str_last_name');
    $zip        = evtmgr_person_billing_value_ci($person, 'str_zip');
    $city       = evtmgr_person_billing_value_ci($person, 'str_city');
    $email      = evtmgr_person_billing_value_ci($person, 'str_email');
    $person_id  = evtmgr_person_billing_person_id($person);

    $label = trim($first_name . ' ' . $last_name);
    $place = trim($zip . ' ' . $city);

    if ($place !== '') {
        $label .= ' — ' . $place;
    }

    if ($email !== '') {
        $label .= ' — ' . $email;
    }

    if ($person_id > 0) {
        $label .= ' (id ' . $person_id . ')';
    }

    return $label !== '' ? $label : 'Person id ' . $person_id;
}

function evtmgr_person_billing_get_person_by_id($persons, $person_id) {
    foreach ($persons as $person) {
        if (evtmgr_person_billing_person_id($person) === absint($person_id)) {
            return $person;
        }
    }

    return null;
}

function evtmgr_person_billing_admin_url($args = array()) {
    $args = array_merge(array('page' => EVTMGR_PERSON_BILLING_PAGE_SLUG), $args);

    return esc_url(add_query_arg($args, admin_url('admin.php')));
}

function evtmgr_person_billing_base_admin_url() {
    return admin_url('admin.php?page=' . rawurlencode(EVTMGR_PERSON_BILLING_PAGE_SLUG));
}

function evtmgr_person_billing_get_events_id($wpdb, $tables, $event_uid) {
    return absint($wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$tables['event']} WHERE event_uid = %s LIMIT 1",
            $event_uid
        )
    ));
}

function evtmgr_person_billing_get_rows($wpdb, $tables, $event_uid, $person_id) {
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT id, str_billing_text, int_price, fky_event_id
            FROM {$tables['registrations_billing']}
            WHERE fky_person_id = %d
              AND fky_event_uid = %s
            ORDER BY id
            ",
            $person_id,
            $event_uid
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : array();
}

function evtmgr_person_billing_parse_price($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $value = str_replace(array("'", ' '), '', $value);
    $value = str_replace(',', '.', $value);

    if (!is_numeric($value)) {
        return null;
    }

    return (int) round((float) $value);
}

try {
    $event_registration = new Event_Registration_Context();
    $event_uid = $event_registration->get_cookie_event_uid(true);

    $persons_obj = new class_evtmgr_persons();
    $persons = $persons_obj->get_persons_registered($event_uid);

    if (empty($persons)) {
        throw new RuntimeException('Keine registrierten Personen gefunden.');
    }

    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'select';
    ?>
    <div class="container py-3">
        <div class="row">
            <div class="col-12 col-xl-10">
    <?php

    if ($action === 'select') {
        ?>
        <h1 class="h3 mb-3">Kosten korrigieren</h1>
        <p class="text-muted mb-4">Wählen Sie zuerst eine teilnehmende Person aus.</p>

        <form method="get" action="<?php echo esc_url(evtmgr_person_billing_base_admin_url()); ?>" class="row gy-3 align-items-end">
            <input type="hidden" name="page" value="<?php echo esc_attr(EVTMGR_PERSON_BILLING_PAGE_SLUG); ?>">
            <input type="hidden" name="action" value="edit">

            <div class="col-12">
                <label for="person_id" class="form-label fw-semibold">Teilnehmende Person</label>
                <select id="person_id" name="person_id" class="form-select" required style="width:800px;max-width:100%;">
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($persons as $person) : ?>
                        <?php $person_id = evtmgr_person_billing_person_id($person); ?>
                        <?php if ($person_id > 0) : ?>
                            <option value="<?php echo esc_attr($person_id); ?>">
                                <?php echo esc_html(evtmgr_person_billing_person_label($person)); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary rounded-pill">Kosten anzeigen</button>
            </div>
        </form>
        <?php
    } elseif ($action === 'edit') {
        $person_id = isset($_REQUEST['person_id']) ? absint(wp_unslash($_REQUEST['person_id'])) : 0;
        $person = evtmgr_person_billing_get_person_by_id($persons, $person_id);

        if (!$person || $person_id <= 0) {
            throw new RuntimeException('Ungültige Person ausgewählt.');
        }

        $rows = evtmgr_person_billing_get_rows($wpdb, $tables, $event_uid, $person_id);
        $event_id = evtmgr_person_billing_get_events_id($wpdb, $tables, $event_uid);
        ?>
        <h1 class="h3 mb-3">Kosten korrigieren</h1>
        <h2 class="h5 mb-1"><?php echo esc_html(evtmgr_person_billing_person_label($person)); ?></h2>

        <?php if (empty($rows)) : ?>
            <div class="alert alert-info">Für diese Person sind keine Datensätze mit Kosten vorhanden.</div>
        <?php endif; ?>

        <form method="post" action="<?php echo evtmgr_person_billing_admin_url(); ?>">
            <?php wp_nonce_field('evtmgr_person_billing_save', 'evtmgr_person_billing_nonce'); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
            <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">

            <section class="mb-4">
                <h2 class="h5 mb-3">Vorhandene Kosten</h2>

                <div class="table-responsive">
                    <table class="table table-sm align-middle" style="max-width:900px;">
                        <thead>
                            <tr>
                                <th style="width:55%;">Text</th>
                                <th style="width:20%;">Preis</th>
                                <th style="width:25%;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row) : ?>
                                <?php $billing_id = absint($row['id'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="billing_id[]" value="<?php echo esc_attr($billing_id); ?>">
                                        <input type="text" name="str_billing_text_<?php echo esc_attr($billing_id); ?>" value="<?php echo esc_attr(trim((string) ($row['str_billing_text'] ?? ''))); ?>" class="form-control">
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">CHF</span>
                                            <input type="text" name="int_price_<?php echo esc_attr($billing_id); ?>" value="<?php echo esc_attr((string) ($row['int_price'] ?? '')); ?>" class="form-control" style="max-width:120px;">
                                        </div>
                                    </td>
                                    <td>
                                        <button type="submit" name="delete_billing_id" value="<?php echo esc_attr($billing_id); ?>" class="btn btn-outline-danger btn-sm rounded-pill" formnovalidate>löschen</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td>
                                    <input type="text" name="str_billing_text_0" value="" class="form-control" placeholder="Neue Kostenposition">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">CHF</span>
                                        <input type="text" name="int_price_0" value="" class="form-control" style="max-width:120px;">
                                    </div>
                                </td>
                                <td><span class="badge text-bg-secondary">NEU</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <button type="submit" class="btn btn-primary rounded-pill">Kosten aktualisieren</button>
            <a href="<?php echo evtmgr_person_billing_admin_url(array('action' => 'select')); ?>" class="btn btn-outline-secondary rounded-pill">Andere Person wählen</a>
        </form>
        <?php
    } elseif ($action === 'save') {
        if (!isset($_POST['evtmgr_person_billing_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['evtmgr_person_billing_nonce'])), 'evtmgr_person_billing_save')) {
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen.');
        }

        $person_id = isset($_POST['person_id']) ? absint(wp_unslash($_POST['person_id'])) : 0;
        $person = evtmgr_person_billing_get_person_by_id($persons, $person_id);

        if (!$person || $person_id <= 0) {
            throw new RuntimeException('Ungültige Person ausgewählt.');
        }

        $event_id = isset($_POST['event_id']) ? absint(wp_unslash($_POST['event_id'])) : 0;
        if ($event_id <= 0) {
            $event_id = evtmgr_person_billing_get_events_id($wpdb, $tables, $event_uid);
        }

        $deleted_count = 0;
        $updated_count = 0;
        $inserted_count = 0;

        $wpdb->query('START TRANSACTION');

        $delete_id = isset($_POST['delete_billing_id']) ? absint(wp_unslash($_POST['delete_billing_id'])) : 0;
        if ($delete_id > 0) {
            $deleted = $wpdb->delete(
                $tables['registrations_billing'],
                array(
                    'id' => $delete_id,
                    'fky_person_id'  => $person_id,
                    'fky_event_uid'   => $event_uid,
                ),
                array('%d', '%d', '%s')
            );

            if ($deleted === false) {
                throw new RuntimeException('Löschen fehlgeschlagen: ' . $wpdb->last_error);
            }

            $deleted_count = (int) $deleted;
        } else {
            $billing_ids = isset($_POST['billing_id']) ? wp_unslash($_POST['billing_id']) : array();
            if (!is_array($billing_ids)) {
                $billing_ids = array($billing_ids);
            }

            foreach (array_unique(array_filter(array_map('absint', $billing_ids))) as $billing_id) {
                $text = isset($_POST['str_billing_text_' . $billing_id]) ? sanitize_text_field(wp_unslash($_POST['str_billing_text_' . $billing_id])) : '';
                $price = isset($_POST['int_price_' . $billing_id]) ? evtmgr_person_billing_parse_price(wp_unslash($_POST['int_price_' . $billing_id])) : null;

                if ($price === null) {
                    $price = 0;
                }

                $updated = $wpdb->update(
                    $tables['registrations_billing'],
                    array(
                        'str_billing_text' => $text,
                        'int_price'       => $price,
                    ),
                    array(
                        'id' => $billing_id,
                        'fky_person_id'  => $person_id,
                        'fky_event_uid'   => $event_uid,
                    ),
                    array('%s', '%d'),
                    array('%d', '%d', '%s')
                );

                if ($updated === false) {
                    throw new RuntimeException('Aktualisierung fehlgeschlagen: ' . $wpdb->last_error);
                }

                $updated_count += (int) $updated;
            }

            $new_text = isset($_POST['str_billing_text_0']) ? sanitize_text_field(wp_unslash($_POST['str_billing_text_0'])) : '';
            $new_price = isset($_POST['int_price_0']) ? evtmgr_person_billing_parse_price(wp_unslash($_POST['int_price_0'])) : null;

            if (trim($new_text) !== '' && $new_price !== null) {
                if ($event_id <= 0) {
                    throw new RuntimeException('Kein Kongress für Event Uid gefunden: ' . $event_uid);
                }

                $inserted = $wpdb->insert(
                    $tables['registrations_billing'],
                    array(
                        'str_billing_text' => $new_text,
                        'int_price'       => $new_price,
                        'fky_person_id'    => $person_id,
                        'fky_event_id'  => $event_id,
                        'fky_event_uid'     => $event_uid,
                    ),
                    array('%s', '%d', '%d', '%d', '%s')
                );

                if ($inserted === false) {
                    throw new RuntimeException('Neue Kostenposition konnte nicht gespeichert werden: ' . $wpdb->last_error);
                }

                $inserted_count = 1;
            }
        }

        $wpdb->query('COMMIT');
        ?>
        <div class="alert alert-success">
            <h1 class="h4 alert-heading">Kosten wurden aktualisiert.</h1>
            <p class="mb-0">
                <?php echo esc_html((string) $updated_count); ?> aktualisiert,
                <?php echo esc_html((string) $inserted_count); ?> neu erstellt,
                <?php echo esc_html((string) $deleted_count); ?> gelöscht.
            </p>
        </div>

        <a href="<?php echo evtmgr_person_billing_admin_url(array('action' => 'edit', 'person_id' => $person_id)); ?>" class="btn btn-primary rounded-pill">Kosten erneut bearbeiten</a>
        <a href="<?php echo evtmgr_person_billing_admin_url(array('action' => 'select')); ?>" class="btn btn-outline-secondary rounded-pill">Andere Person wählen</a>
        <?php
    } else {
        throw new RuntimeException('Unbekannte Aktion.');
    }

    ?>
            </div>
        </div>
    </div>
    <?php
} catch (Throwable $e) {
    if (isset($wpdb)) {
        $wpdb->query('ROLLBACK');
    }
    ?>
    <div class="container py-3">
        <div class="row">
            <div class="col-12 col-xl-10">
                <div class="alert alert-danger">
                    <h1 class="h4 alert-heading">Fehler</h1>
                    <p class="mb-0"><?php echo esc_html($e->getMessage()); ?></p>
                </div>
                <a href="<?php echo evtmgr_person_billing_admin_url(array('action' => 'select')); ?>" class="btn btn-secondary rounded-pill">Zurück</a>
            </div>
        </div>
    </div>
    <?php
}
