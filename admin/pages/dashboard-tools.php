
<?php
$manual_links = array(
    array(
        'str_group'       => 'Dateiablage',
        'str_title'       => 'Dateiablage bereinigen',
        'str_url'         => '/wp-admin/admin.php?page=filestorage-clean',
        'mem_description' => 'Alle generierten Dateien des aktuellen Events löschen (Tickets, Rechnungen, Diplome, etc.).',
    ),
    array(
        'str_group'       => 'Wordings',
        'str_title'       => 'Wordings bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_wordings',
        'mem_description' => 'Texte in der Anmeldung bearbeiten',
    ),
    array(
        
        'str_group'       => 'Wordings',
        'str_title'       => 'Wordings extrahieren',
        'str_url'         => '/wp-admin/admin.php?page=wordings-extract',
        'mem_description' => 'Neue Wordings finden und extrahieren.',
    ),
    array(
        'str_group'       => 'Wordings',
        'str_title'       => 'Wordings scannen',
        'str_url'         => '/wp-admin/admin.php?page=wordings-scan',
        'mem_description' => 'Anzahl Verwendungen in der Datenbank aktualisieren.',
    ),    
    array(
        'str_group'       => 'Datenbank',
        'str_title'       => 'Felder extrahieren',
        'str_url'         => '/wp-admin/admin.php?page=database-fields-extract',
        'mem_description' => 'Anzahl Verwendungen in der Datenbank aktualisieren.',
    ),    
    array(
        'str_group'       => 'Datenbank',
        'str_title'       => 'Feld-Labels einfügen',
        'str_url'         => '/wp-admin/admin.php?page=database-fields-fill',
        'mem_description' => 'Anzahl Verwendungen in der Datenbank aktualisieren.',
    ),

    
);

?>

<?php
    include ('dashboard-card-2.php');
?>