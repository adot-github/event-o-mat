<?php include __DIR__ . '/dashboard-active-event-title.php'; ?>

<?php
$current_group = '';
$tier_classes  = ['bg-secondary', 'bg-danger', 'bg-success'];
$tier_index    = 0;

foreach ($manual_links as $item) :
    $item_group = isset($item['str_group']) ? (string) $item['str_group'] : '';

    if ($item_group !== $current_group) :
        if ($current_group !== '') :
            ?>
            </div>
            <?php
        endif;

        $current_group = $item_group;
        $current_tier  = $tier_classes[$tier_index++ % 3];
        $group_margin_top = $tier_index === 1 ? 'mt-0' : 'mt-5';
        ?>

        <div class="d-flex justify-content-between align-items-baseline <?php echo $group_margin_top; ?> mb-3">
            <h2 class="dashed m-0 mt-3 mb-1"><?php echo esc_html($current_group); ?></h2>
        </div>

        <div class="row g-4">
    <?php endif; ?>

    <div class="col-12 col-lg-4 mt-0 mb-3">
        <div class="card evtmgr-tier <?php echo $current_tier; ?> h-100">
            <div class="card-body">

                <h3 class="m-0">
                    <?php echo esc_html($item['str_title']); ?>
                </h3>

                <p class="card-text mt-2 mb-0">
                    <?php echo esc_html($item['mem_description']); ?>
                </p>

                <a href="<?php echo esc_url($item['str_url']); ?>"
                   class="btn evtmgr-tier-btn w-100 rounded-pill fw-semibold mt-4">
                    Öffnen
                </a>

            </div>
        </div>
    </div>

<?php endforeach; ?>

<?php if ($current_group !== '') : ?>
    </div>
<?php endif; ?>
