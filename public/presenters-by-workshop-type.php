<?php
/**
 * Speakers per workshop type accordion.
 *
 * Usage as shortcode:
 * [presenters_by_workshop_type event_uid="xxxx-2026" lang="de" type="3"]
 *
 * The "type" attribute is optional and takes one or more workshop type
 * IDs (comma separated). When omitted, all workshop types of the event
 * are rendered.
 *
 * (grouped by type with an <h2> heading, one accordion per group,
 * one accordion item per speaker with photo + bio in the body)
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

function presenters_by_workshop_type_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
            'type'      => '',
        ),
        $atts,
        'presenters_by_workshop_type'
    );

    $event_uid = sanitize_text_field((string) $atts['event_uid']);
    $lang      = sanitize_key((string) $atts['lang']);
    $type_ids  = array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $atts['type'])))));

    if ($event_uid === '') {
        return '';
    }

    // ── 1. Daten sammeln ─────────────────────────────────────────────────────

    $workshops_obj  = new Evtmgr_Workshops();
    $presenters_obj = new Evtmgr_Presenters();

    $workshop_types = $workshops_obj->get_workshop_types_for_output($event_uid, $lang);

    if (empty($workshop_types)) {
        return '';
    }

    $upload_dir     = wp_upload_dir();
    $upload_baseurl = rtrim((string) ($upload_dir['baseurl'] ?? ''), '/');

    $groups         = array();
    $group_index    = 0;

    foreach ($workshop_types as $workshop_type) {
        $type_id   = absint($workshop_type['id'] ?? 0);
        $type_name = trim((string) ($workshop_type['str_type_name'] ?? ''));

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

        $speakers      = array();
        $speaker_index = 0;

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

            $workshop_title = trim((string) ($workshop['str_workshop_title'] ?? ''));
            $weekday_name   = presenters_by_workshop_type_weekday_name($workshop['dtm_day'] ?? '', $lang);
            $day_time_parts = array_filter(array($weekday_name, $time_label));
            $day_time_label = implode(', ', $day_time_parts);

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

                $employer    = trim((string) ($presenter['str_employer']    ?? ''));
                $job_title   = trim((string) ($presenter['str_job_title']   ?? ''));
                $institution = trim((string) ($presenter['str_institution'] ?? ''));

                $sub_parts = array_filter(array($job_title, $institution !== '' ? $institution : $employer));
                $sub_line  = implode(', ', $sub_parts);

                $image_url = '';
                $image_raw = trim((string) ($presenter['str_person_image'] ?? ''));
                if ($image_raw !== '' && $upload_baseurl !== '') {
                    $image_url = $upload_baseurl . '/bgf-2026/' . ltrim($image_raw, '/');
                }

                $bio_raw  = trim((string) ($presenter['mem_presenter_text'] ?? ''));
                $bio_html = $bio_raw !== '' ? wp_kses_post($bio_raw) : '';

                $speakers[] = array(
                    'heading_id'     => esc_attr('ks-heading-'  . $type_id . '-' . $group_index . '-' . $speaker_index),
                    'collapse_id'    => esc_attr('ks-collapse-' . $type_id . '-' . $group_index . '-' . $speaker_index),
                    'full_name'      => esc_html($full_name),
                    'sub_line'       => esc_html($sub_line),
                    'workshop_title' => esc_html($workshop_title),
                    'day_time_label' => esc_html($day_time_label),
                    'image_url'      => esc_url($image_url),
                    'bio_html'       => $bio_html,
                    'sort_last_name' => (string) ($presenter['str_last_name']  ?? ''),
                    'sort_first_name'=> (string) ($presenter['str_first_name'] ?? ''),
                );

                $speaker_index++;
            }
        }

        if (empty($speakers)) {
            continue;
        }

        usort($speakers, function (array $a, array $b) {
            $cmp = strcasecmp($a['sort_last_name'], $b['sort_last_name']);

            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp($a['sort_first_name'], $b['sort_first_name']);
        });

        $groups[] = array(
            'group_id'   => esc_attr('ks-group-' . $group_index),
            'type_name'  => esc_html($type_name),
            'speakers'   => $speakers,
        );

        $group_index++;
    }

    if (empty($groups)) {
        return '';
    }

    // ── 2. Ausgabe als Heredoc ───────────────────────────────────────────────

    $groups_html = implode('', array_map(function (array $group) {
        $group_id  = $group['group_id'];
        $type_name = $group['type_name'];

        $items_html = implode('', array_map(function (array $sp) use ($group_id) {
            $heading_id  = $sp['heading_id'];
            $collapse_id = $sp['collapse_id'];
            $full_name   = $sp['full_name'];
            $btn_class   = 'accordion-button collapsed';
            $expanded    = 'false';
            $coll_class  = 'accordion-collapse collapse';

            $title_html = '';
            if ($sp['workshop_title'] !== '' || $sp['day_time_label'] !== '') {
                $day_time_html = $sp['day_time_label'] !== ''
                    ? "<br>\n                        <span style=\"font-weight:normal;\">{$sp['day_time_label']}</span>"
                    : '';
                $title_html = "<h4 class=\"h1 mt-3 mb-5\">{$sp['workshop_title']}{$day_time_html}</h4>";
            }

            $meta_html = '';
            if ($sp['sub_line'] !== '') {
                $meta_html .= "<p class=\"ks-speaker-meta mb-1\">{$sp['sub_line']}</p>";
            }

            $image_html = '';
            if ($sp['image_url'] !== '') {
                $image_html = "<img src=\"{$sp['image_url']}\" alt=\"{$full_name}\" class=\"img-fluid float-start ms-2 me-5 mb-3\" style=\"width:250px;height:250px;object-fit:cover;border-radius:50%;\">";
            }

            $bio_html = $sp['bio_html'] !== '' ? "<div class=\"ks-speaker-bio\">{$sp['bio_html']}</div>" : '';

            return <<<HTML

            <div class="accordion-item">
                <h3 class="accordion-header m-0" id="{$heading_id}">
                    <button class="{$btn_class}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{$collapse_id}"
                            aria-expanded="{$expanded}"
                            aria-controls="{$collapse_id}">
                        {$full_name}
                    </button>
                </h3>
                <div id="{$collapse_id}"
                     class="{$coll_class}"
                     aria-labelledby="{$heading_id}"
                     data-bs-parent="#{$group_id}">
                    <div class="accordion-body clearfix">
                        {$title_html}
                        {$meta_html}
                        {$image_html}
                        {$bio_html}
                    </div>
                </div>
            </div>
        HTML;
        }, $group['speakers']));

        return <<<HTML

        <div class="anlass-group mb-5">
            <h2 class="display-x mt-5">{$type_name}</h2>
            <div class="accordion event-keynote-speakers" id="{$group_id}">{$items_html}
            </div>
        </div>
        HTML;
    }, $groups));

    return <<<HTML
    <style>
        .event-keynote-speakers {
            --bs-accordion-btn-icon-width: 1.75rem;
            --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23212529' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%230d6efd' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        }
    </style>
    <div class="wrapper event-workshop-type-speakers">{$groups_html}
    </div>
HTML;
}
