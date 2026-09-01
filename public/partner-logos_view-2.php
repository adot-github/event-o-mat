<?php
/**
 * Sponsor / Partner logo wall — View 2: Animated ticker / marquee.
 *
 * Shortcode: [sponsor_ticker event_uid="xxxx-2026" lang="de" speed="40"]
 *
 * Logos scroll horizontally in an infinite CSS animation loop.
 * The track is duplicated to create a seamless looping effect.
 * The speed attribute controls animation duration in seconds (default 40).
 */

if (!defined('ABSPATH')) {
    exit;
}

$_sp_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_sp_classes_dir . 'class-helpers.php';
require_once $_sp_classes_dir . 'class-evtmgr-sponsors.php';

add_action('init', function () {
    add_shortcode('sponsor_ticker', 'sponsor_wall_ticker_shortcode');
});

function sponsor_wall_ticker_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
            'speed'     => '60',
        ),
        $atts,
        'sponsor_ticker'
    );

    $event_uid = sanitize_text_field((string) $atts['event_uid']);
    $lang      = sanitize_key((string) $atts['lang']);
    $speed     = absint($atts['speed']);

    if ($speed < 5) {
        $speed = 5;
    }

    Event_Registration_Helpers::enqueue_bootstrap($event_uid);

    $obj      = new Evtmgr_Sponsors();
    $sponsors = $obj->get_sponsors_by_event_uid($event_uid, $lang);

    if (empty($sponsors)) {
        return '';
    }

    // Filter out entries without a logo
    $sponsors = array_values(array_filter($sponsors, function ($s) {
        return trim((string) ($s['str_sponsor_logo'] ?? '')) !== '';
    }));

    if (empty($sponsors)) {
        return '';
    }

    $unique_id = 'spticker-' . wp_unique_id();

    ob_start();
    ?>
    <div class="sponsor-wall sponsor-wall--ticker" id="<?php echo esc_attr($unique_id); ?>">
        <style>
            #<?php echo esc_attr($unique_id); ?> .sponsor-wall__track {
                animation-duration: <?php echo $speed; ?>s;
            }
        </style>

        <div class="sponsor-wall__mask">
            <div class="sponsor-wall__track">

                <?php
                // Output twice for the seamless infinite scroll
                for ($pass = 0; $pass < 2; $pass++) :
                    foreach ($sponsors as $s) :
                        $name     = trim((string) ($s['str_sponsor_name'] ?? ''));
                        $logo     = trim((string) ($s['str_sponsor_logo'] ?? ''));
                        $link     = trim((string) ($s['str_sponsor_link'] ?? ''));
                        $img_url  = esc_url(home_url($logo));
                        $link_url = $link !== '' ? esc_url($link) : '';
                        $alt      = esc_attr($name);
                ?>
                <div class="sponsor-wall__item" aria-hidden="<?php echo $pass > 0 ? 'true' : 'false'; ?>">
                    <?php if ($link_url !== '') : ?>
                    <a class="sponsor-wall__link" href="<?php echo $link_url; ?>" target="_blank" rel="noopener noreferrer" title="<?php echo $alt; ?>">
                    <?php endif; ?>

                        <img class="sponsor-wall__logo" src="<?php echo $img_url; ?>" alt="<?php echo $pass > 0 ? '' : $alt; ?>" loading="lazy">

                    <?php if ($link_url !== '') : ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php
                    endforeach;
                endfor;
                ?>

            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
