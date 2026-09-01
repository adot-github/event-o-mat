
<?php

global $wpdb;

$event_uid = '';

if (!class_exists('Event_Registration_Context')) {
    $event_registration_context_file = get_stylesheet_directory() . '/db-custom/event-registration/classes/class-event-registration.php';

    if (file_exists($event_registration_context_file)) {
        require_once $event_registration_context_file;
    }
}

if (class_exists('Event_Registration_Context')) {
    $event_registration_context = new Event_Registration_Context();

    if (method_exists($event_registration_context, 'get_cookie_event_uid')) {
        $event_uid = $event_registration_context->get_cookie_event_uid(false);
    }
}

if ($event_uid === '' && !empty($_COOKIE['current_event_uid'])) {
    $event_uid = sanitize_text_field(wp_unslash($_COOKIE['current_event_uid']));
}

if (!empty($event_uid)) {
    if (!class_exists('Evtmgr_Workshops')) {
        $evtmgr_workshops_class_file = get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-workshops.php';

        if (file_exists($evtmgr_workshops_class_file)) {
            require_once $evtmgr_workshops_class_file;
        }
    }

    if (class_exists('Evtmgr_Workshops')) {
        $workshops_obj = new Evtmgr_Workshops();

        if (method_exists($workshops_obj, 'sync_registrations')) {
            $workshops_obj->sync_registrations($event_uid);
        }
    }
}

$workshop_report_rows = array();

if (!empty($event_uid) && isset($wpdb)) {
    $workshops_table              = $wpdb->prefix . 'evtmgr_workshops';
    $registrations_workshops_table = $wpdb->prefix . 'evtmgr_registrations_workshops';

    $workshop_report_sql = "
        SELECT
            w.id AS workshop_id,
            w.str_workshop_number,
            w.str_workshop_title_de,
            w.int_max_number_of_registrations,
            w.int_number_of_registrations AS stored_number_of_registrations,
            COUNT(DISTINCT rw.id) AS registration_count
        FROM {$workshops_table} AS w
        LEFT JOIN {$registrations_workshops_table} AS rw
            ON rw.fky_workshop_id = w.id
           AND rw.fky_event_uid = w.fky_event_uid
        WHERE w.fky_event_uid = %s
          AND w.ysn_no_registration_possible = 0
        GROUP BY
            w.id,
            w.str_workshop_number,
            w.str_workshop_title_de,
            w.int_max_number_of_registrations,
            w.int_number_of_registrations
        ORDER BY
            w.str_workshop_number,
            w.str_workshop_title_de
    ";

    $workshop_report_rows = $wpdb->get_results(
        $wpdb->prepare($workshop_report_sql, $event_uid),
        ARRAY_A
    );

    if (!is_array($workshop_report_rows)) {
        $workshop_report_rows = array();
    }
}

$income_report_rows = array();

if (!empty($event_uid) && isset($wpdb)) {
    $persons_table = $wpdb->prefix . 'evtmgr_persons';

    $income_report_sql = "
        SELECT
            CAST(COALESCE(p.num_invoice_total, 0) AS DECIMAL(10,2)) AS amount_per_registration,
            COUNT(*) AS registration_count
        FROM {$persons_table} AS p
        WHERE p.fky_event_uid = %s
        GROUP BY amount_per_registration
        ORDER BY amount_per_registration DESC
    ";

    $income_report_rows = $wpdb->get_results(
        $wpdb->prepare($income_report_sql, $event_uid),
        ARRAY_A
    );

    if (!is_array($income_report_rows)) {
        $income_report_rows = array();
    }
}

$manual_links = array(
    array(
        'str_group'       => 'Anmeldungen',
        'str_title'       => 'Anmeldungen bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_persons',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Anmeldungen.',
    ),
    array(
        'str_group'       => 'Anmeldungen',
        'str_title'       => 'Anmeldungen umbuchen',
        'str_url'         => '/wp-admin/admin.php?page=workshop-booking-changes',
        'mem_description' => 'Formular zur Anpassung der gebuchten Workshops. Danach müssen auch die Kosten angepasst werden.',
    ),
    array(
        'str_group'       => 'Anmeldungen',
        'str_title'       => 'Anmeldungen löschen',
        'str_url'         => '/wp-admin/admin.php?page=registration-delete',
        'mem_description' => 'Routine zum kompletten lösche einer Anmeldung.',
    ),

    array(
        'str_group'       => 'Rechnungen',
        'str_title'       => 'Rechnungen korrigieren',
        'str_url'         => '/wp-admin/admin.php?page=invoice-change',
        'mem_description' => 'Formular zur Anpassung der Rechnung.',
    ),
    array(
        'str_group'       => 'Rechnungen',
        'str_title'       => 'Rechnungen generieren',
        'str_url'         => '/wp-admin/admin.php?page=invoice-pdf-create',
        'mem_description' => 'Erstellt eine Rechnung als PDF für angemeldete Personen.',
    ),
    array(
        'str_group'       => 'Rechnungen',
        'str_title'       => 'Rechnungen versenden',
        'str_url'         => '/wp-admin/admin.php?page=invoice-send-by-email',
        'mem_description' => 'Verschickt eine Rechnung als PDF an ausgewählte Personen.',
    ),
    
    array(
        'str_group'       => 'Teilnahmebestätigungen',
        'str_title'       => 'Teilnahmebestätigungen generieren',
        'str_url'         => '/wp-admin/admin.php?page=diploma-pdf-create',
        'mem_description' => 'Erstellt eine Teilnahmebestätigung als PDF für angemeldete Personen.',
    ),
    
    array(
        'str_group'       => 'Teilnahmebestätigungen',
        'str_title'       => 'Teilnahmebestätigungen versenden',
        'str_url'         => '/wp-admin/admin.php?page=diploma-send-by-email',
        'mem_description' => 'Verschickt eine Teilnahmebestätigungen als PDF an ausgewählte Personen.',
    ),

    array(
        'str_group'       => 'Namensschilder und Tickets',
        'str_title'       => 'Namensschilder generieren',
        'str_url'         => '/wp-admin/admin.php?page=etiketten-pdf-create',
        'mem_description' => 'Erstellt Namens-Etiketten zur Beschriftung für angemeldeten Personen.',
    ),
    array(
        'str_group'       => 'Namensschilder und Tickets',
        'str_title'       => 'Tickets generieren',
        'str_url'         => '/wp-admin/admin.php?page=ticket-pdf-create',
        'mem_description' => 'Erstellt Tickets für den Check-In für angemeldeten Personen.',
    ),


    


    array(
        'str_group'       => 'Listen',
        'str_title'       => 'Angemeldete Personen',
        'str_url'         => '/wp-admin/admin.php?page=report-persons',
        'mem_description' => 'Liste aller Personen, welche sich angemeldet haben.',
    ),
    array(
        'str_group'       => 'Listen',
        'str_title'       => 'Angemeldete Personen mit Workshops',
        'str_url'         => '/wp-admin/admin.php?page=report-participant-workshops',
        'mem_description' => 'Liste aller Personen, welche sich angemeldet haben, mit den angemeldeten Workshops.',
    ),

    
    array(
        'str_group'       => 'PDF erstellen',
        'str_title'       => 'Programme für Teilnehmende',
        'str_url'         => '/wp-admin/admin.php?page=person-program-pdf-create',
        'mem_description' => 'Erstellt das individuelle Programm als PDF für angemeldeten Personen.',
    ),
);
?>


<div class="container-xxl py-4">
    <div class="ps-2">
        <?php include __DIR__ . '/dashboard-active-event-title.php'; ?>
    </div>

    <section class="m-0">
        <div class="accordion" id="accordion-workshop-auslastung">
            <div class="accordion-item">
                <h2 class="accordion-header m-0" id="accordion-workshop-auslastung-heading">
                    <button class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#accordion-workshop-auslastung-body"
                            aria-expanded="false"
                            aria-controls="accordion-workshop-auslastung-body">
                        Auslastung und Einnahmen
                    </button>
                </h2>
                <div id="accordion-workshop-auslastung-body"
                     class="accordion-collapse collapse"
                     aria-labelledby="accordion-workshop-auslastung-heading">
                    <div class="accordion-body p-0">

                        <?php if (empty($event_uid)) : ?>
                            <div class="alert alert-warning m-3">
                                Kein aktiver Event gewählt.
                            </div>
                        <?php elseif (empty($workshop_report_rows)) : ?>
                            <div class="alert alert-info m-3">
                                Für diesen Event wurden keine Workshops gefunden.
                            </div>
                        <?php else : ?>
                            <?php
                                // Gemeinsame Skala für die Visualisierung des Anmeldestandes (Anteil freier Plätze).
                                $render_anmeldestand_bar = static function ($registration_count, $max_places, $label) {
                                    $registration_count = (int) $registration_count;
                                    $max_places         = (int) $max_places;

                                    if ($max_places <= 0) {
                                        echo '<span class="text-muted">&ndash;</span>';
                                        return;
                                    }

                                    $free_places   = max(0, $max_places - $registration_count);
                                    $booked_percent = (int) round(($registration_count / $max_places) * 100);
                                    $free_percent  = min(100, max(0, 100 - $booked_percent));

                                    if ($free_percent >= 81) {
                                        $bar_color = '#60b564';
                                    } elseif ($free_percent >= 51) {
                                        $bar_color = '#fed700';
                                    } elseif ($free_percent >= 31) {
                                        $bar_color = '#f69d01';
                                    } elseif ($free_percent >= 6) {
                                        $bar_color = '#e46117';
                                    } else {
                                        $bar_color = '#ca0638';
                                    }
                                    ?>
                                    <div class="progress"
                                         role="progressbar"
                                         aria-label="Anmeldestand <?php echo esc_attr($label); ?>"
                                         aria-valuenow="<?php echo esc_attr((string) $free_percent); ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         style="height: 1rem; background-color: #e9ecef; border-radius: 0;"
                                         title="<?php echo esc_attr($free_places . ' von ' . $max_places . ' frei (' . $free_percent . '%)'); ?>">
                                        <div class="progress-bar"
                                             style="width: <?php echo esc_attr((string) $free_percent); ?>%; background-color: <?php echo esc_attr($bar_color); ?>; color: #000; font-weight: 600; border-radius: 0;">
                                            <?php echo esc_html($free_percent . '% frei'); ?>
                                        </div>
                                    </div>
                                    <?php
                                };

                                $total_max_places    = 0;
                                $total_registrations = 0;

                                foreach ($workshop_report_rows as $totals_row) {
                                    $row_max = isset($totals_row['int_max_number_of_registrations'])
                                        ? (int) $totals_row['int_max_number_of_registrations']
                                        : 0;

                                    if ($row_max > 0) {
                                        $total_max_places    += $row_max;
                                        $total_registrations += isset($totals_row['registration_count'])
                                            ? (int) $totals_row['registration_count']
                                            : 0;
                                    }
                                }

                                $total_free_places = max(0, $total_max_places - $total_registrations);
                            ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th style="min-width: 160px;">Anmeldestand</th>
                                            <th class="text-end">Anm.</th>
                                            <th class="text-end">Max.</th>
                                            <th class="text-end">Frei</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                            <?php foreach ($workshop_report_rows as $workshop_report_row) : ?>
                                            <?php
                                                $registration_count = isset($workshop_report_row['registration_count'])
                                                    ? (int) $workshop_report_row['registration_count']
                                                    : 0;

                                                $max_places = isset($workshop_report_row['int_max_number_of_registrations'])
                                                    ? (int) $workshop_report_row['int_max_number_of_registrations']
                                                    : 0;

                                                $free_places = $max_places > 0
                                                    ? max(0, $max_places - $registration_count)
                                                    : null;

                                                $workshop_label = trim(
                                                    (string) ($workshop_report_row['str_workshop_number'] ?? '') . ' ' .
                                                    (string) ($workshop_report_row['str_workshop_title_de'] ?? '')
                                                );

                                                if ($workshop_label === '') {
                                                    $workshop_label = 'Workshop ID ' . (string) ($workshop_report_row['workshop_id'] ?? '');
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html($workshop_label); ?></td>
                                                <td><?php $render_anmeldestand_bar($registration_count, $max_places, $workshop_label); ?></td>
                                                <td class="text-end"><?php echo esc_html((string) $registration_count); ?></td>
                                                <td class="text-end">
                                                    <?php echo $max_places > 0 ? esc_html((string) $max_places) : '&ndash;'; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php echo $free_places !== null ? esc_html((string) $free_places) : '&ndash;'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" style="border: 0; height: 1rem;"></td>
                                        </tr>
                                        <tr class="fw-bold border-top">
                                            <td>Total Auslastung</td>
                                            <td><?php $render_anmeldestand_bar($total_registrations, $total_max_places, 'total'); ?></td>
                                            <td class="text-end"><?php echo esc_html((string) $total_registrations); ?></td>
                                            <td class="text-end"><?php echo $total_max_places > 0 ? esc_html((string) $total_max_places) : '&ndash;'; ?></td>
                                            <td class="text-end"><?php echo $total_max_places > 0 ? esc_html((string) $total_free_places) : '&ndash;'; ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <?php
                                $format_chf_sum = static function ($amount) {
                                    return 'CHF ' . number_format((float) $amount, 2, '.', '\'');
                                };

                                $format_chf_unit = static function ($amount) {
                                    $amount   = (float) $amount;
                                    $rappen   = (int) round(($amount - floor($amount)) * 100);
                                    $integer  = number_format(floor($amount), 0, '.', '\'');

                                    return $rappen === 0
                                        ? 'CHF ' . $integer . '.' . "\u{2013}"
                                        : 'CHF ' . $integer . '.' . str_pad((string) $rappen, 2, '0', STR_PAD_LEFT);
                                };

                                $income_total = 0.0;
                            ?>

                            <div class="table-responsive mt-4">
                                <table class="table table-sm table-striped table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Einnahmen</th>
                                            <th class="text-end">Betrag</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($income_report_rows)) : ?>
                                            <tr>
                                                <td colspan="2" class="text-muted">Keine Anmeldungen erfasst.</td>
                                            </tr>
                                        <?php else : ?>
                                            <?php foreach ($income_report_rows as $income_report_row) : ?>
                                                <?php
                                                    $amount_per_registration = isset($income_report_row['amount_per_registration'])
                                                        ? (float) $income_report_row['amount_per_registration']
                                                        : 0.0;

                                                    $income_registration_count = isset($income_report_row['registration_count'])
                                                        ? (int) $income_report_row['registration_count']
                                                        : 0;

                                                    $income_line_sum = $amount_per_registration * $income_registration_count;
                                                    $income_total   += $income_line_sum;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                            echo esc_html(
                                                                $income_registration_count . ' ' .
                                                                _n('Anmeldung', 'Anmeldungen', $income_registration_count, 'picostrap5-child-base') .
                                                                ' zu '
                                                            );
                                                            echo esc_html($format_chf_unit($amount_per_registration));
                                                        ?>
                                                    </td>
                                                    <td class="text-end"><?php echo esc_html($format_chf_sum($income_line_sum)); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold border-top">
                                            <td>Total Einnahmen</td>
                                            <td class="text-end"><?php echo esc_html($format_chf_sum($income_total)); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    include ('dashboard-card-2.php');
    ?>

</div>