<?php
/**
 * Sponsor / Partner logo wall — View 1: Responsive grid.
 *
 * Shortcode: [sponsor_wall event_uid="xxxx-2026" lang="de"]
 *
 * Displays all sponsor logos in an equal-height Bootstrap 5 card grid with hover effects.
 * Logos are grouped by str_sponsor_group when the field is filled.
 */

if (!defined('ABSPATH')) {
    exit;
}

$_sp_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_sp_classes_dir . 'class-helpers.php';
require_once $_sp_classes_dir . 'class-evtmgr-sponsors.php';

add_action('init', function () {
    add_shortcode('sponsor_wall', 'sponsor_wall_grid_shortcode');
});

function sponsor_wall_grid_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
        ),
        $atts,
        'sponsor_wall'
    );

    $event_uid = sanitize_text_field((string) $atts['event_uid']);
    $lang      = sanitize_key((string) $atts['lang']);

    $obj      = new Evtmgr_Sponsors();
    $sponsors = $obj->get_sponsors_by_event_uid($event_uid, $lang);

    if (empty($sponsors)) {
        return '';
    }

    // Group by str_sponsor_group (empty → ungrouped)
    $groups = array();
    foreach ($sponsors as $s) {
        $group            = trim((string) ($s['str_sponsor_group'] ?? ''));
        $groups[$group][] = $s;
    }

    ob_start();
    ?>
    <div class="sponsor-wall sponsor-wall--grid">
        <?php foreach ($groups as $group_label => $items) : ?>

            <?php if ($group_label !== '') : ?>
            <h3 class="sponsor-wall__group-title"><?php echo esc_html($group_label); ?></h3>
            <?php endif; ?>

            <div class="row g-0 sponsor-wall__row">
                <?php foreach ($items as $s) :
                    $name = trim((string) ($s['str_sponsor_name'] ?? ''));
                    $logo = trim((string) ($s['str_sponsor_logo'] ?? ''));
                    $link = trim((string) ($s['str_sponsor_link'] ?? ''));

                    if ($logo === '') {
                        continue;
                    }

                    // Preserve existing relative logo paths, but do not break already absolute URLs.
                    $img_url  = preg_match('#^https?://#i', $logo) ? esc_url($logo) : esc_url(home_url($logo));
                    $link_url = $link !== '' ? esc_url($link) : '';
                    $alt      = $name !== '' ? esc_attr($name) : esc_attr__('Sponsor logo', 'picostrap5-child-base');
                ?>
                <div class="col-6 col-sm-6 col-md-4 col-lg-3 sponsor-wall__col">
                    <div class="card sponsor-card h-100 rounded-0">
                        <div class="card-body sponsor-card__body">
                            <?php if ($link_url !== '') : ?>
                            <a class="sponsor-wall__link" href="<?php echo $link_url; ?>" target="_blank" rel="noopener noreferrer" title="<?php echo $alt; ?>">
                            <?php else : ?>
                            <div class="sponsor-wall__link" aria-label="<?php echo $alt; ?>">
                            <?php endif; ?>

                                <img class="sponsor-wall__logo" src="<?php echo $img_url; ?>" alt="<?php echo $alt; ?>" loading="lazy">

                            <?php if ($link_url !== '') : ?>
                            </a>
                            <?php else : ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
