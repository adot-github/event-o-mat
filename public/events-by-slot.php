<?php
/**
 * All offers of an event by time: one heading per day, one sub heading per
 * time block ("9.00–10.00 Uhr · Keynote II"), the offers below as cards or
 * as accordion — the same cards / accordion items as [events_with_filters]
 * (incl. like button).
 *
 * Usage as shortcode:
 * [events_by_slot event_uid="xxxx-2026" lang="de" type_of_display="cards"]
 *
 * - type_of_display: cards (default) / accordion (one accordion per time block)
 */

if (!defined('ABSPATH')) {
    exit;
}

$_ebs_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_ebs_classes_dir . 'class-helpers.php';
require_once $_ebs_classes_dir . 'class-evtmgr-workshops.php';
require_once $_ebs_classes_dir . 'class-evtmgr-wordings.php';

add_action('init', function () {
    add_shortcode('events_by_slot', 'events_by_slot_shortcode');
    add_shortcode('liked_events', 'liked_events_shortcode');
});

/**
 * [liked_events event_uid="xxxx-2026" lang="de" type_of_display="accordion"]
 *
 * The visitor's "Merkliste": same display as [events_by_slot] (day → time
 * block → accordion by default), limited to the liked offers. Unliking an
 * offer on this page removes it from the list (js/workshop-likes.js).
 */
function liked_events_shortcode($atts = array(), $content = null, $tag = 'liked_events') {
    return events_by_slot_shortcode($atts, $content, 'liked_events', true);
}

/** "09:00:00" → "9.00" */
function events_by_slot_format_time($time_raw) {
    $time_raw = trim((string) $time_raw);

    if (strlen($time_raw) < 5) {
        return '';
    }

    return (int) substr($time_raw, 0, 2) . '.' . substr($time_raw, 3, 2);
}

function events_by_slot_shortcode($atts = array(), $content = null, $tag = 'events_by_slot', $only_liked = false) {
    $atts = shortcode_atts(
        array(
            'event_uid'       => '',
            'lang'            => 'de',
            'type_of_display' => $only_liked ? 'accordion' : 'cards',
        ),
        $atts,
        $only_liked ? 'liked_events' : 'events_by_slot'
    );

    $event_uid       = sanitize_text_field((string) $atts['event_uid']);
    $lang            = sanitize_key((string) $atts['lang']);
    $type_of_display = sanitize_key((string) $atts['type_of_display']) === 'accordion' ? 'accordion' : 'cards';

    $empty_liked_html = '<div class="liked-events__empty alert alert-light">'
        . 'Ihre Merkliste ist leer. Setzen Sie Angebote mit dem Merken-Symbol auf Ihre Merkliste.'
        . '</div>';

    // Unique ids per shortcode instance (several blocks on one page).
    static $ebs_instance = 0;
    $ebs_instance++;

    Event_Registration_Helpers::enqueue_bootstrap($event_uid, true);

    if ($event_uid === '' || !function_exists('events_with_filters_render_workshop_html')) {
        return '';
    }

    $workshops_obj = new Evtmgr_Workshops();
    $wordings      = (new Evtmgr_Wordings())->get_wordings($lang, $event_uid);

    $rows = $workshops_obj->get_workshops_with_timezone_for_event($event_uid, $lang);

    if (empty($rows)) {
        return $only_liked ? '<div class="wrapper events-by-slot liked-events">' . $empty_liked_html . '</div>' : '';
    }

    // "Merkliste": like button on every card, preset from the visitor's likes.
    $liked_workshop_ids = function_exists('evtmgr_liked_workshop_ids_for_visitor')
        ? evtmgr_liked_workshop_ids_for_visitor($event_uid)
        : array();
    if (function_exists('event_registration_enqueue_workshop_likes')) {
        event_registration_enqueue_workshop_likes();
    }

    // ── Gruppieren: Tag → Zeitblock → Karten ────────────────────────────────
    $days = array();

    foreach ($rows as $row) {
        $workshop_id = absint($row['id'] ?? 0);

        if ($workshop_id <= 0) {
            continue;
        }

        if ($only_liked && !in_array($workshop_id, $liked_workshop_ids, true)) {
            continue;
        }

        $day_key ='d' . absint($row['day_id'] ?? 0) . '-' . (string) ($row['dtm_day'] ?? '');
        $tz_key  = 't' . absint($row['timezone_id'] ?? 0);

        if (!isset($days[$day_key])) {
            $day_name = trim((string) ($row['str_day_name'] ?? ''));
            if ($day_name === '' && !empty($row['dtm_day'])) {
                $day_name = wp_date('l, j. F', strtotime((string) $row['dtm_day']));
            }
            $days[$day_key] = array('name' => $day_name, 'blocks' => array());
        }

        if (!isset($days[$day_key]['blocks'][$tz_key])) {
            $from = events_by_slot_format_time($row['dtm_time_from'] ?? '');
            $to   = events_by_slot_format_time($row['dtm_time_to'] ?? '');
            $time = $from !== '' && $to !== '' && $from !== $to ? $from . '–' . $to . ' Uhr' : ($from !== '' ? $from . ' Uhr' : '');

            $days[$day_key]['blocks'][$tz_key] = array(
                'time'  => $time,
                'name'  => trim((string) ($row['str_timezone_name'] ?? '')),
                'cards' => '',
            );
        }

        $workshop_html = events_with_filters_render_workshop_html(
            $workshop_id,
            $lang,
            $wordings,
            in_array($workshop_id, $liked_workshop_ids, true),
            $type_of_display === 'accordion' ? 'accordion' : 'card'
        );

        if ($workshop_html === '') {
            continue;
        }

        $days[$day_key]['blocks'][$tz_key]['cards'] .= $type_of_display === 'accordion'
            ? events_with_filters_accordion_item_html('ebs' . $ebs_instance . '-' . $workshop_id, $row, $workshop_html)
            : events_with_filters_card_item_html($workshop_html);
    }

    // ── Ausgabe ──────────────────────────────────────────────────────────────
    $html = '';

    foreach ($days as $day) {
        $blocks_html = '';

        foreach ($day['blocks'] as $block) {
            if ($block['cards'] === '') {
                continue;
            }

            $heading_parts = array_filter(array(esc_html($block['time']), esc_html($block['name'])));
            $heading       = implode(' · ', $heading_parts);

            $items_html = $type_of_display === 'accordion'
                ? "<div class=\"events-with-filters-wrapper events-with-filters-accordion-wrapper\">\n                <div class=\"accordion events-with-filters-accordion\">{$block['cards']}\n                </div>\n            </div>"
                : "<div class=\"events-with-filters-wrapper mx-n2\">\n                <div class=\"row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mx-0\">{$block['cards']}\n                </div>\n            </div>";

            $blocks_html .= <<<HTML

        <section class="events-by-slot__block">
            <h3 class="events-by-slot__time">{$heading}</h3>
            {$items_html}
        </section>
HTML;
        }

        if ($blocks_html === '') {
            continue;
        }

        $day_heading = $day['name'] !== '' ? '<h2 class="events-by-slot__day">' . esc_html($day['name']) . '</h2>' : '';

        $html .= "\n    <div class=\"events-by-slot__day-group\">{$day_heading}{$blocks_html}\n    </div>";
    }

    // Merkliste: always rendered (also empty) so JS can show the empty notice
    // after the last offer was removed.
    $wrapper_class = $only_liked ? 'wrapper events-by-slot liked-events' : 'wrapper events-by-slot';
    $empty_html    = $only_liked ? $empty_liked_html : '';

    if ($html === '' && !$only_liked) {
        return '';
    }

    if ($only_liked && $html !== '') {
        $empty_html = str_replace('liked-events__empty', 'liked-events__empty d-none', $empty_liked_html);
    }

    return <<<HTML
    <style>
        .events-by-slot__day-group { margin-bottom: 3rem; }
        .events-by-slot__day { margin: 2.5rem 0 1rem; font-size: 2rem; }
        .events-by-slot__block { margin-bottom: 2.5rem; }
        .events-by-slot__time {
            margin: 1.5rem 0 1rem;
            padding-bottom: .5rem;
            border-bottom: 2px solid #000000;
            font-size: 1.25rem;
        }
    </style>
    <div class="{$wrapper_class}">{$empty_html}{$html}
    </div>
HTML;
}
