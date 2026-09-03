
<?php
$manual_links = array(
    array(
        'str_group'       => 'PDF-Generierung für Workshops',
        'str_title'       => 'PDFs für jeden Workshop/Event generieren',
        'str_url'         => '/wp-admin/admin.php?page=workshop-flyer-pdf-create',
        'mem_description' => 'Für diese Funktion muss eine auf den Event passende PDF-Vorlage programmiert sein.',
    ),

    array(
        'str_group'       => 'PDF-Booklet',
        'str_title'       => 'PDF-Booklet für den Event generieren',
        'str_url'         => '/wp-admin/admin.php?page=booklet-pdf-create',
        'mem_description' => 'Für diese Funktion muss eine auf den Event passende PDF-Vorlage programmiert sein. Ebenso muss ein Inhaltsverzeichnis für das Booklet generiert sein. ',
    ),
    array(
        'str_group'       => 'PDF-Generierung für Teilnehmende',
        'str_title'       => 'Rechnungen generieren',
        'str_url'         => '/wp-admin/admin.php?page=invoice-pdf-create',
        'mem_description' => 'Erstellt eine Rechnung als PDF für angemeldete Personen.',
    ),
    array(
        'str_group'       => 'PDF-Generierung für Teilnehmende',
        'str_title'       => 'Teilnahmebestätigungen generieren',
        'str_url'         => '/wp-admin/admin.php?page=diploma-pdf-create',
        'mem_description' => 'Erstellt eine Teilnahmebestätigung als PDF für angemeldete Personen.',
    ),
    array(
        'str_group'       => 'PDF-Generierung für Teilnehmende',
        'str_title'       => 'Namensschilder generieren',
        'str_url'         => '/wp-admin/admin.php?page=etiketten-pdf-create',
        'mem_description' => 'Erstellt Namens-Etiketten zur Beschriftung für angemeldeten Personen.',
    ),

    array(
        'str_group'       => 'PDF-Generierung für Teilnehmende',
        'str_title'       => 'Programme für Teilnehmende',
        'str_url'         => '/wp-admin/admin.php?page=person-program-pdf-create',
        'mem_description' => 'Erstellt das individuelle Programm als PDF für angemeldeten Personen.',
    ),
    array(
        'str_group'       => 'PDF-Generierung für Teilnehmende',
        'str_title'       => 'Namensschilder generieren',
        'str_url'         => '/wp-admin/admin.php?page=etiketten-pdf-create',
        'mem_description' => 'Erstellt Namens-Etiketten zur Beschriftung für angemeldeten Personen.',
    ),
    array(
        'str_group'       => 'PDF-Generierung für Dozierende',
        'str_title'       => 'Buchungslisten für Workshops',
        'str_url'         => '/wp-admin/admin.php?page=workshop-booking-lists-pdf-create',
        'mem_description' => 'Erstellt Liste aller Teilnehmenden als PDF für jeden Workshop/Anlass.',
    ),
    
);

?>

<div class="container-xxl py-4">
    <?php include ('dashboard-card-2.php'); ?>
</div>