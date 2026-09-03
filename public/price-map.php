<?php
/**
 * Preis-/Anmeldungstabelle (Price Map).
 *
 * Usage as shortcode:
 * [price_map event_uid="xxxx-2026" lang="de"]
 *
 * Renders EXACTLY the same table as the event booklet does on its
 * "Preise" page: the markup comes verbatim from
 * Evtmgr_Pdf_Content::get_event_price_map() (pricing parents as
 * <tr class="pr-section">, their children as <tr class="pr-row"> with
 * optional description and "gültig bis" note). The booklet's print CSS
 * (pr-* classes) is ported to web units here and scoped to
 * .event-price-map so it does not collide with the theme.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-evtmgr-pdf-content.php';

add_action('init', function () {
    add_shortcode('price_map', 'event_registration_price_map_shortcode');
});

function event_registration_price_map_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
        ),
        $atts,
        'price_map'
    );

    $event_uid = sanitize_text_field((string) $atts['event_uid']);
    $lang      = sanitize_key((string) $atts['lang']);

    if ($event_uid === '') {
        return '';
    }

    $content = new Evtmgr_Pdf_Content($event_uid, $lang !== '' ? $lang : 'de');

    if (!$content->has_event()) {
        return '';
    }

    $table_html = $content->get_event_price_map();

    if (trim($table_html) === '') {
        return '';
    }

    $css = <<<CSS
    <style>
        .event-price-map { margin: 1.5rem 0; }

        .event-price-map .pr-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
            line-height: 1.4;
        }

        .event-price-map .pr-section td {
            background: #ffffff;
            color: #000000;
            font-weight: 400;
            font-size: 1.15rem;
            padding: 1.4rem 0.75rem 0.4rem;
            border-top: 2px solid #000000;
        }

        .event-price-map .pr-row td {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }

        .event-price-map .pr-name  { width: 75%; }

        .event-price-map .pr-price {
            width: 25%;
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }

        .event-price-map .pr-desc {
            font-size: 0.85rem;
            font-weight: 400;
            color: #555555;
            margin-top: 0.2rem;
        }

        .event-price-map .pr-valid {
            font-size: 0.8rem;
            font-style: italic;
            color: #888888;
            margin-top: 0.2rem;
        }

        @media (max-width: 480px) {
            .event-price-map .pr-name  { width: 62%; }
            .event-price-map .pr-price { width: 38%; }
        }
    </style>
CSS;

    return $css . '<div class="wrapper event-price-map">' . $table_html . '</div>';
}
