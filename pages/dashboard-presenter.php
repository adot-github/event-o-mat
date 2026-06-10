<link rel='stylesheet' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/pages/assets/dashboard.css' media='all' />

<?php
$manual_links = array(
    array(
        'str_title'       => 'Workshops bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=adot_evtmgr_workshops',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Workshops.',
    ),
    array(
        'str_title'       => 'Umsatz des Events',
        'str_url'         => '/wp-admin/admin.php?page=pdf_participants_invoices',
        'mem_description' => 'Liste aller Personen mit zu bezahlendem Betrag',
    ),
    array(
        'str_title'       => 'Workshop-Buchungslisten für Workshops',
        'str_url'         => '/wp-admin/admin.php?page=pdf_workshop_booking_lists',
        'mem_description' => 'Erstellt Liste aller Teilnehmenden als PDF für jeden Workshop/Anlass',
    ),

    array(
        'str_title'       => 'Präsentierende Personen',
        'str_url'         => '/wp-admin/admin.php?page=adot_evtmgr_presenters',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Dozierenden.',
    ),

    array(
        'str_title'       => 'Lister der präsentierende Personen',
        'str_url'         => '/wp-admin/admin.php?page=presenter_persons',
        'mem_description' => 'Liste aller Personen, welche in Workshops etc. präsentieren',
    ),
);
?>

<?php
    include ('dashboard-card.php');
?>