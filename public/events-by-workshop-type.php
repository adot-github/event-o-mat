<?php
/**
 * Events (workshops) per workshop type accordion.
 *
 * Usage as shortcode:
 * [events_by_workshop_type event_uid="xxxx-2026" lang="de" type="3"]
 *
 * The "type" attribute is optional and takes one or more workshop type
 * IDs (comma separated). When omitted, all workshop types of the event
 * are rendered.
 *
 * Grouped by type with an <h2> heading, one accordion per group,
 * one accordion item per event/workshop (the workshop title is the
 * accordion title). The body shows the short + long description,
 * day/time, the presenters with photo, and a link to the workshop PDF
 * when one exists.
 */

if (!defined('ABSPATH')) {
    exit;
}

$_ks_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_ks_classes_dir . 'class-helpers.php';
require_once $_ks_classes_dir . 'class-evtmgr-workshops.php';
require_once $_ks_classes_dir . 'class-evtmgr-presenters.php';

add_action('init', function () {
    add_shortcode('events_by_workshop_type', 'events_by_workshop_type_shortcode');
});

function events_by_workshop_type_weekday_name($date_raw, $lang) {
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

    $names     = $weekday_names[$lang] ?? $weekday_names['de'];
    $day_index = ((int) date('N', $timestamp)) - 1;

    return $names[$day_index] ?? '';
}

function events_by_workshop_type_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
            'type'      => '',
        ),
        $atts,
        'events_by_workshop_type'
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

    $pdf_subfolder = 'workshop-booking-lists';
    $pdf_basedir   = get_stylesheet_directory()     . '/db-custom/event-registration/file-storage/' . sanitize_file_name($pdf_subfolder) . '/' . sanitize_file_name($event_uid) . '/';
    $pdf_baseurl   = get_stylesheet_directory_uri() . '/db-custom/event-registration/file-storage/' . rawurlencode($pdf_subfolder) . '/' . rawurlencode($event_uid) . '/';

    $groups      = array();
    $group_index = 0;

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

        $events         = array();
        $event_index    = 0;

        foreach ($workshops as $workshop) {
            $workshop_id      = absint($workshop['id'] ?? 0);
            $workshop_title   = trim((string) ($workshop['str_workshop_title'] ?? ''));
            $workshop_subtitle = trim((string) ($workshop['str_workshop_subtitle'] ?? ''));

            if ($workshop_id <= 0 || $workshop_title === '') {
                continue;
            }

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

            $weekday_name   = events_by_workshop_type_weekday_name($workshop['dtm_day'] ?? '', $lang);
            $day_time_parts = array_filter(array($weekday_name, $time_label));
            $day_time_label = implode(', ', $day_time_parts);

            $description      = trim((string) ($workshop['mem_workshop_description'] ?? ''));
            $description_long = trim((string) ($workshop['mem_workshop_description_long'] ?? ''));

            $pdf_file_name = $workshops_obj->workshop_pdf_file_name($workshop);
            $pdf_url       = '';
            if ($pdf_file_name !== '' && file_exists($pdf_basedir . $pdf_file_name)) {
                $pdf_url = $pdf_baseurl . rawurlencode($pdf_file_name);
            }

            $presenters      = $presenters_obj->get_presenters_by_workshop_id($workshop_id, $lang);
            $presenter_items = array();

            foreach ($presenters as $presenter) {
                $full_name = trim(
                    ((string) ($presenter['str_academic_title'] ?? '') !== '' ? $presenter['str_academic_title'] . ' ' : '') .
                    (string) ($presenter['str_first_name'] ?? '') . ' ' .
                    (string) ($presenter['str_last_name']  ?? '')
                );

                if ($full_name === '') {
                    continue;
                }

                $institution = trim((string) ($presenter['str_institution'] ?? ''));
                $bio_raw     = trim((string) ($presenter['mem_presenter_text'] ?? ''));

                $image_url = '';
                $image_raw = trim((string) ($presenter['str_person_image'] ?? ''));
                if ($image_raw !== '' && $upload_baseurl !== '') {
                    $image_url = $upload_baseurl . '/bgf-2026/' . ltrim($image_raw, '/');
                }

                $presenter_items[] = array(
                    'full_name'    => esc_html($full_name),
                    'institution'  => esc_html($institution),
                    'bio_html'     => $bio_raw !== '' ? wp_kses_post($bio_raw) : '',
                    'image_url'    => esc_url($image_url),
                );
            }

            $events[] = array(
                'heading_id'        => esc_attr('ks-heading-'  . $type_id . '-' . $group_index . '-' . $event_index),
                'collapse_id'       => esc_attr('ks-collapse-' . $type_id . '-' . $group_index . '-' . $event_index),
                'workshop_title'    => esc_html($workshop_title),
                'workshop_subtitle' => esc_html($workshop_subtitle),
                'description'       => $description !== '' ? wp_kses_post($description) : '',
                'description_long'  => $description_long !== '' ? wp_kses_post($description_long) : '',
                'day_time_label'    => esc_html($day_time_label),
                'pdf_url'           => $pdf_url !== '' ? esc_url($pdf_url) : '',
                'presenters'        => $presenter_items,
            );

            $event_index++;
        }

        if (empty($events)) {
            continue;
        }

        $groups[] = array(
            'group_id'  => esc_attr('ks-group-' . $group_index),
            'type_name' => esc_html($type_name),
            'events'    => $events,
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

        $items_html = implode('', array_map(function (array $ev) use ($group_id) {
            $heading_id  = $ev['heading_id'];
            $collapse_id = $ev['collapse_id'];
            $btn_class   = 'accordion-button collapsed';
            $expanded    = 'false';
            $coll_class  = 'accordion-collapse collapse';

            $description_html = $ev['description'] !== ''
                ? "<div class=\"lead\">{$ev['description']}</div>"
                : '';

            $description_long_html = $ev['description_long'] !== ''
                ? "<p>{$ev['description_long']}</p>"
                : '';

            $day_time_html = $ev['day_time_label'] !== ''
                ? "<p class=\"ks-event-daytime\">{$ev['day_time_label']}</p>"
                : '';

            $subtitle_html = $ev['workshop_subtitle'] !== ''
                ? "<br>\n                        <span style=\"font-weight:normal;\">{$ev['workshop_subtitle']}</span>"
                : '';

            $presenters_html = implode('', array_map(function (array $p) {
                $image_html = $p['image_url'] !== ''
                    ? "<img src=\"{$p['image_url']}\" alt=\"{$p['full_name']}\" class=\"img-fluid float-start ms-2 me-4 mb-3\" style=\"width:250px;height:250px;object-fit:cover;border-radius:50%;\">"
                    : '';

                $institution_html = $p['institution'] !== '' ? "<p class=\"mb-1\">{$p['institution']}</p>" : '';
                $bio_html         = $p['bio_html'] !== '' ? "<div class=\"ks-speaker-bio\">{$p['bio_html']}</div>" : '';

                return <<<HTML

                    <div class="ks-event-presenter clearfix mb-4">
                        {$image_html}
                        <h4 class="mb-1">{$p['full_name']}</h4>
                        {$institution_html}
                        {$bio_html}
                    </div>
HTML;
            }, $ev['presenters']));

            $pdf_html = $ev['pdf_url'] !== ''
                ? "<p class=\"ks-event-pdf\"><a href=\"{$ev['pdf_url']}\" target=\"_blank\" rel=\"noopener\">PDF zu diesem Angebot</a></p>"
                : '';

            return <<<HTML

            <div class="accordion-item">
                <h2 class="accordion-header m-0" id="{$heading_id}">
                    <button class="{$btn_class}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{$collapse_id}"
                            aria-expanded="{$expanded}"
                            aria-controls="{$collapse_id}">
                        <span>{$ev['workshop_title']}{$subtitle_html}</span>
                    </button>
                </h2>
                <div id="{$collapse_id}"
                     class="{$coll_class}"
                     aria-labelledby="{$heading_id}"
                     data-bs-parent="#{$group_id}">
                    <div class="accordion-body">
                        {$description_html}
                        {$description_long_html}
                        {$day_time_html}
                        {$presenters_html}
                        {$pdf_html}
                    </div>
                </div>
            </div>
HTML;
        }, $group['events']));

        return <<<HTML

        <div class="anlass-group mb-5">
            <h2 class="display-x mt-5">{$type_name}</h2>
            <div class="accordion event-workshop-type-events" id="{$group_id}">{$items_html}
            </div>
        </div>
HTML;
    }, $groups));

    return <<<HTML
    <style>
        .event-workshop-type-events {
            --bs-accordion-btn-icon-width: 1.75rem;
            --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23212529' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%230d6efd' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        }
    </style>
    <div class="wrapper event-workshop-type-events-wrapper">{$groups_html}
    </div>
HTML;
}
