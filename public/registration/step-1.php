<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    $cookie_result = $registration->ensure_registration_cookie();
    $cookie_ok     = !empty($cookie_result['success']);
    $message       = isset($cookie_result['message']) ? $cookie_result['message'] : '';

    $event_model = new Evtmgr_Events();
    $qry_events   = $event_model->get_events_by_event_uid($event_uid, $lang);
?>

<?php if ('' !== $message) : ?>
    <div class="event-registration-message <?php echo $cookie_ok ? 'is-success' : 'is-error'; ?>">
        <?php echo esc_html($message); ?>
    </div>
<?php endif; ?>

<?php

    $timezones_obj = new Evtmgr_Time_Zones();
    $slots_obj      = new Evtmgr_Slots();
    $workshops_obj  = new Evtmgr_Workshops();
    $presenters_obj  = new Evtmgr_Presenters();
    $likes_obj      = new Evtmgr_Workshop_Likes();

    if (!class_exists('Evtmgr_Options')) {
        require_once __DIR__ . '/../../classes/class-evtmgr-options.php';
    }
    $step1_use_wishlist = !empty($event_uid) && (new Evtmgr_Options())->get_option($event_uid, 'use_wishlist') === '1';

    $step1_visitor_cookie     = $likes_obj->get_or_create_visitor_cookie();
    $step1_liked_workshop_ids = $likes_obj->get_liked_workshop_ids($event_uid, $step1_visitor_cookie);

    $qry_time_zones_top = $timezones_obj->get_time_zones_top($event_uid, $lang);
    $qry_time_zones_all = $timezones_obj->get_time_zones_all($event_uid, $lang);
    $selected_workshops_value = '';

    if (!empty($registration_values['selected_workshops'])) {
        $selected_workshops_value = sanitize_text_field((string) $registration_values['selected_workshops']);
    }

    $selected_workshops_cookie_name = 'event_registration_selected_workshops_' . sanitize_key((string) ($event_uid ?? ''));
    $selected_workshops_cookie_value = '';

    if (!empty($_COOKIE[$selected_workshops_cookie_name])) {
        $selected_workshops_cookie_value = sanitize_text_field(wp_unslash((string) $_COOKIE[$selected_workshops_cookie_name]));
    }

    if ($selected_workshops_value === '' && $selected_workshops_cookie_value !== '') {
        $selected_workshops_value = $selected_workshops_cookie_value;
    }

    $selected_workshop_ids = array();

    if ($selected_workshops_value !== '') {
        $selected_workshop_ids = array_values(array_filter(array_map('absint', explode(',', $selected_workshops_value))));
    }

    if (!function_exists('event_registration_format_time_number')) {
        function event_registration_format_time_number($time_value) {
            if (empty($time_value)) {
                return '';
            }

            $timestamp = strtotime($time_value);

            if (!$timestamp) {
                return '';
            }

            return date('Hi', $timestamp);
        }
    }

    if (!function_exists('event_registration_format_time_label')) {
        function event_registration_format_time_label($time_value) {
            if (empty($time_value)) {
                return '';
            }

            $timestamp = strtotime($time_value);

            if (!$timestamp) {
                return '';
            }

            return date('G.i', $timestamp);
        }
    }

    if (!function_exists('event_registration_step1_time_number_to_minutes')) {
    function event_registration_step1_time_number_to_minutes($time_number) {
        $time_number = preg_replace('/[^0-9]/', '', (string) $time_number);

        if ($time_number === '') {
            return null;
        }

        $time_number = str_pad($time_number, 4, '0', STR_PAD_LEFT);
        $hours       = (int) substr($time_number, 0, 2);
        $minutes     = (int) substr($time_number, 2, 2);

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }
    }

    if (!function_exists('event_registration_step1_build_timetable_hours')) {
    function event_registration_step1_build_timetable_hours(array $sessions) {
        $min_minutes = null;
        $max_minutes = null;

        foreach ($sessions as $session) {
            $start_minutes = event_registration_step1_time_number_to_minutes($session['time_from'] ?? '');
            $end_minutes   = event_registration_step1_time_number_to_minutes($session['time_to'] ?? '');

            if ($start_minutes !== null) {
                $min_minutes = $min_minutes === null
                    ? $start_minutes
                    : min($min_minutes, $start_minutes);
            }

            if ($end_minutes !== null) {
                $max_minutes = $max_minutes === null
                    ? $end_minutes
                    : max($max_minutes, $end_minutes);
            }
        }

        

        if ($min_minutes === null || $max_minutes === null) {
            $min_minutes = 9 * 60;
            $max_minutes = 17 * 60;
        }

        $start_hour = (int) floor($min_minutes / 60);
        $end_hour   = (int) floor($max_minutes / 60);

        if ($end_hour < $start_hour) {
            $end_hour = $start_hour;
        }

        $hours = array();

        for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
            $hours[] = sprintf('%02d:00', $hour);
        }

        return array(
            'start_hour' => $start_hour,
            'end_hour'   => $end_hour,
            'hours'      => $hours,
        );
    }
    }

?>

<?php

    if (!function_exists('event_registration_step1_sanitize_css_classes')) {
        function event_registration_step1_sanitize_css_classes($class_value) {
            $class_value = trim((string) $class_value);

            if ($class_value === '') {
                return '';
            }

            $classes = preg_split('/\s+/', $class_value);
            $classes = array_filter(array_map('sanitize_html_class', $classes));

            return implode(' ', array_unique($classes));
        }
    }

    if (!function_exists('event_registration_step1_get_first_slot_id_from_timezone')) {
        function event_registration_step1_get_first_slot_id_from_timezone(array $timezone) {
            return !empty($timezone['fky_slot_id']) ? absint($timezone['fky_slot_id']) : 0;
        }
    }

    if (!function_exists('event_registration_step1_render_workshop_html')) {
        function event_registration_step1_render_workshop_html(array $workshop, $color, $lang, array $wordings = [], $is_liked = false) {
            $id = !empty($workshop['id'])
                ? absint($workshop['id'])
                : 0;

            if ($id <= 0) {
                return '';
            }

            $str_slot_color = !empty($color)
                ? sanitize_hex_color_no_hash((string) $color)
                : 'eeeeee';

            ob_start();
            include __DIR__ . '/_workshop.php';
            return trim((string) ob_get_clean());
        }
    }

    if (!function_exists('event_registration_step1_group_timezones_by_parent')) {
        function event_registration_step1_group_timezones_by_parent(array $timezones) {
            $grouped = array();

            foreach ($timezones as $timezone) {
                $parent_id = !empty($timezone['fky_parent_timezone_id'])
                    ? absint($timezone['fky_parent_timezone_id'])
                    : 0;

                if (!isset($grouped[$parent_id])) {
                    $grouped[$parent_id] = array();
                }

                $grouped[$parent_id][] = $timezone;
            }

            return $grouped;
        }
    }

    if (!function_exists('event_registration_step1_presenter_label_parts')) {
        function event_registration_step1_presenter_label_parts(array $presenter) {
            $name = trim(
                ($presenter['str_first_name'] ?? '') . ' ' .
                ($presenter['str_last_name'] ?? '')
            );

            $details = '';

            if (!empty($presenter['str_job_title']) && !empty($presenter['str_institution'])) {
                $details = $presenter['str_job_title'] . ', ' . $presenter['str_institution'];
            } elseif (!empty($presenter['str_job_title'])) {
                $details = $presenter['str_job_title'];
            } elseif (!empty($presenter['str_institution'])) {
                $details = $presenter['str_institution'];
            }

            return array(
                'academic_title' => $presenter['str_academic_title'] ?? '',
                'name'           => $name,
                'details'        => $details,
            );
        }
    }

    $time_zones_by_parent = event_registration_step1_group_timezones_by_parent(
        is_array($qry_time_zones_all) ? $qry_time_zones_all : array()
    );

    $timetable_sessions = array();
    $debug_step1 = !empty($_GET['debug_step1']);


    /*
     * Helper: build all workshop-related data for one timezone.
     *
     * Workshops are only shown when they are directly linked to the timezone
     * currently rendered in the session list. Parent rows and child rows can
     * both be visible, but there is no fallback
     * from parent rows to child rows.
     */
    $build_workshop_data_for_timezones = function (array $timezone_sources) use (
        $slots_obj,
        $workshops_obj,
        $event_uid,
        $lang,
        $selected_workshop_ids,
        $wordings,
        $step1_liked_workshop_ids
    ) {
        $workshop_rows           = array();
        $workshops_per_slot_rows = array();
        $session_slot_ids        = array();
        $first_slot_id           = 0;
        $color                   = 'eeeeee';
        $debug_sources           = array();
        $is_slot_restricted      = false;

        foreach ($timezone_sources as $timezone_source) {
            $source_timezone_id = !empty($timezone_source['id'])
                ? absint($timezone_source['id'])
                : 0;

            if ($source_timezone_id <= 0) {
                continue;
            }

            $source_debug = array(
                'timezone_id'       => $source_timezone_id,
                'timezone_name'     => $timezone_source['str_timezone_name'] ?? '',
                'parent_timezone_id'=> $timezone_source['fky_parent_timezone_id'] ?? '',
                'slots_found'       => array(),
                'chosen_slot_id'    => 0,
                'workshops_found'   => array(),
                'workshop_count'    => 0,
            );

            $parent_tz_id = absint($timezone_source['fky_parent_timezone_id'] ?? 0) > 0
                ? absint($timezone_source['fky_parent_timezone_id'])
                : $source_timezone_id;

            $qry_slots = $slots_obj->get_slots_by_timezone_id($parent_tz_id, $event_uid, $lang);

            if (!is_array($qry_slots)) {
                $qry_slots = array();
            }

            // Child timezones are always restricted to their parent's slot columns.
            // Root timezones without any slot assignment are plenary (shown in all columns).
            $is_child_tz        = absint($timezone_source['fky_parent_timezone_id'] ?? 0) > 0;
            $is_slot_restricted = $is_slot_restricted || $is_child_tz || !empty($qry_slots);

            foreach ($qry_slots as $debug_slot) {
                $source_debug['slots_found'][] = array(
                    'id'            => $debug_slot['id'] ?? '',
                    'str_slot_name' => $debug_slot['str_slot_name'] ?? '',
                    'str_color'     => $debug_slot['str_color'] ?? '',
                    'int_sort'      => $debug_slot['int_sort'] ?? '',
                );
            }

            $source_slot_id_from_timezone = event_registration_step1_get_first_slot_id_from_timezone($timezone_source);
            $source_debug['slot_id_from_timezone'] = $source_slot_id_from_timezone;

            $direct_slot_ids = !empty($qry_slots)
                ? array_values(array_map('absint', array_column($qry_slots, 'id')))
                : array();

            if (empty($direct_slot_ids) && $source_slot_id_from_timezone > 0) {
                $direct_slot_ids = array($source_slot_id_from_timezone);
            }

            if (empty($direct_slot_ids)) {
                $debug_sources[] = $source_debug;
                continue;
            }

            // Build a color map from qry_slots results (where available).
            $slot_color_map = array();
            foreach ($qry_slots as $qs) {
                $qs_id = absint($qs['id'] ?? 0);
                if ($qs_id > 0 && !empty($qs['str_color'])) {
                    $slot_color_map[$qs_id] = $qs['str_color'];
                }
            }

            foreach ($direct_slot_ids as $iter_slot_id) {
                if ($iter_slot_id <= 0) {
                    continue;
                }

                $session_slot_ids[] = $iter_slot_id;

                if ($first_slot_id <= 0) {
                    $first_slot_id = $iter_slot_id;
                }

                if ($color === 'eeeeee' && !empty($slot_color_map[$iter_slot_id])) {
                    $color = $slot_color_map[$iter_slot_id];
                }

                $source_workshops = $workshops_obj->get_workshops_by_slot(
                    $iter_slot_id,
                    $source_timezone_id,
                    $event_uid,
                    $lang
                );

                if (!is_array($source_workshops)) {
                    continue;
                }

                foreach ($source_workshops as $debug_workshop) {
                    $source_debug['workshops_found'][] = array(
                        'id'               => $debug_workshop['id'] ?? '',
                        'number'           => $debug_workshop['str_workshop_number'] ?? '',
                        'title'            => $debug_workshop['str_workshop_title'] ?? '',
                        'fky_slot_id'      => $debug_workshop['fky_slot_id'] ?? '',
                        'fky_timezone_id'  => $debug_workshop['fky_timezone_id'] ?? '',
                        'ysn_online'       => $debug_workshop['ysn_online'] ?? '',
                    );
                }
                $source_debug['workshop_count'] += count($source_workshops);

                foreach ($source_workshops as $workshop) {
                    $workshop_id = !empty($workshop['id']) ? absint($workshop['id']) : 0;
                    if ($workshop_id <= 0) {
                        continue;
                    }
                    $workshop_rows[$workshop_id]                              = $workshop;
                    $workshops_per_slot_rows[$iter_slot_id][$workshop_id]    = $workshop;
                }
            }

            $source_debug['chosen_slot_id'] = $first_slot_id;
            $debug_sources[] = $source_debug;
        }

        $workshop_count = count($workshop_rows);

        $single_workshops   = array();
        $selected_workshops = array();
        $workshop_options   = array();

        foreach ($workshop_rows as $workshop_id => $workshop) {
            $workshop_html = event_registration_step1_render_workshop_html(
                $workshop,
                $color,
                $lang,
                $wordings,
                in_array($workshop_id, $step1_liked_workshop_ids, true)
            );

            if ($workshop_html === '') {
                continue;
            }

            $workshop_item = array(
                'id'   => $workshop_id,
                'html' => $workshop_html,
            );

            if ($workshop_count === 1) {
                $single_workshops[] = $workshop_item;
            }

            if (
                $workshop_count > 1
                && in_array($workshop_id, $selected_workshop_ids, true)
            ) {
                $selected_workshops[] = $workshop_item;
            }

            if ($workshop_count > 1) {
                $workshop_options[] = $workshop_item;
            }
        }

        $workshops_per_slot = array();
        foreach ($workshops_per_slot_rows as $slot_id => $slot_workshop_rows) {
            $slot_count    = count($slot_workshop_rows);
            $slot_single   = array();
            $slot_selected = array();
            $slot_options  = array();

            foreach ($slot_workshop_rows as $workshop_id => $workshop) {
                $workshop_html = event_registration_step1_render_workshop_html($workshop, $color, $lang, $wordings, in_array($workshop_id, $step1_liked_workshop_ids, true));
                if ($workshop_html === '') {
                    continue;
                }
                $workshop_item = array('id' => $workshop_id, 'html' => $workshop_html);
                if ($slot_count === 1) {
                    $slot_single[] = $workshop_item;
                }
                if ($slot_count > 1 && in_array($workshop_id, $selected_workshop_ids, true)) {
                    $slot_selected[] = $workshop_item;
                }
                if ($slot_count > 1) {
                    $slot_options[] = $workshop_item;
                }
            }

            $workshops_per_slot[$slot_id] = array(
                'workshop_count'     => $slot_count,
                'single_workshops'   => $slot_single,
                'selected_workshops' => $slot_selected,
                'workshop_options'   => $slot_options,
            );
        }

        return array(
            'slot_id'            => $first_slot_id,
            'session_slot_ids'   => array_values(array_unique($session_slot_ids)),
            'is_slot_restricted' => $is_slot_restricted,
            'color'              => $color,
            'workshop_count'     => $workshop_count,
            'single_workshops'   => $single_workshops,
            'selected_workshops' => $selected_workshops,
            'workshop_options'   => $workshop_options,
            'workshops_per_slot' => $workshops_per_slot,
            'debug_sources'      => $debug_sources,
        );
    };

    foreach ($qry_time_zones_all as $timezone) {

        if (empty($timezone['ysn_show_timezone_in_output'])) {
            continue;
        }

        $timezone_id = !empty($timezone['id'])
            ? absint($timezone['id'])
            : 0;

        if ($timezone_id <= 0) {
            continue;
        }

        /*
         * Build workshop data only for the currently rendered timezone.
         * No child fallback: a workshop must be linked directly to this timezone.
         */
        $workshop_data = $build_workshop_data_for_timezones(array($timezone));
        $workshop_source_mode = 'direct';
        $direct_workshop_data = $workshop_data;

        $raw_time_from = $timezone['dtm_time_from'] ?? '';
        $time_from     = event_registration_format_time_number($raw_time_from);
        $time_to       = event_registration_format_time_number($timezone['dtm_time_to'] ?? '');

        if ($time_to === '') {
            $time_to = $time_from;
        }

        $diff_minutes       = isset($timezone['int_time_from_diff_in_minutes']) && $timezone['int_time_from_diff_in_minutes'] !== null && $timezone['int_time_from_diff_in_minutes'] !== ''
            ? (int) $timezone['int_time_from_diff_in_minutes']
            : null;
        $label_time_from    = $raw_time_from;
        if ($diff_minutes !== null && $raw_time_from !== '') {
            $ts = strtotime($raw_time_from);
            if ($ts !== false) {
                $label_time_from = date('H:i:s', $ts + $diff_minutes * 60);
            }
        }

        $time_label_from = event_registration_format_time_label($label_time_from);
        $time_label_to   = event_registration_format_time_label($timezone['dtm_time_to'] ?? '');

        if ($time_label_to === '') {
            $time_label_to = $time_label_from;
        }

        $qry_presenters = $presenters_obj->get_presenters_by_timezone_id($timezone_id);

        if (!is_array($qry_presenters)) {
            $qry_presenters = array();
        }

        $presenters = array();

        foreach ($qry_presenters as $presenter) {
            $presenters[] = event_registration_step1_presenter_label_parts($presenter);
        }

        $timezone_text =
            $timezone['mem_timezone_text_' . $lang]
            ?? $timezone['mem_timezone_text_de']
            ?? '';

        $workshop_count = (int) ($workshop_data['workshop_count'] ?? 0);

        $timetable_sessions[] = array(
            'timezone_id'        => $timezone_id,
            'slot_id'            => $workshop_data['slot_id'] ?? 0,
            'session_class'      => event_registration_step1_sanitize_css_classes($timezone['str_css_class'] ?? ''),
            'time_from'          => $time_from,
            'time_to'            => $time_to,
            'time_label_from'    => $time_label_from,
            'time_label_to'      => $time_label_to,
            'show_time_in_timezone_output'=> !empty($timezone['ysn_show_time_in_output']),
            'show_text_in_timezone_output'=> !empty($timezone['ysn_show_text_in_output']),
            'timezone_name'      => $timezone['str_timezone_name'] ?? '',
            'timezone_color'     => trim((string) ($timezone['str_color'] ?? '')),
            'fullwidth'          => !empty($timezone['ysn_show_fullwidth']),
            'timezone_text'      => $timezone_text,
            'presenters'         => $presenters,
            'workshop_count'     => $workshop_count,
            'session_slot_ids'   => $workshop_data['session_slot_ids'] ?? array(),
            'is_slot_restricted' => $workshop_data['is_slot_restricted'] ?? false,
            'single_workshops'   => $workshop_data['single_workshops'] ?? array(),
            'selected_workshops' => $workshop_data['selected_workshops'] ?? array(),
            'workshop_options'   => $workshop_data['workshop_options'] ?? array(),
            'workshops_per_slot' => $workshop_data['workshops_per_slot'] ?? array(),
            'debug'              => array(
                'source_mode'          => $workshop_source_mode,
                'visible_timezone_id'  => $timezone_id,
                'visible_timezone_name'=> $timezone['str_timezone_name'] ?? '',
                'parent_timezone_id'   => $timezone['fky_parent_timezone_id'] ?? '',
                'direct_sources'       => $direct_workshop_data['debug_sources'] ?? array(),
                'final_sources'        => $workshop_data['debug_sources'] ?? array(),
                'final_workshop_count' => $workshop_count,
                'final_slot_id'        => $workshop_data['slot_id'] ?? 0,
            ),
        );
    }

    $timetable_hour_data  = event_registration_step1_build_timetable_hours($timetable_sessions);
    $timetable_start_hour = (int) ($timetable_hour_data['start_hour'] ?? 9);
    $timetable_end_hour   = (int) ($timetable_hour_data['end_hour'] ?? 17);
    $timetable_hours      = $timetable_hour_data['hours'] ?? array();
    $first_hour           = !empty($timetable_hours) ? (int) reset($timetable_hours) : $timetable_start_hour;
    $last_hour            = !empty($timetable_hours) ? (int) end($timetable_hours)   : $timetable_end_hour;

    $qry_slot_headers = $slots_obj->get_slots_with_timezone($event_uid, $lang);
    $all_slot_ids     = array_values(array_map('absint', array_column($qry_slot_headers, 'id')));
    $number_of_slots  = max(1, count($all_slot_ids));

?>

<!-- TITEL -->
<?php if (!$cookie_ok) : ?>
    <p class="event-registration-error">
        <?php esc_html_e('Die Anmeldung kann auf diesem Gerät nicht fortgesetzt werden.', 'event-registration'); ?>
    </p>
<?php elseif (empty($qry_events)) : ?>
    <p class="event-registration-error">
        <?php echo esc_html(sprintf('Der Anlass mit folgender UID wurde nicht gefunden: %s', $event_uid)); ?>
    </p>
<?php else : ?>
    <!--
    <h1>
        <?php echo esc_html($registration->get_value($qry_events, 'str_event_name')); ?><br>
        <span style="font-weight:300"><?php echo esc_html($registration->get_value($qry_events, 'str_event_subtitle')); ?></span>
    </h1>
    -->

    <?php
    echo ($wordings['beginn_der_anmeldung'] ?? 'beginn_der_anmeldung') . ' ' .
        wp_date('l, j. F Y', strtotime($qry_events['dtm_registration_opened'])) .
        '<br>';

    echo ($wordings['ende_der_anmeldung'] ?? 'ende_der_anmeldung') . ' ' .
        wp_date('l, j. F Y', strtotime($qry_events['dtm_registration_closed']));
    ?>
    <div class="event-registration-description lead mt-3">
        <?php echo wp_kses_post($registration->get_value($qry_events, 'mem_event_description')); ?>
    </div>

    <!--
    <div class="event-registration-actions">
        <button type="submit" class="btn btn-primary" name="go_next" value="1">
            <?php esc_html_e('Weiter zu Schritt 2', 'event-registration'); ?>
        </button>
    </div>
    -->
<?php endif; ?>

<style>
    .timetable {
    /* column adjust */
        --stages: <?php echo (int) $number_of_slots; ?> !important;
        --start-time: <?php echo (int) $first_hour; ?> !important;
        --end-time: <?php echo (int) $last_hour; ?>;
        --stage-gap: 0.5rem;
        --stage-width: min(100vw - var(--th-width) - 3rem, 12rem);
        --time-unit-height: .5rem;
}

        .session.full-width-row {
        grid-column: 2 / 6;
        position: relative;
        z-index: 1;
        /* +2 instead of +1 to account for missing session-list grid-row: 2 offset */
        grid-row-start: calc(
            (var(--start-hours-to-minutes) + var(--start-minutes) - var(--minutes-to-start)) / 5 + 2
        );
        grid-row-end: calc(
            (var(--end-hours-to-minutes) + var(--end-minutes) - var(--minutes-to-start)) / 5 + 2
        );
        }
</style>
<!-- TIME TABLE-->
<div class="container-lg bg-light p-0 mt-5">
    <!--
    <div class="timetable" style="--start-time: <?php echo esc_attr((string) $timetable_start_hour); ?>; --end-time: <?php echo esc_attr((string) $timetable_end_hour); ?>;">
    -->
    <div class="timetable">
        
        <div class="timetable--head" aria-hidden="true">
            <div class="timetable--inner-head">
                <?php if (!empty($qry_slot_headers)) : ?>
                    <?php foreach ($qry_slot_headers as $slot_header) : ?>
                        <?php
                        $slot_title      = $slot_header['str_slot_name_' . ($lang ?? 'de')]
                            ?? $slot_header['str_slot_name_de']
                            ?? '';
                        $slot_color_raw  = trim((string) ($slot_header['str_color'] ?? ''));
                        $slot_color_css  = $slot_color_raw !== '' ? 'background-color:#' . ltrim($slot_color_raw, '#') . ';' : '';
                        ?>
                        <div class="stage-headline m-0"<?php if ($slot_color_css !== '') : ?> style="<?php echo esc_attr($slot_color_css); ?>"<?php endif; ?>><?php echo esc_html($slot_title); ?></div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="stage-headline m-0"><?php echo $wordings['programm'] ?? 'programm'; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="timetable--body">
            
            <div class="hours" aria-hidden="true">
                <?php foreach ($timetable_hours as $hour_label) : ?>
                    <?php $hour_label_dot = str_replace(":", ".", $hour_label); ?>
                    <div><time datetime="<?php echo esc_attr($hour_label); ?>"><?php echo esc_html($hour_label_dot); ?></time></div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($timetable_sessions as $fw_session) : ?>
                <?php if (empty($fw_session['fullwidth'])) : continue; endif; ?>
                <?php
                $fw_color_raw = trim((string) ($fw_session['timezone_color'] ?? ''));
                $fw_bg_color  = '';
                if ($fw_color_raw !== '') {
                    $fw_hex = str_pad(ltrim($fw_color_raw, '#'), 6, '0');
                    if (strlen($fw_hex) === 3) { $fw_hex = $fw_hex[0].$fw_hex[0].$fw_hex[1].$fw_hex[1].$fw_hex[2].$fw_hex[2]; }
                    $fw_r = hexdec(substr($fw_hex,0,2)); $fw_g = hexdec(substr($fw_hex,2,2)); $fw_b = hexdec(substr($fw_hex,4,2));
                    $fw_bg_color = sprintf('rgb(%d,%d,%d)',
                        (int) round($fw_r + (255 - $fw_r) * 0.8),
                        (int) round($fw_g + (255 - $fw_g) * 0.8),
                        (int) round($fw_b + (255 - $fw_b) * 0.8)
                    );
                }
                ?>
                <div class="session full-width-row <?php echo esc_attr($fw_session['session_class']); ?>"
                     style="--start: <?php echo esc_attr($fw_session['time_from']); ?>; --end: <?php echo esc_attr($fw_session['time_to']); ?>;<?php if ($fw_bg_color !== '') echo ' background-color:' . $fw_bg_color . ';'; ?>">

                    <div data-slot="<?php echo esc_attr((string) $fw_session['slot_id']); ?>"
                            data-timezone="<?php echo esc_attr((string) $fw_session['timezone_id']); ?>"
                            class="js-session-container session-eno session-1 track-all">

                        <?php if (!empty($fw_session['show_time_in_timezone_output'])) : ?>
                            <span class="time">
                                <?php echo esc_html($fw_session['time_label_from']); ?>–<?php echo esc_html($fw_session['time_label_to']); ?> Uhr
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($fw_session['show_text_in_timezone_output'])) : ?>
                            <h3 class="session-title mb-1 mt-2">
                                <?php echo esc_html($fw_session['timezone_name']); ?>
                            </h3>

                            <?php if (trim((string) $fw_session['timezone_text']) !== '') : ?>
                                <?php echo wp_kses_post($fw_session['timezone_text']); ?>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($fw_session['presenters'])) : ?>
                            <ul class="speaker-list icon-inverted no-border">
                                <?php foreach ($fw_session['presenters'] as $fw_presenter) : ?>
                                    <li>
                                        <?php if (!empty($fw_presenter['academic_title'])) : ?>
                                            <?php echo esc_html($fw_presenter['academic_title']); ?>
                                        <?php endif; ?>
                                        <?php echo esc_html($fw_presenter['name']); ?>
                                        <?php if (!empty($fw_presenter['details'])) : ?>
                                            | <?php echo esc_html($fw_presenter['details']); ?><br>
                                        <?php else : ?>
                                            <br>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php $fw_workshop_count = (int) ($fw_session['workshop_count'] ?? 0); ?>
                        <?php if ($fw_workshop_count === 1) : ?>
                            <div class="workshop mt-1">
                                <?php foreach ($fw_session['single_workshops'] as $fw_item) : ?>
                                    <div class="workshop-item"><?php echo $fw_item['html']; ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($fw_workshop_count > 1) : ?>
                            <div class="mt-2">
                                <span class="mr-1">
                                    <?php echo $wordings['anzahl_angebote_zur_auswahl'] ?? 'anzahl_angebote_zur_auswahl'; ?>
                                    <?php echo esc_html((string) $fw_workshop_count); ?>
                                </span>
                                <?php $has_selected = !empty($fw_session['selected_workshops']); ?>
                                <a href="#" class="btn btn-select-workshop btn-sm js-workshop-add ps-2 pe-2"<?php if ($has_selected) : ?> style="display:none"<?php endif; ?>>
                                    <?php echo $wordings['angebot_auswaehlen'] ?? 'angebot_auswaehlen'; ?>
                                </a>
                                <?php if ($has_selected) : ?>
                                    <a href="#" class="btn btn-select-workshop btn-sm js-workshop-close-replacement ps-2 pe-2" role="button" aria-label="Angebot abwählen" title="Angebot abwählen">
                                        <?php echo $wordings['angebot_abwaehlen'] ?? 'Angebot abwählen'; ?>
                                        <span style="margin-left:.5rem;">✕</span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="js-workshops-label js-workshops-label-no mt-2">
                                        <?php echo $wordings['sie_haben_noch_kein_angebot_gewaehlt'] ?? 'sie_haben_noch_kein_angebot_gewaehlt'; ?>
                                    </div>
                                    <div class="js-workshops-label js-workshops-label-yes mt-2" style="display:none">
                                        <?php echo $wordings['sie_haben_folgendes_angebot_gewaehlt'] ?? 'sie_haben_folgendes_angebot_gewaehlt'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row js-wokshop-container">
                                <?php foreach ($fw_session['selected_workshops'] as $fw_item) : ?>
                                    <div class="col-md-12 mt-1 selected-workshop-wrapper"
                                            data-workshop="<?php echo esc_attr((string) $fw_item['id']); ?>">
                                        <div class="workshop">
                                            <?php
                                                $item_html = (string) ($fw_item['html'] ?? '');
                                                // remove any old internal close markup so it doesn't show alongside the replacement button
                                                $item_html = preg_replace('/<div[^>]*class=["\']([^"\']*\bjs-workshop-close\b[^"\']*)["\'][^>]*>.*?<\/div>/is', '', $item_html);
                                                echo $item_html;
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="js-workshop-options" hidden>
                                <?php foreach ($fw_session['workshop_options'] as $fw_item) : ?>
                                    <template class="js-workshop-option-template"
                                                data-workshop="<?php echo esc_attr((string) $fw_item['id']); ?>">
                                        <div class="col-md-12 event-registration-modal-workshop js-workshop-select workshop-select"
                                                data-workshop="<?php echo esc_attr((string) $fw_item['id']); ?>">
                                            <div class="workshop p-0 m-0">
                                                <?php echo $fw_item['html']; ?>
                                            </div>
                                        </div>
                                    </template>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($qry_slot_headers)) : ?>
                <?php foreach ($qry_slot_headers as $col_index => $col_slot) : ?>
                    <?php $col_slot_id = absint($col_slot['id'] ?? 0); ?>
                    <?php include('step-1-col-1.php'); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <?php $col_index = 0; $col_slot_id = 0; $col_slot = array(); ?>
                <?php include('step-1-col-1.php'); ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="modal fade" id="event_registration_workshop_modal" tabindex="-1" aria-labelledby="event_registration_workshop_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h3" id="event_registration_workshop_modal_label"><?php echo $wordings['angebot_auswaehlen'] ?? 'angebot_auswaehlen'; ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body js-workshop-modal-body">
                <p class="lead"><?php echo $wordings['bitte_waehlen_sie_das_gewuenschte_angebot_durch_klick_auf_das_angebot'] ?? 'bitte_waehlen_sie_das_gewuenschte_angebot_durch_klick_auf_das_angebot'; ?></p>

                <?php if ($step1_use_wishlist) : ?>
                    <div class="js-workshop-modal-filter d-flex align-items-center flex-wrap gap-2 mb-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="js_modal_liked_only_checkbox">
                            <label class="form-check-label" for="js_modal_liked_only_checkbox">
                                Nur Angebote aus meiner Merkliste
                            </label>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-modal-liked-filter-apply">Filtern</button>
                    </div>
                <?php endif; ?>

                <div class="row js-workshop-modal-list ps-3 pe-3"></div>
            </div>
        </div>
    </div>
</div>

<div class="event-registration-selected-workshops-field mb-3 d-none">
    <label for="selected_workshops" class="form-label small mb-1">selected_workshops</label>
    <input type="text"
           class="form-control form-control-sm"
           id="selected_workshops"
           name="selected_workshops"
           value="<?php echo esc_attr($selected_workshops_value); ?>">
</div>

<textarea id="person_program_data"
          name="person_program_data"
          class="d-none"></textarea>

<div class="event-registration-actions">
        <button type="submit"
        name="registration_action"
        value="next"
        class="btn btn-primary btn-lg mt-3 float-right js-final-button">
            <?php echo $wordings['weiter_in_der_anmeldung'] ?? 'weiter_in_der_anmeldung'; ?>
        </button>
</div>

<script>
    (function () {
        var activeSessionContainer = null;
        var modalElement = document.getElementById('event_registration_workshop_modal');
        var modalList = modalElement ? modalElement.querySelector('.js-workshop-modal-list') : null;
        var modalLikedOnlyCheckbox = modalElement ? modalElement.querySelector('#js_modal_liked_only_checkbox') : null;
        var modalLikedFilterButton = modalElement ? modalElement.querySelector('.js-modal-liked-filter-apply') : null;
        var bootstrapModal = null;
        var selectedWorkshopsCookieName = <?php echo wp_json_encode($selected_workshops_cookie_name); ?>;
        var selectedWorkshopsStorageKey = 'event_registration_selected_workshops_' + <?php echo wp_json_encode((string) ($event_uid ?? '')); ?>;

        function getBootstrapModal() {
            if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return null;
            }

            if (!bootstrapModal) {
                bootstrapModal = new bootstrap.Modal(modalElement);
            }

            return bootstrapModal;
        }

        function fixWorkshopContainer(container) {
            if (!container) {
                return;
            }

            var selectedCount = container.querySelectorAll('.js-wokshop-container .js-workshop-item').length;
            var show = selectedCount ? 'yes' : 'no';

            container.querySelectorAll('.js-workshops-label').forEach(function (label) {
                label.style.display = 'none';
            });

            container.querySelectorAll('.js-workshops-label-' + show).forEach(function (label) {
                label.style.display = '';
            });
        }

        function getSelectedWorkshopIds(container) {
            if (!container) {
                return [];
            }

            return Array.prototype.map.call(
                container.querySelectorAll('.js-wokshop-container .js-workshop-item'),
                function (item) {
                    return item.getAttribute('data-workshop') || '';
                }
            ).filter(Boolean);
        }

        function getSelectedWorkshopIdsFromDom() {
            var ids = Array.prototype.map.call(
                document.querySelectorAll('.js-wokshop-container .js-workshop-item'),
                function (item) {
                    return item.getAttribute('data-workshop') || '';
                }
            ).filter(Boolean);

            return ids.filter(function (id, index) {
                return ids.indexOf(id) === index;
            });
        }

        function persistSelectedWorkshopsState(value) {
            if (selectedWorkshopsCookieName) {
                var cookieValue = value === undefined ? '' : String(value);
                document.cookie = selectedWorkshopsCookieName + '=' + encodeURIComponent(cookieValue) + '; path=/; max-age=' + (60 * 60 * 24 * 30);
            }

            try {
                var state = {};
                document.querySelectorAll('.js-session-container').forEach(function (container) {
                    var key = container.getAttribute('data-timezone') || container.getAttribute('data-slot') || '';
                    if (!key) {
                        return;
                    }

                    var selectedIds = Array.prototype.map.call(
                        container.querySelectorAll('.js-wokshop-container .js-workshop-item'),
                        function (item) {
                            return item.getAttribute('data-workshop') || '';
                        }
                    ).filter(Boolean);

                    state[key] = selectedIds;
                });

                localStorage.setItem(selectedWorkshopsStorageKey, JSON.stringify(state));
            } catch (error) {
                console.warn('Unable to persist workshop selection state.', error);
            }
        }

        function buildSelectedWorkshopNode(selection) {
            if (!selection) {
                return null;
            }

            var newWorkshop = selection.cloneNode(true);
            newWorkshop.classList.remove('workshop-select');
            newWorkshop.classList.remove('js-workshop-select');
            newWorkshop.classList.remove('workshop-select-denied');
            newWorkshop.classList.remove('event-registration-modal-workshop');
            newWorkshop.classList.remove('is-selected');
            newWorkshop.classList.add('selected-workshop-wrapper');
            newWorkshop.removeAttribute('aria-disabled');

            newWorkshop.querySelectorAll('.js-workshop-close').forEach(function (el) {
                el.remove();
            });

            return newWorkshop;
        }

        function restoreSelectedWorkshopSelections() {
            try {
                var storedState = localStorage.getItem(selectedWorkshopsStorageKey);
                if (!storedState) {
                    return;
                }

                var state = JSON.parse(storedState);
                if (!state || typeof state !== 'object') {
                    return;
                }

                document.querySelectorAll('.js-session-container').forEach(function (container) {
                    var key = container.getAttribute('data-timezone') || container.getAttribute('data-slot') || '';
                    if (!key || !state[key] || !Array.isArray(state[key])) {
                        return;
                    }

                    var targetContainer = container.querySelector('.js-wokshop-container');
                    if (!targetContainer) {
                        return;
                    }

                    targetContainer.innerHTML = '';
                    var templates = container.querySelectorAll('.js-workshop-options .js-workshop-option-template');

                    templates.forEach(function (template) {
                        var workshopId = template.getAttribute('data-workshop') || '';
                        if (!state[key].includes(workshopId)) {
                            return;
                        }

                        var node = template.content.firstElementChild.cloneNode(true);
                        var selectedWorkshopNode = buildSelectedWorkshopNode(node);
                        if (selectedWorkshopNode) {
                            targetContainer.appendChild(selectedWorkshopNode);
                        }
                    });

                    fixWorkshopContainer(container);
                });
            } catch (error) {
                console.warn('Unable to restore workshop selection state.', error);
            }
        }

        function createWorkshopReplacementButton(anchorButton) {
            if (!anchorButton) {
                return null;
            }

            var replacementButton = document.createElement('a');
            replacementButton.href = '#';
            var baseClasses = (anchorButton.className || '').replace(/\bjs-workshop-add\b/, '').trim();
            replacementButton.className = (baseClasses + ' js-workshop-close-replacement ps-2 pe-2').trim();
            replacementButton.setAttribute('role', 'button');
            replacementButton.setAttribute('aria-label', 'Angebot abwählen');
            replacementButton.setAttribute('title', 'Angebot abwählen');
            replacementButton.appendChild(document.createTextNode('Angebot abwählen'));

            var spanX = document.createElement('span');
            spanX.style.marginLeft = '.5rem';
            spanX.textContent = '✕';
            replacementButton.appendChild(spanX);

            return replacementButton;
        }

        function syncWorkshopActionButtons(container) {
            if (!container) {
                return;
            }

            var timezone = container.getAttribute('data-timezone') || '';
            var containers = timezone
                ? document.querySelectorAll('.js-session-container[data-timezone="' + CSS.escape(timezone) + '"]')
                : [container];

            containers.forEach(function (sessionContainer) {
                var addBtn = sessionContainer.querySelector('.js-workshop-add');
                var hasSelected = sessionContainer.querySelector('.selected-workshop-wrapper') !== null;
                var existingReplacement = sessionContainer.querySelector('.js-workshop-close-replacement');

                if (existingReplacement) {
                    existingReplacement.remove();
                }

                if (addBtn) {
                    addBtn.style.display = hasSelected ? 'none' : '';
                }

                if (!hasSelected) {
                    return;
                }

                if (addBtn) {
                    addBtn.parentNode.insertBefore(createWorkshopReplacementButton(addBtn), addBtn);
                    return;
                }

                var fallbackTarget = sessionContainer.querySelector('.mt-2') || sessionContainer;
                var replacementButton = createWorkshopReplacementButton({ className: 'btn btn-select-workshop btn-sm' });
                if (replacementButton) {
                    fallbackTarget.insertBefore(replacementButton, fallbackTarget.firstChild);
                }
            });
        }

        function updateSelectedWorkshopsField() {
            var field = document.getElementById('selected_workshops');

            if (!field) {
                return;
            }

            var ids = getSelectedWorkshopIdsFromDom();
            var value = ids.join(',');

            field.value = value;
            persistSelectedWorkshopsState(value);
        }

        function updatePersonProgramDataField() {
            var field = document.getElementById('person_program_data');
            var timetable = document.querySelector('.timetable');

            if (!field || !timetable) {
                return;
            }

            field.value = timetable.innerHTML;
        }

        function applyModalLikedFilter() {
            if (!modalList || !modalLikedOnlyCheckbox) {
                return;
            }

            var onlyLiked = modalLikedOnlyCheckbox.checked;

            Array.prototype.forEach.call(modalList.children, function (wrapper) {
                var workshopItem = wrapper.querySelector('.js-workshop-item');
                var isLiked = !!workshopItem && workshopItem.getAttribute('data-liked') === '1';

                wrapper.style.display = (onlyLiked && !isLiked) ? 'none' : '';
            });
        }

        if (modalLikedFilterButton) {
            modalLikedFilterButton.addEventListener('click', function () {
                applyModalLikedFilter();
            });
        }

        function openWorkshopModal(container) {
            var modal = getBootstrapModal();

            if (!modal || !modalList) {
                console.warn('Bootstrap 5 modal JavaScript is not loaded.');
                return;
            }

            activeSessionContainer = container;
            modalList.innerHTML = '';

            var selectedIds = getSelectedWorkshopIds(container);
            var templates = container.querySelectorAll('.js-workshop-options .js-workshop-option-template');

            templates.forEach(function (template) {
                var workshopId = template.getAttribute('data-workshop') || '';
                var node = template.content.firstElementChild.cloneNode(true);

                var workshopItem = node.querySelector('.js-workshop-item');
                var isBookedOut  = workshopItem && workshopItem.getAttribute('data-booked-out') === '1';

                if (isBookedOut) {
                    node.classList.remove('js-workshop-select');
                    node.classList.remove('workshop-select');
                    node.classList.add('workshop-select-denied');
                    node.classList.add('is-selected');
                    node.setAttribute('aria-disabled', 'true');
                }

                modalList.appendChild(node);
            });

            applyModalLikedFilter();

            modal.show();
        }

        function selectWorkshop(selection) {
            if (!activeSessionContainer || !selection.classList.contains('workshop-select')) {
                return;
            }

            var selectedItem = selection.querySelector('.js-workshop-item');
            var workshopId = selectedItem ? selectedItem.getAttribute('data-workshop') : '';

            if (!workshopId) {
                return;
            }

            var timezoneId = activeSessionContainer.getAttribute('data-timezone') || '';

            if (timezoneId) {
                document.querySelectorAll(
                    '.js-session-container[data-timezone="' + CSS.escape(timezoneId) + '"] .js-wokshop-container'
                ).forEach(function (container) {
                    container.innerHTML = '';
                    fixWorkshopContainer(container.closest('.js-session-container'));
                });
            } else {
                var currentContainer = activeSessionContainer.querySelector('.js-wokshop-container');
                if (currentContainer) {
                    currentContainer.innerHTML = '';
                }
            }

            var newWorkshop = buildSelectedWorkshopNode(selection);
            if (!newWorkshop) {
                return;
            }

            // Insert the selected workshop into the container
            var targetContainer = activeSessionContainer.querySelector('.js-wokshop-container');
            if (targetContainer) {
                targetContainer.appendChild(newWorkshop);
            }

            fixWorkshopContainer(activeSessionContainer);
            syncWorkshopActionButtons(activeSessionContainer);
            updateSelectedWorkshopsField();

            var modal = getBootstrapModal();
            if (modal) {
                modal.hide();
            }
        }

        document.addEventListener('click', function (event) {
            var addButton = event.target.closest('.js-workshop-add');
            if (addButton) {
                event.preventDefault();
                openWorkshopModal(addButton.closest('.js-session-container'));
                return;
            }

            var selection = event.target.closest('.js-workshop-select');
            if (selection && modalElement && modalElement.contains(selection)) {
                if (event.target.closest('.accordion-button')) {
                    return;
                }
                event.preventDefault();
                selectWorkshop(selection);
                return;
            }

            var closeButton = event.target.closest('.js-workshop-close, .js-workshop-close-replacement');
            if (closeButton) {
                event.preventDefault();
                var container = closeButton.closest('.js-session-container');
                var selectedWorkshop = closeButton.closest('.selected-workshop-wrapper');

                if (!selectedWorkshop) {
                    selectedWorkshop = closeButton.closest('.js-workshop-item');
                }

                if (selectedWorkshop) {
                    selectedWorkshop.remove();
                    fixWorkshopContainer(container);
                    updateSelectedWorkshopsField();
                } else if (container) {
                    // replacement button clicked – find and remove selected workshop inside the same session container
                    var found = container.querySelector('.selected-workshop-wrapper');
                    if (!found) {
                        found = container.querySelector('.js-workshop-item.selected-workshop-wrapper') || container.querySelector('.js-workshop-item') || null;
                    }
                    if (found) {
                        found.remove();
                        fixWorkshopContainer(container);
                        updateSelectedWorkshopsField();
                    }
                }

                if (container) {
                    syncWorkshopActionButtons(container);
                }
            }
        });

        document.addEventListener('submit', function () {
            updateSelectedWorkshopsField();
            updatePersonProgramDataField();
        });

        var finalButton = document.querySelector('.js-final-button');
        if (finalButton) {
            finalButton.addEventListener('click', function () {
                updateSelectedWorkshopsField();
                updatePersonProgramDataField();
            });
        }

        document.querySelectorAll('.js-session-container').forEach(fixWorkshopContainer);
        restoreSelectedWorkshopSelections();
        updateSelectedWorkshopsField();
        updatePersonProgramDataField();

        document.querySelectorAll('.js-session-container').forEach(syncWorkshopActionButtons);
    })();
</script>

<script>
    // JS fallback: only used when CSS mod() or round() are NOT supported
    (function () {
        const supportsRound = CSS.supports('width: round(down, 10px, 1px)');
        const supportsMod = CSS.supports('--number: mod(10, 3)');

        if (supportsRound && supportsMod) {
            return;
        }

        const timetable = document.querySelector('.timetable');
        if (!timetable) return;

        const timetableStyles = getComputedStyle(timetable);
        const startTimeVar = timetableStyles.getPropertyValue('--start-time').trim();
        const startTimeHours = Number(startTimeVar) || 0;

        const minutesToTimelineStart = startTimeHours * 60;
        const unitMinutes = 5;

        function timeNumberToRow(raw) {
            const time = Number(raw);
            if (!Number.isFinite(time)) return null;

            const hours = Math.floor(time / 100);
            const minutes = time % 100;

            const totalMinutes = hours * 60 + minutes;
            const minutesRelative = totalMinutes - minutesToTimelineStart;

            return minutesRelative / unitMinutes + 1;
        }

        document.querySelectorAll('.session').forEach((session) => {
            const styles = getComputedStyle(session);

            const startRaw =
                session.style.getPropertyValue('--start') ||
                styles.getPropertyValue('--start');

            const endRaw =
                session.style.getPropertyValue('--end') ||
                styles.getPropertyValue('--end');

            const startRow = timeNumberToRow(startRaw.trim());
            const endRow = timeNumberToRow(endRaw.trim());

            if (startRow != null) session.style.gridRowStart = String(startRow);
            if (endRow != null) session.style.gridRowEnd = String(endRow);
        });
    })();
</script>


