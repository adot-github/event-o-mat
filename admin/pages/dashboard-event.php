
<?php
$manual_links = array(
    array(
        'str_group'       => 'Events',
        'str_title'       => 'Events bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_events',
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
    array(
        'str_group'       => 'Optionen',
        'str_title'       => 'Optionen des Events',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_options',
        'mem_description' => 'Diverse Optionen für den Event ',
    ),
);

?>

<?php
    include ('dashboard-card-2.php');
?>