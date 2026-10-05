<?php
/**
 * Event key figures / Eckdaten.
 *
 * Usage as shortcode:
 * [event_keyfigures event_uid="xxxx-2026" lang="de"]
 *
 * Renders the event's headline data via Evtmgr_Pdf_Content: title (<h1>),
 * subtitle, description (rich text), then a definition list with the event
 * date, "Anmeldung ab" (dtm_registration_opened) and "Anmeldeschluss"
 * (dtm_registration_closed). Empty fields are skipped. CSS is inlined and
 * scoped to .event-keyfigures.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-evtmgr-pdf-content.php';

add_action('init', function () {
    add_shortcode('event_keyfigures', 'event_registration_event_keyfigures_shortcode');
});

/**
 * The facts row (Datum / Anmeldung ab / Anmeldeschluss) as <dl>, '' when all
 * three are empty. Shared by [event_keyfigures] and the step-1 intro of
 * [event_registration]; needs event_registration_keyfigures_css() on the page.
 */
function event_registration_keyfigures_facts_html(Evtmgr_Pdf_Content $content) {
    $facts = array(
        'Datum'          => $content->get_event_date(),
        'Anmeldung ab'   => $content->get_event_registration_opened(),
        'Anmeldeschluss' => $content->get_event_registration_closed(),
    );

    $facts_rows = '';
    foreach ($facts as $label => $value) {
        if (trim((string) $value) === '') {
            continue;
        }
        $facts_rows .= '<div class="event-keyfigures__fact">'
            . '<dt class="event-keyfigures__fact-label">' . esc_html($label) . '</dt>'
            . '<dd class="event-keyfigures__fact-value">' . $value . '</dd>'
            . '</div>';
    }

    return $facts_rows !== '' ? '<dl class="event-keyfigures__facts">' . $facts_rows . '</dl>' : '';
}

/** Inline CSS for .event-keyfigures — printed once per page. */
function event_registration_keyfigures_css() {
    static $printed = false;

    if ($printed) {
        return '';
    }
    $printed = true;

    return <<<CSS
    <style>
        .event-keyfigures { margin: 1.5rem 0; }

        .event-keyfigures__title { margin: 0 0 0.5rem; }

        .event-keyfigures__subtitle {
            margin: 0 0 1rem;
            font-size: 1.25rem;
            font-weight: 300;
            color: #444444;
        }

        .event-keyfigures__description { margin: 0 0 1.5rem; }

        .event-keyfigures__facts {
            margin: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem 1.5rem;
            border-top: 2px solid #000000;
            padding-top: 1rem;
        }

        .event-keyfigures__fact { margin: 0; }

        .event-keyfigures__fact-label {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #666666;
        }

        .event-keyfigures__fact-value {
            margin: 0.15rem 0 0;
            font-size: 1.1rem;
        }
    </style>
CSS;
}

function event_registration_event_keyfigures_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
        ),
        $atts,
        'event_keyfigures'
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

    $title       = $content->get_event_title();
    $subtitle    = $content->get_event_subtitle();
    $description  = $content->get_event_description();

    $title_html    = $title !== ''             ? '<h1 class="event-keyfigures__title">' . $title . '</h1>' : '';
    $subtitle_html = $subtitle !== ''          ? '<p class="event-keyfigures__subtitle">' . $subtitle . '</p>' : '';
    $desc_html     = trim($description) !== '' ? '<div class="event-keyfigures__description">' . $description . '</div>' : '';
    $facts_html    = event_registration_keyfigures_facts_html($content);

    if ($title_html === '' && $subtitle_html === '' && $desc_html === '' && $facts_html === '') {
        return '';
    }

    return event_registration_keyfigures_css() . '<div class="wrapper event-keyfigures">'
        . $title_html
        . $subtitle_html
        . $desc_html
        . $facts_html
        . '</div>';
}
