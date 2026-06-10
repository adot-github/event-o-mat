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

$manual_links = array(
    array(
        'str_group'       => 'Anmeldungen',
        'str_title'       => 'Anmeldungen bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=adot_evtmgr_persons',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Anmeldungen.',
    ),
    array(
        'str_group'       => 'Anmeldungen',
        'str_title'       => 'Umbuchungen von Workshops',
        'str_url'         => '/wp-admin/admin.php?page=workshop-booking-changes',
        'mem_description' => 'Formular zur Anpassung der gebuchten Workshops. Danach müssen eventuell auch die Kosten angepasst werden.',
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
        'str_title'       => 'Rechnungen für Teilnehmende erstellen',
        'str_url'         => '/wp-admin/admin.php?page=invoice-pdf-create',
        'mem_description' => 'Erstellt eine Rechnung als PDF für alle angemeldeten Personen',
    ),
    array(
        'str_group'       => 'Rechnungen',
        'str_title'       => 'Rechnungen für Teilnehmende versenden',
        'str_url'         => '/wp-admin/admin.php?page=invoice-send-by-email',
        'mem_description' => 'Erstellt eine Rechnung als PDF für alle angemeldeten Personen',
    ),
    
    array(
        'str_group'       => 'Teilnahmebestätigungen',
        'str_title'       => 'Teilnahmebestätigungen für Teilnehmende als PDF erstellen',
        'str_url'         => '/wp-admin/admin.php?page=diploma-pdf-create',
        'mem_description' => 'Erstellt eine Teilnahmebestätigung als PDF für alle angemeldeten Personen',
    ),
    
    array(
        'str_group'       => 'Teilnahmebestätigungen',
        'str_title'       => 'Teilnahmebestätigungen versenden',
        'str_url'         => '/wp-admin/admin.php?page=diploma-send-by-email',
        'mem_description' => 'Teilnahmebestätigungen per E-Mail versenden',
    ),
    
    array(
        'str_group'       => 'Listen',
        'str_title'       => 'Liste der angemeldeten Personen',
        'str_url'         => '/wp-admin/admin.php?page=report-persons',
        'mem_description' => 'Liste aller Personen, welche sich angemeldet haben',
    ),
    array(
        'str_group'       => 'Listen',
        'str_title'       => 'Liste der angemeldeten Personen mit Workshops',
        'str_url'         => '/wp-admin/admin.php?page=report-participant-workshops',
        'mem_description' => 'Liste aller Personen, welche sich angemeldet haben mit den angemeldeten Workshops',
    ),

    
    array(
        'str_group'       => 'PDF erstellen',
        'str_title'       => 'Programme für Teilnehmende als PDF',
        'str_url'         => '/wp-admin/admin.php?page=person-program-pdf-create',
        'mem_description' => 'Erstellt das individuelle Programm als PDF für alle angemeldeten Personen',
    ),
);
?>


<div class="container-xxl py-4">

    <section class="mb-0">
        <h2 class="h4 mb-3">Workshop-Auslastung</h2>

        <?php if (empty($event_uid)) : ?>
            <div class="alert alert-warning mb-0">
                Kein aktiver Event gewählt.
            </div>
        <?php elseif (empty($workshop_report_rows)) : ?>
            <div class="alert alert-info mb-0">
                Für diesen Event wurden keine Workshops gefunden.
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Workshop</th>
                            <th class="text-end">Anmeldungen</th>
                            <th class="text-end">Max. Plätze</th>
                            <th class="text-end">Freie Plätze</th>
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
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php
    $current_group = '';

    foreach ($manual_links as $item) :
        $item_group = isset($item['str_group']) ? (string) $item['str_group'] : '';

        if ($item_group !== $current_group) :
            if ($current_group !== '') :
                ?>
                </div>
                <?php
            endif;

            $current_group = $item_group;
            ?>
            <br>
            <hr class="mt-4">
            <h3 class="mt-0 mb-0">
                <?php echo esc_html($current_group); ?>
            </h3>


            <div class="row g-4 mb-4">
        <?php endif; ?>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body d-flex flex-column">

                    <h2 class="h5 card-title">
                        <?php echo esc_html($item['str_title']); ?>
                    </h2>

                    <p class="card-text text-muted m-0">
                        <?php echo esc_html($item['mem_description']); ?>
                    </p>

                    <div class="mt-auto">
                        <a href="<?php echo esc_url($item['str_url']); ?>"
                           class="btn btn-primary">
                            Öffnen
                        </a>
                    </div>

                </div>
            </div>
        </div>

    <?php endforeach; ?>

    <?php if ($current_group !== '') : ?>
        </div>
    <?php endif; ?>

</div>