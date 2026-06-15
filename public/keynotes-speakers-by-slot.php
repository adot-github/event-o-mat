<?php
/**
 * Keynote speakers per slot accordion.
 *
 * Usage as shortcode:
 * [show_keynotespeakers_by_slot event_uid="xxxx-2026" lang="de"]
 */

if (!defined('ABSPATH')) {
    exit;
}

$_ks_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_ks_classes_dir . 'class-helpers.php';
require_once $_ks_classes_dir . 'class-evtmgr-slots.php';
require_once $_ks_classes_dir . 'class-evtmgr-workshops.php';
require_once $_ks_classes_dir . 'class-evtmgr-presenters.php';

add_action('init', function () {
    add_shortcode('show_keynotespeakers_by_slot', 'show_keynotespeakers_by_slot_shortcode');
});

function show_keynotespeakers_by_slot_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
        ),
        $atts,
        'show_keynotespeakers_by_slot'
    );

    $event_uid = sanitize_text_field((string) $atts['event_uid']);
    $lang      = sanitize_key((string) $atts['lang']);

    if ($event_uid === '') {
        return '';
    }

    // ── 1. Daten sammeln ─────────────────────────────────────────────────────

    $slots_obj      = new Evtmgr_Slots();
    $workshops_obj  = new Evtmgr_Workshops();
    $presenters_obj = new Evtmgr_Presenters();

    $slots = $slots_obj->get_slots_for_output($event_uid, $lang);

    if (empty($slots)) {
        return '';
    }

    $accordion_id_attr = esc_attr('ks-accordion-' . sanitize_html_class($event_uid));
    $slots_data        = array();
    $rendered_index    = 0;

    foreach ($slots as $slot) {
        $slot_id   = absint($slot['id'] ?? 0);
        $slot_name = trim((string) ($slot['str_slot_name'] ?? ''));

        if ($slot_id <= 0 || $slot_name === '') {
            continue;
        }

        $workshops = $workshops_obj->get_workshops_all_by_slot($slot_id, $event_uid, $lang);

        if (empty($workshops)) {
            continue;
        }

        $lines = array();

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

            $presenters    = $presenters_obj->get_presenters_by_workshop_id($workshop_id, $lang);
            $speaker_parts = array();

            foreach ($presenters as $presenter) {
                $name         = trim((string) ($presenter['str_first_name'] ?? '') . ' ' . (string) ($presenter['str_last_name'] ?? ''));
                $str_name     = trim((string) ($presenter['str_name']        ?? ''));
                $str_employer = trim((string) ($presenter['str_employer']    ?? ''));
                $institution  = trim((string) ($presenter['str_institution'] ?? ''));

                $suffix = array_filter(array($str_name, $str_employer));
                if (!empty($suffix)) {
                    $name = trim($name . ', ' . implode(', ', $suffix));
                }

                if ($name !== '' && $institution !== '') {
                    $speaker_parts[] = $name . ' (' . $institution . ')';
                } elseif ($name !== '') {
                    $speaker_parts[] = $name;
                }
            }

            $speaker_text = implode(' / ', $speaker_parts);

            if ($speaker_text === '') {
                $speaker_text = trim((string) ($workshop['str_workshop_title'] ?? ''));
            }

            if ($speaker_text === '') {
                continue;
            }

            $lines[] = $time_label !== '' ? $time_label . ': ' . $speaker_text : $speaker_text;
        }

        if (empty($lines)) {
            continue;
        }

        $is_first = ($rendered_index === 0);

        $slots_data[] = array(
            'heading_id'  => esc_attr('ks-heading-'  . $slot_id),
            'collapse_id' => esc_attr('ks-collapse-' . $slot_id),
            'slot_name'   => esc_html($slot_name),
            'btn_class'   => 'accordion-button' . ($is_first ? '' : ' collapsed'),
            'expanded'    => $is_first ? 'true' : 'false',
            'coll_class'  => 'accordion-collapse collapse' . ($is_first ? ' show' : ''),
            'lines'       => $lines,
        );

        $rendered_index++;
    }

    if (empty($slots_data)) {
        return '';
    }

    // ── 2. Ausgabe als Heredoc ───────────────────────────────────────────────

    $slots_html = implode('', array_map(function (array $sd) use ($accordion_id_attr) {
        $heading_id  = $sd['heading_id'];
        $collapse_id = $sd['collapse_id'];
        $slot_name   = $sd['slot_name'];
        $btn_class   = $sd['btn_class'];
        $expanded    = $sd['expanded'];
        $coll_class  = $sd['coll_class'];

        $items_html = implode('', array_map(function (string $line) {
            $li = esc_html($line);
            return "\n                        <li>{$li}</li>";
        }, $sd['lines']));

        return <<<HTML

        <div class="accordion-item">
            <h2 class="accordion-header m-0" id="{$heading_id}">
                <button class="{$btn_class}"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{$collapse_id}"
                        aria-expanded="{$expanded}"
                        aria-controls="{$collapse_id}">
                    {$slot_name}
                </button>
            </h2>
            <div id="{$collapse_id}"
                 class="{$coll_class}"
                 aria-labelledby="{$heading_id}"
                 data-bs-parent="#{$accordion_id_attr}">
                <div class="accordion-body">
                    <ul class="list-unstyled mb-0 keynote-speaker-list">{$items_html}
                    </ul>
                </div>
            </div>
        </div>
HTML;
    }, $slots_data));

    return <<<HTML
    <style>
        .event-keynote-speakers {
            --bs-accordion-btn-icon-width: 1.75rem;
            --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23212529' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%230d6efd' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        }
    </style>
    <div class="accordion event-keynote-speakers" id="{$accordion_id_attr}">{$slots_html}
    </div>
HTML;
}
