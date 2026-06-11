<link rel='stylesheet' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/pages/assets/dashboard.css' media='all' />

<?php
$manual_links = array(
    array(
        'str_group'       => 'Events',
        'str_title'       => 'Events bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=adot_evtmgr_events',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Events.',
    ),
    array(
        
        'str_group'       => 'Events',
        'str_title'       => 'Event duplizieren',
        'str_url'         => '/wp-admin/admin.php?page=event-duplicate',
        'mem_description' => 'Einen neuen Event erstellen basierend auf einem bestehenden Event.',
    ),
    array(
        'str_group'       => 'Events',
        'str_title'       => 'Event löschen',
        'str_url'         => '/wp-admin/admin.php?page=event-delete',
        'mem_description' => 'Event und alle zugehörigen Daten löschen.',
    ),    
    array(
        'str_group'       => 'Reports',
        'str_title'       => 'Umsatz des Events',
        'str_url'         => '/wp-admin/admin.php?page=report-income',
        'mem_description' => 'Liste aller Personen mit zu bezahlendem Betrag',
    ),
);

?>

<?php
    include ('dashboard-card-2.php');
?>