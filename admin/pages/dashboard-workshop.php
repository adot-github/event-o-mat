
<?php
$manual_links = array(
    array(
        'str_group'       => 'Workshops',
        'str_title'       => 'Workshops bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_workshops',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Workshops.',
    ),
    array(
        'str_group'       => 'Workshops',
        'str_title'       => 'Workshops umbuchen',
        'str_url'         => '/wp-admin/admin.php?page=workshop-booking-changes',
        'mem_description' => 'Formular zur Anpassung der gebuchten Workshops. Danach müssen eventuell auch die Kosten angepasst werden.',
    ),
    array(
        'str_group'       => 'Workshops',
        'str_title'       => 'Buchungslisten für Workshops',
        'str_url'         => '/wp-admin/admin.php?page=workshop-booking-lists-pdf-create',
        'mem_description' => 'Erstellt Liste aller Teilnehmenden als PDF für jeden Workshop/Anlass',
    ),

    array(
        'str_group'       => 'Präsentierende Personen',
        'str_title'       => 'Präsentierende Personen bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_presenters',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Dozierenden.',
    ),

    array(
        'str_group'       => 'Präsentierende Personen',
        'str_title'       => 'Liste der präsentierenden Personen',
        'str_url'         => '/wp-admin/admin.php?page=report-presenters',
        'mem_description' => 'Liste aller Personen, welche in Workshops etc. präsentieren',
    ),
    array(
        'str_group'       => 'Workshop-Konfigurationen',
        'str_title'       => 'Zeitplan bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_timezones',
        'mem_description' => 'Den Ablauf des Events erfassen oder mutieren.',
    ),
    array(
        'str_group'       => 'Workshop-Konfigurationen',
        'str_title'       => 'Slots bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_slots',
        'mem_description' => 'Die Slots/Tracks erfassen oder bearbeiten',
    ),
    array(
        'str_group'       => 'Workshop-Konfigurationen',
        'str_title'       => 'Preisstruktur bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=acdb_evtmgr_pricing',
        'mem_description' => 'Die Kostenstruktur des Events erfassen oder bearbeiten',
    )
    
);
?>

<?php
    include ('dashboard-card-2.php');
?>