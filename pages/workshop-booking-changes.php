<link rel='stylesheet' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/pages/assets/dashboard.css' media='all' />

<?php

$wp_load = dirname(__FILE__, 7) . '/wp-load.php';

if (!file_exists($wp_load)) {
    die('wp-load.php not found: ' . htmlspecialchars($wp_load, ENT_QUOTES, 'UTF-8'));
}

require_once $wp_load;

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-event-registration.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-persons.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-workshops.php';

global $wpdb;

$prefix = isset($wpdb->prefix) && $wpdb->prefix !== '' ? $wpdb->prefix : 'wp_';

if (!defined('EVTMGR_WORKSHOP_BOOKINGS_PAGE_SLUG')) {
    define('EVTMGR_WORKSHOP_BOOKINGS_PAGE_SLUG', 'workshop-booking-changes');
}

$tables = array(
    'persons'                 => $prefix . 'evtmgr_persons',
    'registrations'           => $prefix . 'evtmgr_registrations',
    'workshops'               => $prefix . 'evtmgr_workshops',
    'timezones'               => $prefix . 'evtmgr_timezones',
    'registrations_workshops' => $prefix . 'evtmgr_registrations_workshops',
    'event'                => $prefix . 'evtmgr_events',
);

function evtmgr_workshop_bookings_value_ci($row, $key, $default = '') {
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

function evtmgr_workshop_bookings_person_id($person) {
    $candidates = array('id', 'id', 'person_id', 'id');

    foreach ($candidates as $candidate) {
        $value = evtmgr_workshop_bookings_value_ci($person, $candidate);

        if ($value !== '') {
            return absint($value);
        }
    }

    return 0;
}

function evtmgr_workshop_bookings_person_label($person) {
    $first_name = evtmgr_workshop_bookings_value_ci($person, 'str_first_name');
    $last_name  = evtmgr_workshop_bookings_value_ci($person, 'str_last_name');
    $zip        = evtmgr_workshop_bookings_value_ci($person, 'str_zip');
    $city       = evtmgr_workshop_bookings_value_ci($person, 'str_city');
    $email      = evtmgr_workshop_bookings_value_ci($person, 'str_email');
    $person_id  = evtmgr_workshop_bookings_person_id($person);

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

function evtmgr_workshop_bookings_page_slug() {
    $page = isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : '';

    return $page !== '' ? $page : EVTMGR_WORKSHOP_BOOKINGS_PAGE_SLUG;
}

function evtmgr_workshop_bookings_base_admin_url() {
    return admin_url('admin.php?page=' . rawurlencode(EVTMGR_WORKSHOP_BOOKINGS_PAGE_SLUG));
}

function evtmgr_workshop_bookings_admin_url($args = array()) {
    $args = array_merge(
        array('page' => EVTMGR_WORKSHOP_BOOKINGS_PAGE_SLUG),
        $args
    );

    return esc_url(add_query_arg($args, admin_url('admin.php')));
}

function evtmgr_workshop_bookings_current_url($args = array()) {
    return evtmgr_workshop_bookings_admin_url($args);
}

function evtmgr_workshop_bookings_get_person_by_id($persons, $person_id) {
    foreach ($persons as $person) {
        if (evtmgr_workshop_bookings_person_id($person) === absint($person_id)) {
            return $person;
        }
    }

    return null;
}

function evtmgr_workshop_bookings_has_valid_registration($wpdb, $tables, $event_uid, $person_id) {
    $sql = "
        SELECT COUNT(*)
        FROM {$tables['registrations']}
        WHERE fky_person_id = %d
          AND fky_event_uid = %s
    ";

    return (int) $wpdb->get_var($wpdb->prepare($sql, $person_id, $event_uid)) > 0;
}

function evtmgr_workshop_bookings_get_current_bookings($wpdb, $tables, $event_uid, $person_id) {
    $sql = "
        SELECT
            rw.id AS registration_workshop_id,
            rw.fky_workshop_id,
            rw.fky_timezone_id,
            ws.id AS workshop_id,
            ws.str_workshop_number,
            ws.str_workshop_title_de,
            ws.fky_timezone_id AS workshop_timezone_id,
            tz.id AS timezone_id,
            tz.str_timezone_name_de,
            tz.dtm_day,
            tz.dtm_time_from,
            tz.int_sort_order
        FROM {$tables['registrations_workshops']} AS rw
        INNER JOIN {$tables['workshops']} AS ws
            ON ws.id = rw.fky_workshop_id
        INNER JOIN {$tables['timezones']} AS tz
            ON tz.id = ws.fky_timezone_id
        WHERE rw.fky_person_id = %d
          AND ws.fky_event_uid = %s
          AND ws.ysn_no_registration_possible = 0
        ORDER BY tz.int_sort_order, ws.str_workshop_number
    ";

    $rows = $wpdb->get_results(
        $wpdb->prepare($sql, $person_id, $event_uid),
        ARRAY_A
    );

    return is_array($rows) ? $rows : array();
}

function evtmgr_workshop_bookings_get_available_workshops($wpdb, $tables, $event_uid, $booked_workshop_ids) {
    $params = array($event_uid);
    $not_in_sql = '';

    $booked_workshop_ids = array_values(array_unique(array_filter(array_map('absint', $booked_workshop_ids))));

    if (!empty($booked_workshop_ids)) {
        $placeholders = implode(',', array_fill(0, count($booked_workshop_ids), '%d'));
        $not_in_sql = " AND w.id NOT IN ($placeholders)";
        $params = array_merge($params, $booked_workshop_ids);
    }

    $sql = "
        SELECT
            w.id AS workshop_id,
            w.str_workshop_title_de,
            w.str_workshop_number,
            w.int_max_number_of_registrations,
            tz.id AS timezone_id,
            tz.str_timezone_name_de,
            tz.dtm_time_from,
            tz.int_sort_order,
            w.int_number_of_registrations
        FROM {$tables['workshops']} AS w
        INNER JOIN {$tables['timezones']} AS tz
            ON tz.id = w.fky_timezone_id
        WHERE w.fky_event_uid = %s
        AND w.ysn_no_registration_possible = 0
        {$not_in_sql}
        ORDER BY tz.int_sort_order, w.str_workshop_number
    ";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

    return is_array($rows) ? $rows : array();
}

function evtmgr_workshop_bookings_get_events_id($wpdb, $tables, $event_uid) {
    $sql = "
        SELECT id
        FROM {$tables['event']}
        WHERE event_uid = %s
        LIMIT 1
    ";

    return absint($wpdb->get_var($wpdb->prepare($sql, $event_uid)));
}

function evtmgr_workshop_bookings_format_date($value) {
    if ($value === '' || $value === null) {
        return '';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : (string) $value;
}

function evtmgr_workshop_bookings_format_time($value) {
    if ($value === '' || $value === null) {
        return 'Keine korrekte Zeit';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date_i18n(get_option('time_format'), $timestamp) : 'Keine korrekte Zeit';
}

function evtmgr_workshop_bookings_workshop_label($row, $include_date_time = true) {
    $label = trim((string) ($row['str_workshop_number'] ?? '') . ' ' . (string) ($row['str_workshop_title_de'] ?? ''));

    if ($include_date_time) {
        $date = evtmgr_workshop_bookings_format_date($row['dtm_day'] ?? '');
        $time = evtmgr_workshop_bookings_format_time($row['dtm_time_from'] ?? '');

        $meta = trim($date . ', ' . $time, ' ,');

        if ($meta !== '') {
            $label .= ' — ' . $meta;
        }
    }

    return $label;
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

    if ($action === 'select') {
        ?>
<div class="container py-4">
    <div class="row">
        <div class="col-12">
<?php
        ?>
        <h1 class="h3 mb-3">Umbuchungen vornehmen</h1>
        <p class="text-muted mb-4">Wählen Sie zuerst eine teilnehmende Person aus.</p>

        <form method="get" action="<?php echo esc_url(evtmgr_workshop_bookings_base_admin_url()); ?>" class="row gy-3">
                    <input type="hidden" name="page" value="<?php echo esc_attr(EVTMGR_WORKSHOP_BOOKINGS_PAGE_SLUG); ?>">
                    <input type="hidden" name="action" value="edit">
                    <div class="col-12">
                        <label for="person_id" class="form-label fw-semibold">Teilnehmende Person</label>

                        <select id="person_id" name="person_id" class="form-select" required style="width:800px; max-width:100%;">
                            <option value="">Bitte auswählen</option>
                            <?php foreach ($persons as $person) : ?>
                                <?php $person_id = evtmgr_workshop_bookings_person_id($person); ?>
                                <?php if ($person_id > 0) : ?>
                                    <option value="<?php echo esc_attr($person_id); ?>">
                                        <?php echo esc_html(evtmgr_workshop_bookings_person_label($person)); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary rounded-pill">Buchungen anzeigen</button>
                    </div>
        </form>
        <?php
        ?>
        </div>
    </div>
</div>
<?php
        return;
    }

    if ($action === 'edit') {
        $person_id = isset($_REQUEST['person_id']) ? absint(wp_unslash($_REQUEST['person_id'])) : 0;
        $person = evtmgr_workshop_bookings_get_person_by_id($persons, $person_id);

        if (!$person || $person_id <= 0) {
            throw new RuntimeException('Ungültige Person ausgewählt.');
        }


        $current_bookings = evtmgr_workshop_bookings_get_current_bookings($wpdb, $tables, $event_uid, $person_id);
        $booked_ids = array_map('absint', wp_list_pluck($current_bookings, 'workshop_id'));
        $available_workshops = evtmgr_workshop_bookings_get_available_workshops($wpdb, $tables, $event_uid, $booked_ids);

        ?>
<div class="container py-4">
    <div class="row">
        <div class="col-12">
<?php
        ?>
        <h1 class="h3 mb-3">Umbuchungen vornehmen</h1>
        <section class="mb-4">
            <h2 class="h5 mb-1"><?php echo esc_html(evtmgr_workshop_bookings_person_label($person)); ?></h2>
            <div class="text-muted">Event Uid: <?php echo esc_html($event_uid); ?></div>
        </section>

        <form method="post" action="<?php echo evtmgr_workshop_bookings_admin_url(); ?>" class="mb-4">
            <?php wp_nonce_field('evtmgr_workshop_bookings_review', 'evtmgr_workshop_bookings_nonce'); ?>
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">

            <section class="mb-4">
                <h2 class="h5 mb-3">Vorhandene Anmeldungen</h2>
                    <?php if (empty($current_bookings)) : ?>
                        <p class="text-muted mb-0">Keine bestehenden Workshop-Anmeldungen gefunden.</p>
                    <?php else : ?>
                        <?php foreach ($current_bookings as $booking) : ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="ws_chosen[]" value="<?php echo esc_attr(absint($booking['workshop_id'])); ?>" id="current_ws_<?php echo esc_attr(absint($booking['workshop_id'])); ?>" checked>
                                <label class="form-check-label" for="current_ws_<?php echo esc_attr(absint($booking['workshop_id'])); ?>">
                                    <?php echo esc_html(evtmgr_workshop_bookings_workshop_label($booking)); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
            </section>

            <section class="mb-4">
                <h2 class="h5 mb-3">Neue Anmeldungen</h2>
                    <?php if (empty($available_workshops)) : ?>
                        <p class="text-muted mb-0">Keine weiteren Workshops verfügbar.</p>
                    <?php else : ?>
                        <?php $current_group = null; ?>
                        <?php foreach ($available_workshops as $workshop) : ?>
                            <?php
                            $group = (string) ($workshop['str_timezone_name_de'] ?? 'Ohne Zeitzone') . ' | ' . evtmgr_workshop_bookings_format_time($workshop['dtm_time_from'] ?? '');
                            $workshop_id = absint($workshop['workshop_id'] ?? 0);
                            $timezone_id = absint($workshop['timezone_id'] ?? 0);
                            $max = isset($workshop['int_max_number_of_registrations']) ? (int) $workshop['int_max_number_of_registrations'] : 0;
                            $count = isset($workshop['int_number_of_registrations']) ? (int) $workshop['int_number_of_registrations'] : 0;
                            $is_full = $max > 0 && $count >= $max;
                            ?>
                            <?php if ($group !== $current_group) : ?>
                                <?php if ($current_group !== null) : ?></div><?php endif; ?>
                                <h3 class="h6 mt-3 mb-2 border-top pt-3"><?php echo esc_html($group); ?></h3>
                                <div class="ps-1">
                                <?php $current_group = $group; ?>
                            <?php endif; ?>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="ws_not_chosen[]" value="<?php echo esc_attr($workshop_id . '_' . $timezone_id); ?>" id="new_ws_<?php echo esc_attr($workshop_id . '_' . $timezone_id); ?>">
                                <label class="form-check-label" for="new_ws_<?php echo esc_attr($workshop_id . '_' . $timezone_id); ?>">
                                    <?php echo esc_html(trim((string) ($workshop['str_workshop_number'] ?? '') . ' ' . (string) ($workshop['str_workshop_title_de'] ?? ''))); ?>
                                    <span class="badge rounded-pill <?php echo $is_full ? 'text-bg-danger' : 'text-bg-success'; ?> ms-2">Plätze: <?php echo esc_html((string) $max); ?> Anmeldungen: <?php echo esc_html((string) $count); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($current_group !== null) : ?></div><?php endif; ?>
                    <?php endif; ?>
            </section>

            <button type="submit" class="btn btn-primary rounded-pill">Umbuchung prüfen</button>
            <a href="<?php echo evtmgr_workshop_bookings_current_url(array('action' => 'select')); ?>" class="btn btn-outline-secondary rounded-pill">Andere Person wählen</a>
        </form>
        <?php
        ?>
        </div>
    </div>
</div>
<?php
        return;
    }

    if ($action === 'review') {
        if (!isset($_POST['evtmgr_workshop_bookings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['evtmgr_workshop_bookings_nonce'])), 'evtmgr_workshop_bookings_review')) {
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen.');
        }

        $person_id = isset($_POST['person_id']) ? absint(wp_unslash($_POST['person_id'])) : 0;
        $person = evtmgr_workshop_bookings_get_person_by_id($persons, $person_id);

        if (!$person || $person_id <= 0) {
            throw new RuntimeException('Ungültige Person ausgewählt.');
        }

        $current_bookings = evtmgr_workshop_bookings_get_current_bookings($wpdb, $tables, $event_uid, $person_id);
        $current_ids = array_values(array_unique(array_filter(array_map('absint', wp_list_pluck($current_bookings, 'workshop_id'))))); 

        $chosen_ids = isset($_POST['ws_chosen']) ? wp_unslash($_POST['ws_chosen']) : array();
        if (!is_array($chosen_ids)) {
            $chosen_ids = array($chosen_ids);
        }
        $chosen_ids = array_values(array_unique(array_filter(array_map('absint', $chosen_ids))));

        $delete_ids = array_values(array_diff($current_ids, $chosen_ids));

        $add_values = isset($_POST['ws_not_chosen']) ? wp_unslash($_POST['ws_not_chosen']) : array();
        if (!is_array($add_values)) {
            $add_values = array($add_values);
        }

        $add_pairs = array();
        foreach ($add_values as $add_value) {
            $parts = explode('_', sanitize_text_field((string) $add_value));
            $workshop_id = isset($parts[0]) ? absint($parts[0]) : 0;
            $timezone_id = isset($parts[1]) ? absint($parts[1]) : 0;

            if ($workshop_id > 0 && $timezone_id > 0) {
                $add_pairs[$workshop_id . '_' . $timezone_id] = array(
                    'workshop_id' => $workshop_id,
                    'timezone_id' => $timezone_id,
                );
            }
        }
        $add_pairs = array_values($add_pairs);

        $delete_rows = array();
        if (!empty($delete_ids)) {
            foreach ($current_bookings as $booking) {
                if (in_array(absint($booking['workshop_id']), $delete_ids, true)) {
                    $delete_rows[] = $booking;
                }
            }
        }

        $add_rows = array();
        if (!empty($add_pairs)) {
            $or_parts = array();
            $params = array($event_uid);
            foreach ($add_pairs as $pair) {
                $or_parts[] = '(w.id = %d AND tz.id = %d)';
                $params[] = $pair['workshop_id'];
                $params[] = $pair['timezone_id'];
            }

            $sql = "
                SELECT
                    w.id AS workshop_id,
                    w.str_workshop_title_de,
                    w.str_workshop_number,
                    w.int_max_number_of_registrations,
                    tz.id AS timezone_id,
                    tz.str_timezone_name_de,
                    tz.dtm_time_from,
                    tz.int_sort_order,
                    w.int_number_of_registrations
                FROM {$tables['workshops']} AS w
                INNER JOIN {$tables['timezones']} AS tz
                    ON tz.id = w.fky_timezone_id
                WHERE w.fky_event_uid = %s
                  AND (" . implode(' OR ', $or_parts) . ")
                ORDER BY tz.int_sort_order, w.str_workshop_number
            ";

            $add_rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
            $add_rows = is_array($add_rows) ? $add_rows : array();
        }

        ?>
<div class="container py-4">
    <div class="row">
        <div class="col-12">
<?php
        ?>
        <h1 class="h3 mb-3">Umbuchung prüfen</h1>
        <section class="mb-4">
            <h2 class="h5 mb-1"><?php echo esc_html(evtmgr_workshop_bookings_person_label($person)); ?></h2>
            <div class="text-muted">Event Uid: <?php echo esc_html($event_uid); ?></div>
        </section>

        <form method="post" action="<?php echo evtmgr_workshop_bookings_admin_url(); ?>">
            <?php wp_nonce_field('evtmgr_workshop_bookings_apply', 'evtmgr_workshop_bookings_nonce'); ?>
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
            <?php foreach ($delete_ids as $delete_id) : ?>
                <input type="hidden" name="ws_delete[]" value="<?php echo esc_attr($delete_id); ?>">
            <?php endforeach; ?>
            <?php foreach ($add_pairs as $pair) : ?>
                <input type="hidden" name="ws_add[]" value="<?php echo esc_attr($pair['workshop_id'] . '_' . $pair['timezone_id']); ?>">
            <?php endforeach; ?>

            <section class="mb-4">
                <h2 class="h5 mb-3">Folgende Anmeldungen werden gelöscht</h2>
                    <?php if (empty($delete_rows)) : ?>
                        <p class="text-muted mb-0">Keine Löschungen.</p>
                    <?php else : ?>
                        <ul class="mb-0">
                            <?php foreach ($delete_rows as $row) : ?>
                                <li><?php echo esc_html(evtmgr_workshop_bookings_workshop_label($row)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
            </section>

            <section class="mb-4">
                <h2 class="h5 mb-3">Folgende Anmeldungen werden neu gebucht</h2>
                    <?php if (empty($add_rows)) : ?>
                        <p class="text-muted mb-0">Keine neuen Buchungen.</p>
                    <?php else : ?>
                        <ul class="mb-0">
                            <?php foreach ($add_rows as $row) : ?>
                                <li><?php echo esc_html(evtmgr_workshop_bookings_workshop_label($row, false)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
            </section>

            <button type="submit" class="btn btn-primary rounded-pill">Umbuchung vornehmen</button>
            <a href="<?php echo evtmgr_workshop_bookings_current_url(array('action' => 'edit', 'person_id' => $person_id)); ?>" class="btn btn-outline-secondary rounded-pill">Zurück bearbeiten</a>
        </form>
        <?php
        ?>
        </div>
    </div>
</div>
<?php
        return;
    }

    if ($action === 'apply') {
        if (!isset($_POST['evtmgr_workshop_bookings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['evtmgr_workshop_bookings_nonce'])), 'evtmgr_workshop_bookings_apply')) {
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen.');
        }

        $person_id = isset($_POST['person_id']) ? absint(wp_unslash($_POST['person_id'])) : 0;
        $person = evtmgr_workshop_bookings_get_person_by_id($persons, $person_id);

        if (!$person || $person_id <= 0) {
            throw new RuntimeException('Ungültige Person ausgewählt.');
        }

        $delete_ids = isset($_POST['ws_delete']) ? wp_unslash($_POST['ws_delete']) : array();
        if (!is_array($delete_ids)) {
            $delete_ids = array($delete_ids);
        }
        $delete_ids = array_values(array_unique(array_filter(array_map('absint', $delete_ids))));

        $add_values = isset($_POST['ws_add']) ? wp_unslash($_POST['ws_add']) : array();
        if (!is_array($add_values)) {
            $add_values = array($add_values);
        }

        $add_pairs = array();
        foreach ($add_values as $add_value) {
            $parts = explode('_', sanitize_text_field((string) $add_value));
            $workshop_id = isset($parts[0]) ? absint($parts[0]) : 0;
            $timezone_id = isset($parts[1]) ? absint($parts[1]) : 0;

            if ($workshop_id > 0 && $timezone_id > 0) {
                $add_pairs[$workshop_id . '_' . $timezone_id] = array(
                    'workshop_id' => $workshop_id,
                    'timezone_id' => $timezone_id,
                );
            }
        }
        $add_pairs = array_values($add_pairs);

        $event_id = evtmgr_workshop_bookings_get_events_id($wpdb, $tables, $event_uid);

        if ($event_id <= 0 && !empty($add_pairs)) {
            throw new RuntimeException('Kein Kongress für Event Uid gefunden: ' . $event_uid);
        }

        $deleted_count = 0;
        $inserted_count = 0;
        $errors = array();

        $wpdb->query('START TRANSACTION');

        if (!empty($delete_ids)) {
            $placeholders = implode(',', array_fill(0, count($delete_ids), '%d'));
            $sql = "
                DELETE FROM {$tables['registrations_workshops']}
                WHERE fky_workshop_id IN ($placeholders)
                  AND fky_person_id = %d
                  AND fky_event_uid = %s
            ";
            $params = array_merge($delete_ids, array($person_id, $event_uid));
            $deleted = $wpdb->query($wpdb->prepare($sql, $params));

            if ($deleted === false) {
                $errors[] = 'Löschen fehlgeschlagen: ' . $wpdb->last_error;
            } else {
                $deleted_count = (int) $deleted;
            }
        }

        if (empty($errors) && !empty($add_pairs)) {
            foreach ($add_pairs as $pair) {
                $existing = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "
                        SELECT COUNT(*)
                        FROM {$tables['registrations_workshops']}
                        WHERE fky_workshop_id = %d
                          AND fky_person_id = %d
                          AND fky_timezone_id = %d
                          AND fky_event_uid = %s
                        ",
                        $pair['workshop_id'],
                        $person_id,
                        $pair['timezone_id'],
                        $event_uid
                    )
                );

                if ($existing > 0) {
                    continue;
                }

                $inserted = $wpdb->insert(
                    $tables['registrations_workshops'],
                    array(
                        'fky_workshop_id' => $pair['workshop_id'],
                        'fky_person_id'   => $person_id,
                        'fky_timezone_id' => $pair['timezone_id'],
                        'fky_event_uid'    => $event_uid,
                        'fky_event_id' => $event_id,
                    ),
                    array('%d', '%d', '%d', '%s', '%d')
                );

                if ($inserted === false) {
                    $errors[] = 'Neue Buchung fehlgeschlagen für Workshop ' . $pair['workshop_id'] . ': ' . $wpdb->last_error;
                    break;
                }

                $inserted_count++;
            }
        }

        if (!empty($errors)) {
            $wpdb->query('ROLLBACK');
            throw new RuntimeException(implode(' ', $errors));
        }

        $wpdb->query('COMMIT');

        $workshop = new Evtmgr_Workshops();
        $sync_result = $workshop->sync_registrations($event_uid);

        ?>
<div class="container py-4">
    <div class="row">
        <div class="col-12">
<?php
        ?>
        <div class="alert alert-success">
            <h1 class="h4 alert-heading">Die Umbuchung wurde vorgenommen.</h1>
            <p class="mb-0"><?php echo esc_html($deleted_count); ?> Anmeldung(en) gelöscht, <?php echo esc_html($inserted_count); ?> Anmeldung(en) neu gebucht.</p>
        </div>

        <?php if (empty($sync_result['success'])) : ?>
            <div class="alert alert-warning">
                Die Umbuchung wurde gespeichert, aber die Synchronisierung der Anmeldezahlen meldete Fehler.
                <?php if (!empty($sync_result['errors'])) : ?>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($sync_result['errors'] as $sync_error) : ?>
                            <li><?php echo esc_html($sync_error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="alert alert-info">
                Workshop-Anmeldezahlen wurden synchronisiert. Geprüft: <?php echo esc_html((string) ($sync_result['checked'] ?? 0)); ?>,
                aktualisiert: <?php echo esc_html((string) ($sync_result['updated'] ?? 0)); ?>.
            </div>
        <?php endif; ?>

        <a href="<?php echo evtmgr_workshop_bookings_current_url(array('action' => 'edit', 'person_id' => $person_id)); ?>" class="btn btn-primary rounded-pill">Aktuelle Buchungen anzeigen</a>
        <a href="<?php echo evtmgr_workshop_bookings_current_url(array('action' => 'select')); ?>" class="btn btn-outline-secondary rounded-pill">Andere Person wählen</a>
        <?php
        ?>
        </div>
    </div>
</div>
<?php
        return;
    }

    throw new RuntimeException('Unbekannte Aktion.');
} catch (Throwable $e) {
    ?>
<div class="container py-4">
    <div class="row">
        <div class="col-12">
<?php
    ?>
    <div class="alert alert-danger">
        <h1 class="h4 alert-heading">Fehler</h1>
        <p class="mb-0"><?php echo esc_html($e->getMessage()); ?></p>
    </div>
    <a href="<?php echo evtmgr_workshop_bookings_current_url(array('action' => 'select')); ?>" class="btn btn-secondary rounded-pill">Zurück</a>
    <?php
    ?>
        </div>
    </div>
</div>
<?php
}
