
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

$manual_links = array(
    array(
        'str_group'       => 'Anmeldungen',
        'str_title'       => 'Anmeldungen bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_persons',
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
        'str_group'       => 'Namensschilder und Tickets',
        'str_title'       => 'Namensschilder PDF generieren',
        'str_url'         => '/wp-admin/admin.php?page=etiketten-pdf-create',
        'mem_description' => 'Namens-Etiketten und Namens-Etiketten erstellen zur BEschriftung der Teilnehmnden.',
    ),
    array(
        'str_group'       => 'Namensschilder und Tickets',
        'str_title'       => 'Ticket PDF generieren',
        'str_url'         => '/wp-admin/admin.php?page=ticket-pdf-create',
        'mem_description' => 'Tickets für den Check-In generieren.',
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
                        Workshop-Auslastung
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
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover align-middle mb-0">
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

                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    include ('dashboard-card-2.php');
    ?>

</div>