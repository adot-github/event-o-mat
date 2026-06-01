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

<div class="container-xxl py-4">
    <div class="row g-4">
        <h2>Dozierende</h2>
        <?php foreach ($manual_links as $item) : ?>
<div class="col-12 col-md-6 col-xl-4">
    <div class="card h-100 shadow-sm card-hover">
        <div class="card-body d-flex flex-column">

            <h2 class="h5 card-title">
                <?php echo esc_html($item['str_title']); ?>
            </h2>

            <p class="card-text text-muted">
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

    </div>
</div>