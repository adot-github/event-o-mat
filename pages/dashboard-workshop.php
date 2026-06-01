<?php
$manual_links = array(
    array(
        'str_group'       => 'Workshops',
        'str_title'       => 'Workshops bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=adot_evtmgr_workshops',
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
        'str_title'       => 'Workshop-Buchungslisten für Workshops',
        'str_url'         => '/wp-admin/admin.php?page=workshop-booking-lists-pdf-create',
        'mem_description' => 'Erstellt Liste aller Teilnehmenden als PDF für jeden Workshop/Anlass',
    ),

    array(
        'str_group'       => 'Workshops: Präsentierende Personen',
        'str_title'       => 'Präsentierende Personen bearbeiten',
        'str_url'         => '/wp-admin/admin.php?page=adot_evtmgr_presenters',
        'mem_description' => 'Formular zur Bearbeitung aller vorhandenen Dozierenden.',
    ),

    array(
        'str_group'       => 'Workshops: Präsentierende Personen',
        'str_title'       => 'Lister der präsentierende Personen',
        'str_url'         => '/wp-admin/admin.php?page=report-presenters',
        'mem_description' => 'Liste aller Personen, welche in Workshops etc. präsentieren',
    ),
);
?>

<div class="container-xxl py-4">

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

    <?php if ($current_group !== '') : ?>
        </div>
    <?php endif; ?>

</div>