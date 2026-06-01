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

<style>
    .card-icon {max-width:36px;float:left;margin-right:.5rem;margin-top:-.65rem;}
</style>

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
                        <img src="/wp-content/themes/picostrap5/db-custom/event-registration/pages/img/event.png" class="card-icon">
                        <?php echo esc_html($item['str_title']); ?>
                    </h2>

                    <p class="card-text m-0">
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