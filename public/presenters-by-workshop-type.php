<?php
/**
 * Speakers per workshop type.
 *
 * Usage as shortcode:
 * [presenters_by_workshop_type event_uid="xxxx-2026" lang="de" type="3" group_by_type="1" type_of_display="accordion"]
 *
 * - type:            optional, one or more workshop type IDs (comma
 *                    separated). When omitted, all workshop types are used.
 * - group_by_type:   1 (default) = one section per workshop type with an
 *                    <h2> heading; 0 = one alphabetical list of all speakers.
 * - type_of_display: accordion (default) = one accordion item per speaker
 *                    with photo + bio + offers in the body;
 *                    cards = one card per speaker with photo, name,
 *                    function and offers (no bio / description).
 *
 * A speaker with several offers appears once per section (once overall when
 * ungrouped) and lists all offers under "Meine Angebote".
 */

if (!defined('ABSPATH')) {
    exit;
}

$_ks_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_ks_classes_dir . 'class-helpers.php';
require_once $_ks_classes_dir . 'class-evtmgr-workshops.php';
require_once $_ks_classes_dir . 'class-evtmgr-presenters.php';

add_action('init', function () {
    add_shortcode('presenters_by_workshop_type', 'presenters_by_workshop_type_shortcode');
});

function presenters_by_workshop_type_weekday_name($date_raw, $lang) {
    $date_raw = trim((string) $date_raw);

    if ($date_raw === '') {
        return '';
    }

    $timestamp = strtotime($date_raw);

    if ($timestamp === false) {
        return '';
    }

    $weekday_names = array(
        'de' => array('Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'),
        'fr' => array('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'),
        'it' => array('lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'),
    );

    $names      = $weekday_names[$lang] ?? $weekday_names['de'];
    $day_index  = ((int) date('N', $timestamp)) - 1;

    return $names[$day_index] ?? '';
}

/** Sort speakers by last name, then first name. */
function presenters_by_workshop_type_sort_speakers(array &$speakers) {
    usort($speakers, function (array $a, array $b) {
        $cmp = strcasecmp($a['sort_last_name'], $b['sort_last_name']);

        if ($cmp !== 0) {
            return $cmp;
        }

        return strcasecmp($a['sort_first_name'], $b['sort_first_name']);
    });
}

/** "Meine Angebote" block: title, description (optional), day/time and like button per offer. */
function presenters_by_workshop_type_offers_html(array $offers, $with_description) {
    $html = '';

    foreach ($offers as $offer) {
        $like_html = function_exists('evtmgr_like_button_html')
            ? evtmgr_like_button_html($offer['workshop_id'], $offer['event_uid'], $offer['is_liked'])
            : '';

        if ($offer['workshop_title'] !== '') {
            $html .= "<h4 class=\"h3 mt-3 mb-0\">{$offer['workshop_title']}</h4>";
        }

        if ($with_description) {
            $description_parts = array_filter(array(
                $offer['workshop_description'],
                $offer['workshop_description_long'],
            ));
            if (!empty($description_parts)) {
                $html .= '<div class="ks-workshop-description mt-2">' . implode('', $description_parts) . '</div>';
            }
        }

        if ($offer['day_time_label'] !== '') {
            // Like button directly below → no bottom margin, the button sits close.
            $daytime_class = $like_html !== '' ? 'ks-workshop-daytime mt-2 mb-0' : 'ks-workshop-daytime mt-2';
            $html .= "<p class=\"{$daytime_class}\">{$offer['day_time_label']}</p>";
        }

        if ($like_html !== '') {
            $html .= "<div class=\"ks-workshop-like\">{$like_html}</div>";
        }
    }

    return $html;
}

/** One accordion (one item per speaker) for a group. */
function presenters_by_workshop_type_accordion_html(array $speakers, $group_id) {
    $items_html = '';

    foreach ($speakers as $sp) {
        $heading_id  = $sp['heading_id'];
        $collapse_id = $sp['collapse_id'];
        $full_name   = $sp['full_name'];

        // Below the photo: academic title + full name, then the function.
        $meta_html = "<p class=\"ks-speaker-name mb-0\">{$full_name}</p>";
        if ($sp['sub_line'] !== '') {
            $meta_html .= "<p class=\"ks-speaker-meta mb-1\">{$sp['sub_line']}</p>";
        }

        $image_html = $sp['image_url'] !== ''
            ? "<img src=\"{$sp['image_url']}\" alt=\"{$full_name}\" class=\"img-fluid mb-3\" style=\"width:250px;height:250px;object-fit:cover;border-radius:50%;\">"
            : '';

        $bio_html    = $sp['bio_html'] !== '' ? "<div class=\"ks-speaker-bio\">{$sp['bio_html']}</div>" : '';
        $offers_html = presenters_by_workshop_type_offers_html($sp['offers'], true);

        $items_html .= <<<HTML

            <div class="accordion-item">
                <h3 class="accordion-header m-0" id="{$heading_id}">
                    <button class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{$collapse_id}"
                            aria-expanded="false"
                            aria-controls="{$collapse_id}">
                        {$full_name}
                    </button>
                </h3>
                <div id="{$collapse_id}"
                     class="accordion-collapse collapse"
                     aria-labelledby="{$heading_id}"
                     data-bs-parent="#{$group_id}">
                    <div class="accordion-body clearfix">
                        <div class="row">
                            <div class="col-12 col-lg-4 col-md-5 text-center mt-4">
                                {$image_html}
                                {$meta_html}
                            </div>
                            <div class="col-12 col-lg-8 col-md-7">
                                    <div class="lead mt-4">
                                    {$bio_html}
                                    </div>
                                    <h3 class="mt-4">Meine Angebote</h3>
                                    {$offers_html}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
HTML;
    }

    return "<div class=\"accordion event-keynote-speakers\" id=\"{$group_id}\">{$items_html}\n            </div>";
}

/** A responsive card grid (one card per speaker) for a group. */
function presenters_by_workshop_type_cards_html(array $speakers) {
    $cards_html = '';

    foreach ($speakers as $sp) {
        $full_name = $sp['full_name'];

        $image_html = $sp['image_url'] !== ''
            ? "<img src=\"{$sp['image_url']}\" alt=\"{$full_name}\" class=\"ks-speaker-card__image\">"
            : '';

        $meta_html = $sp['sub_line'] !== ''
            ? "<p class=\"ks-speaker-meta mb-2\">{$sp['sub_line']}</p>"
            : '';

        $offers_html = presenters_by_workshop_type_offers_html($sp['offers'], false);

        $cards_html .= <<<HTML

            <div class="col">
                <div class="card h-100 ks-speaker-card">
                    <div class="card-body">
                        {$image_html}
                        <h3 class="ks-speaker-card__name">{$full_name}</h3>
                        {$meta_html}
                        <div class="ks-speaker-card__offers">{$offers_html}</div>
                    </div>
                </div>
            </div>
HTML;
    }

    return "<div class=\"row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4\">{$cards_html}\n            </div>";
}

function presenters_by_workshop_type_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid'       => '',
            'lang'            => 'de',
            'type'            => '',
            'group_by_type'   => '1',
            'type_of_display' => 'accordion',
        ),
        $atts,
        'presenters_by_workshop_type'
    );

    $event_uid       = sanitize_text_field((string) $atts['event_uid']);
    $lang            = sanitize_key((string) $atts['lang']);
    $type_ids        = array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $atts['type'])))));
    $group_by_type   = !in_array(strtolower(trim((string) $atts['group_by_type'])), array('0', 'false', 'no', 'nein'), true);
    $type_of_display = sanitize_key((string) $atts['type_of_display']) === 'cards' ? 'cards' : 'accordion';

    Event_Registration_Helpers::enqueue_bootstrap($event_uid, true);

    if ($event_uid === '') {
        return '';
    }

    // Unique ids per shortcode instance (several blocks on one page).
    static $ks_instance = 0;
    $ks_instance++;
    $id_prefix = 'ks' . $ks_instance;

    // ── 1. Daten sammeln ─────────────────────────────────────────────────────

    $workshops_obj  = new Evtmgr_Workshops();
    $presenters_obj = new Evtmgr_Presenters();

    $workshop_types = $workshops_obj->get_workshop_types_for_output($event_uid, $lang);

    if (empty($workshop_types)) {
        return '';
    }

    $upload_dir     = wp_upload_dir();
    $upload_baseurl = rtrim((string) ($upload_dir['baseurl'] ?? ''), '/');

    // "Merkliste": like button per offer, preset from the visitor's likes.
    $liked_workshop_ids = function_exists('evtmgr_liked_workshop_ids_for_visitor')
        ? evtmgr_liked_workshop_ids_for_visitor($event_uid)
        : array();
    if (function_exists('event_registration_enqueue_workshop_likes')) {
        event_registration_enqueue_workshop_likes();
    }

    // Ungrouped: everything goes into one heading-less group.
    $groups = array();

    foreach ($workshop_types as $workshop_type) {
        $type_id          = absint($workshop_type['id'] ?? 0);
        $type_name        = trim((string) ($workshop_type['str_type_name'] ?? ''));
        $type_name_plural = trim((string) ($workshop_type['str_type_name_plural'] ?? ''));

        if ($type_id <= 0 || $type_name === '') {
            continue;
        }

        if (!empty($type_ids) && !in_array($type_id, $type_ids, true)) {
            continue;
        }

        $workshops = $workshops_obj->get_workshops_all_by_type($type_id, $event_uid, $lang);

        if (empty($workshops)) {
            continue;
        }

        // Section heading uses the plural type name, singular as fallback.
        $group_key = $group_by_type ? 'type-' . $type_id : 'all';

        if (!isset($groups[$group_key])) {
            $groups[$group_key] = array(
                'type_name' => $group_by_type ? esc_html($type_name_plural !== '' ? $type_name_plural : $type_name) : '',
                'speakers'  => array(),
            );
        }

        foreach ($workshops as $workshop) {
            $workshop_id   = absint($workshop['id'] ?? 0);
            $time_from_raw = trim((string) ($workshop['dtm_time_from'] ?? ''));
            $time_to_raw   = trim((string) ($workshop['dtm_time_to']   ?? ''));
            $time_from_fmt = strlen($time_from_raw) >= 5 ? substr($time_from_raw, 0, 5) : '';
            $time_to_fmt   = strlen($time_to_raw)   >= 5 ? substr($time_to_raw,   0, 5) : '';

            if ($time_from_fmt !== '' && $time_to_fmt !== '') {
                $time_label = $time_from_fmt . '–' . $time_to_fmt . ' Uhr';
            } elseif ($time_from_fmt !== '') {
                $time_label = $time_from_fmt . ' Uhr';
            } else {
                $time_label = '';
            }

            $weekday_name   = presenters_by_workshop_type_weekday_name($workshop['dtm_day'] ?? '', $lang);
            $day_time_label = implode(', ', array_filter(array($weekday_name, $time_label)));

            $workshop_description      = trim((string) ($workshop['mem_workshop_description'] ?? ''));
            $workshop_description_long = trim((string) ($workshop['mem_workshop_description_long'] ?? ''));

            $offer = array(
                'workshop_id'               => $workshop_id,
                'event_uid'                 => $event_uid,
                'is_liked'                  => in_array($workshop_id, $liked_workshop_ids, true),
                'workshop_title'            => esc_html(trim((string) ($workshop['str_workshop_title'] ?? ''))),
                'workshop_description'      => $workshop_description !== '' ? wp_kses_post($workshop_description) : '',
                'workshop_description_long' => $workshop_description_long !== '' ? wp_kses_post($workshop_description_long) : '',
                'day_time_label'            => esc_html($day_time_label),
            );

            $presenters = $presenters_obj->get_presenters_by_workshop_id($workshop_id, $lang);

            foreach ($presenters as $presenter) {
                $presenter_id = absint($presenter['id'] ?? 0);

                $full_name = trim(
                    ((string) ($presenter['str_academic_title'] ?? '') !== '' ? $presenter['str_academic_title'] . ' ' : '') .
                    (string) ($presenter['str_first_name'] ?? '') . ' ' .
                    (string) ($presenter['str_last_name']  ?? '')
                );

                if ($full_name === '') {
                    continue;
                }

                // Same person, further offer → add the offer, keep one entry.
                $speaker_key = $presenter_id > 0 ? 'p' . $presenter_id : 'n' . md5($full_name);

                if (isset($groups[$group_key]['speakers'][$speaker_key])) {
                    $known_ids = array_column($groups[$group_key]['speakers'][$speaker_key]['offers'], 'workshop_id');
                    if (!in_array($workshop_id, $known_ids, true)) {
                        $groups[$group_key]['speakers'][$speaker_key]['offers'][] = $offer;
                    }
                    continue;
                }

                $employer    = trim((string) ($presenter['str_employer']    ?? ''));
                $job_title   = trim((string) ($presenter['str_job_title']   ?? ''));
                $institution = trim((string) ($presenter['str_institution'] ?? ''));

                $sub_parts = array_filter(array($job_title, $institution !== '' ? $institution : $employer));
                $sub_line  = implode(', ', $sub_parts);

                $image_url = '';
                $image_raw = trim((string) ($presenter['str_person_image'] ?? ''));
                if ($image_raw !== '' && $upload_baseurl !== '') {
                    // str_person_image holds a path relative to the uploads dir
                    // (e.g. "fhnw-practice-day-2026/assets/presenter-images/p-961.jpg").
                    // Older rows may hold just a bare file name.
                    $image_rel = strpos($image_raw, '/') !== false
                        ? ltrim($image_raw, '/')
                        : $event_uid . '/assets/presenter-images/' . $image_raw;
                    $image_url = $upload_baseurl . '/' . $image_rel;
                }

                $bio_raw = trim((string) ($presenter['mem_presenter_text'] ?? ''));

                $groups[$group_key]['speakers'][$speaker_key] = array(
                    'full_name'       => esc_html($full_name),
                    'sub_line'        => esc_html($sub_line),
                    'image_url'       => esc_url($image_url),
                    'bio_html'        => $bio_raw !== '' ? wp_kses_post($bio_raw) : '',
                    'offers'          => array($offer),
                    'sort_last_name'  => (string) ($presenter['str_last_name']  ?? ''),
                    'sort_first_name' => (string) ($presenter['str_first_name'] ?? ''),
                );
            }
        }
    }

    // ── 2. Ausgabe ───────────────────────────────────────────────────────────

    $groups_html = '';
    $group_index = 0;

    foreach ($groups as $group) {
        $speakers = array_values($group['speakers']);

        if (empty($speakers)) {
            continue;
        }

        presenters_by_workshop_type_sort_speakers($speakers);

        $group_id = esc_attr($id_prefix . '-group-' . $group_index);

        foreach ($speakers as $speaker_index => &$sp) {
            $sp['heading_id']  = esc_attr($id_prefix . '-heading-'  . $group_index . '-' . $speaker_index);
            $sp['collapse_id'] = esc_attr($id_prefix . '-collapse-' . $group_index . '-' . $speaker_index);
        }
        unset($sp);

        $body_html = $type_of_display === 'cards'
            ? presenters_by_workshop_type_cards_html($speakers)
            : presenters_by_workshop_type_accordion_html($speakers, $group_id);

        $heading_html = $group['type_name'] !== ''
            ? "<h2 class=\"display-x mt-5\">{$group['type_name']}</h2>"
            : '';

        $groups_html .= <<<HTML

        <div class="anlass-group mb-5">
            {$heading_html}
            {$body_html}
        </div>
HTML;

        $group_index++;
    }

    if ($groups_html === '') {
        return '';
    }

    return <<<HTML
    <style>
        .event-keynote-speakers {
            --bs-accordion-btn-icon-width: 1.75rem;
            --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23212529' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%230d6efd' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        }

        .ks-workshop-like {
            margin: 0 0 1rem;
        }

        .ks-workshop-like .workshop-like-button {
            margin-top: .25rem;
        }

        .ks-speaker-name {
            font-weight: 700;
        }

        .ks-speaker-card__image {
            display: block;
            width: 220px;
            max-width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            margin: 0 auto 1rem;
            object-fit: cover;
            border-radius: 50%;
        }

        .ks-speaker-card__name {
            margin: 0 0 .25rem;
            font-size: 1.5rem;
        }

        .ks-speaker-card__offers .h3 {
            font-size: 1.1rem;
        }
    </style>
    <div class="wrapper event-workshop-type-speakers">{$groups_html}
    </div>
HTML;
}
