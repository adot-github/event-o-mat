
<style>
    .card-icon {max-width:36px;float:left;margin-right:.5rem;margin-top:-.65rem;}
</style>

<div class="container py-4">
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

            <h1 class="mt-5 mb-0">
                <?php echo esc_html($current_group); ?>
            </h1>

            <div class="row g-4">
        <?php endif; ?>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body pb-0 d-flex flex-column">
                    
                    <h2 class="h5 card-title">
                        <img src="/wp-content/themes/picostrap5-child-base/db-custom/event-registration/admin/pages/img/event.png" class="card-icon">
                        <?php echo esc_html($item['str_title']); ?>
                    </h2>

                    <p class="card-text m-0">
                        <?php echo esc_html($item['mem_description']); ?>
                    </p>

                    <div class="mt-2">
                        <a href="<?php echo esc_url($item['str_url']); ?>"
                           class="btn btn-primary rounded-pill">
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