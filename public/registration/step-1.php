<link rel='stylesheet' id='dashicons-css' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/public/assets/time-table.css' media='all' />
<link rel='stylesheet' id='dashicons-css' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/public/assets/time-table-custom-1.css' media='all' />
<link rel='stylesheet' id='dashicons-css' href='/wp-content/themes/picostrap5-child-base/db-custom/event-registration/public/assets/workshops.css' media='all' />

<style>
    .js-workshop-item {
        position: relative;
        border:1.5px solid rgba(255,255,255,.5);
        padding:.5rem;
        border-radius:.5rem;
    }

    .js-workshop-close {
        position: absolute;
        top: 8px;
        right: 10px;
        z-index: 5;

        width: 40px;
        height: 40px;
        border-radius: 50%;

        align-items: center;
        justify-content: center;

        background: rgba(255,255,255,.5);
        border: 2px solid rgba(0,0,0,.5);

        font-size: 24px;
        color:black !important;
        line-height: 1;
        font-weight: 700;

        cursor: pointer;
    }

    .selected-workshop-wrapper .js-workshop-close {
    display: flex !important;
    align-items: center;
    justify-content: center;
    }

    .selected-workshop-wrapper .js-workshop-close::before {
        content: "×";
        display: block;
        font-size: 30px;
        line-height: 1;
        font-weight: 700;
        color: rgba(0,0,0,.5);
        margin-top:-3px;
    }

    .selected-workshop-wrapper .js-workshop-close svg,
    .selected-workshop-wrapper .js-workshop-close img {
        display: none !important;
    }

    .js-workshop-close:hover,
    .js-workshop-close:focus {
        background: rgba(255,255,255,.35);
        color: #fff;
    }

    .js-workshop-close svg {
        width: 18px;
        height: 18px;
    }


    #event_registration_workshop_modal .event-registration-modal-workshop {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1rem;
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
    }

    #event_registration_workshop_modal .event-registration-modal-workshop:hover,
    #event_registration_workshop_modal .event-registration-modal-workshop:focus {
        background: #e0b6d4;
        border-color: #8b036b;
        transform: translateY(-1px);
    }

    #event_registration_workshop_modal .event-registration-modal-workshop.is-selected,
    #event_registration_workshop_modal .event-registration-modal-workshop.workshop-select-denied {
        background: #e9ecef;
        border-color: #adb5bd;
        cursor: not-allowed;
        opacity: 0.65;
    }
    .js-workshop-modal-body, .modal-header {color:black;}
    .btn-select-workshop {
        background: rgba(255,255,255,.5);
        margin-left:1rem;
        border-radius:1rem;
        padding-left:1rem !important;
        padding-right:1rem !important;
        color:white;
        }
        .btn-select-workshop:hover {
        background-color: #000000;
    }
</style>

<style>
    .selected-workshop-wrapper .speaker-list li::before,
    .selected-workshop-wrapper .free-places-list li::before ,
    .selected-workshop-wrapper .price-list li::before
    {
         filter: invert(1);
    }
</style>

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

    $qry_time_zones_top = $timezones_obj->get_time_zones_top($event_uid, $lang);
    $qry_time_zones_all = $timezones_obj->get_time_zones_all($event_uid, $lang);
    $selected_workshops_value = '';

    if (!empty($registration_values['selected_workshops'])) {
        $selected_workshops_value = sanitize_text_field((string) $registration_values['selected_workshops']);
    }

    $selected_workshop_ids = array();

    if ($selected_workshops_value !== '') {
        $selected_workshop_ids = array_values(array_filter(array_map('absint', explode(',', $selected_workshops_value))));
    }

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

?>

<?php

    function event_registration_step1_sanitize_css_classes($class_value) {
        $class_value = trim((string) $class_value);

        if ($class_value === '') {
            return '';
        }

        $classes = preg_split('/\s+/', $class_value);
        $classes = array_filter(array_map('sanitize_html_class', $classes));

        return implode(' ', array_unique($classes));
    }

    if (!function_exists('event_registration_step1_get_first_slot_id_from_timezone')) {
        function event_registration_step1_get_first_slot_id_from_timezone(array $timezone) {
            if (!empty($timezone['fky_slot_id'])) {
                return absint($timezone['fky_slot_id']);
            }

            $str_slots = isset($timezone['str_slots'])
                ? trim((string) $timezone['str_slots'])
                : '';

            if ($str_slots === '') {
                return 0;
            }

            $slot_ids = array_values(array_filter(array_map('absint', explode(',', $str_slots))));

            if (empty($slot_ids)) {
                return 0;
            }

            return (int) $slot_ids[0];
        }
    }

    if (!function_exists('event_registration_step1_render_workshop_html')) {
        function event_registration_step1_render_workshop_html(array $workshop, $color, $lang, array $wordings = []) {
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
     * both be visible when ysn_show_in_output = 1, but there is no fallback
     * from parent rows to child rows.
     */
    $build_workshop_data_for_timezones = function (array $timezone_sources) use (
        $slots_obj,
        $workshops_obj,
        $event_uid,
        $lang,
        $selected_workshop_ids,
        $wordings
    ) {
        $workshop_rows = array();
        $first_slot_id = 0;
        $color         = 'eeeeee';
        $debug_sources = array();

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
                'str_slots'         => $timezone_source['str_slots'] ?? '',
                'show_in_output'    => $timezone_source['ysn_show_in_output'] ?? '',
                'slots_found'       => array(),
                'chosen_slot_id'    => 0,
                'workshops_found'   => array(),
                'workshop_count'    => 0,
            );

            $qry_slots = $slots_obj->qry_slots_by_time_zone(
                $source_timezone_id,
                $event_uid,
                $lang
            );

            if (!is_array($qry_slots)) {
                $qry_slots = array();
            }

            foreach ($qry_slots as $debug_slot) {
                $source_debug['slots_found'][] = array(
                    'id'            => $debug_slot['id'] ?? '',
                    'str_slot_name' => $debug_slot['str_slot_name'] ?? '',
                    'str_color'     => $debug_slot['str_color'] ?? '',
                    'int_sort'      => $debug_slot['int_sort'] ?? '',
                );
            }

            $source_first_slot = !empty($qry_slots[0])
                ? $qry_slots[0]
                : null;

            $source_slot_id_from_query = !empty($source_first_slot['id'])
                ? absint($source_first_slot['id'])
                : 0;

            $source_slot_id_from_timezone = event_registration_step1_get_first_slot_id_from_timezone($timezone_source);

            /*
             * Prefer the real slot record if qry_slots_by_time_zone() found it.
             * Fallback to the timezone's str_slots/fky_slot_id value.
             * This fixes cases where debug shows str_slots = 723 but chosen_slot_id = 0.
             */
            $source_slot_id = $source_slot_id_from_query > 0
                ? $source_slot_id_from_query
                : $source_slot_id_from_timezone;

            $source_debug['chosen_slot_id'] = $source_slot_id;
            $source_debug['slot_id_from_query'] = $source_slot_id_from_query;
            $source_debug['slot_id_from_timezone'] = $source_slot_id_from_timezone;

            if ($first_slot_id <= 0 && $source_slot_id > 0) {
                $first_slot_id = $source_slot_id;
            }

            if ($color === 'eeeeee' && !empty($source_first_slot['str_color'])) {
                $color = $source_first_slot['str_color'];
            }

            if ($source_slot_id <= 0) {
                $debug_sources[] = $source_debug;
                continue;
            }

            $source_workshops = $workshops_obj->get_workshops_by_slot(
                $source_slot_id,
                $source_timezone_id,
                $event_uid,
                $lang
            );

            if (!is_array($source_workshops)) {
                $debug_sources[] = $source_debug;
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
            $source_debug['workshop_count'] = count($source_workshops);

            foreach ($source_workshops as $workshop) {
                $workshop_id = !empty($workshop['id'])
                    ? absint($workshop['id'])
                    : 0;

                if ($workshop_id <= 0) {
                    continue;
                }

                /* Avoid duplicates if the same workshop is reachable through more than one source. */
                $workshop_rows[$workshop_id] = $workshop;
            }

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
                $wordings
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

        return array(
            'slot_id'            => $first_slot_id,
            'color'              => $color,
            'workshop_count'     => $workshop_count,
            'single_workshops'   => $single_workshops,
            'selected_workshops' => $selected_workshops,
            'workshop_options'   => $workshop_options,
            'debug_sources'      => $debug_sources,
        );
    };

    /*
     * Build the timetable from all timezones, not only children.
     * Parent and child rows are both eligible for output.
     * The database field ysn_show_in_output controls whether a timezone
     * becomes an item in the session-list.
     */
    foreach ($qry_time_zones_all as $timezone) {
        if (empty($timezone['ysn_show_in_output'])) {
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

        $time_from = event_registration_format_time_number($timezone['dtm_time_from'] ?? '');
        $time_to   = event_registration_format_time_number($timezone['dtm_time_to'] ?? '');

        if ($time_to === '') {
            $time_to = $time_from;
        }

        $time_label_from = event_registration_format_time_label($timezone['dtm_time_from'] ?? '');
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
            'show_time_in_output'=> !empty($timezone['ysn_show_time_in_output']),
            'timezone_name'      => $timezone['str_timezone_name'] ?? '',
            'timezone_text'      => $timezone_text,
            'presenters'         => $presenters,
            'workshop_count'     => $workshop_count,
            'single_workshops'   => $workshop_data['single_workshops'] ?? array(),
            'selected_workshops' => $workshop_data['selected_workshops'] ?? array(),
            'workshop_options'   => $workshop_data['workshop_options'] ?? array(),
            'debug'              => array(
                'source_mode'          => $workshop_source_mode,
                'visible_timezone_id'  => $timezone_id,
                'visible_timezone_name'=> $timezone['str_timezone_name'] ?? '',
                'parent_timezone_id'   => $timezone['fky_parent_timezone_id'] ?? '',
                'visible_str_slots'    => $timezone['str_slots'] ?? '',
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

?>

<!-- TITEL -->
<?php if (!$cookie_ok) : ?>
    <p class="event-registration-error">
        <?php esc_html_e('Die Anmeldung kann auf diesem Gerät nicht fortgesetzt werden.', 'event-registration'); ?>
    </p>
<?php elseif (empty($qry_events)) : ?>
    <p class="event-registration-error">
        <?php echo esc_html(sprintf('Der Anlass mit folgender UUID wurde nicht gefunden: %s ', $event_uid)); ?>
    </p>
<?php else : ?>
    <!--
    <h1>
        <?php echo esc_html($registration->get_value($qry_events, 'str_event_name')); ?><br>
        <span style="font-weight:300"><?php echo esc_html($registration->get_value($qry_events, 'str_event_subtitle')); ?></span>
    </h1>
    -->

<?php
echo '$Beginn der Anmeldung:£ ' . wp_date('l, j. F Y', strtotime($qry_events['dtm_registration_opened'])) . '<br>';
echo '$Ende der Anmeldung:£ ' . wp_date('l, j. F Y', strtotime($qry_events['dtm_registration_closed']));
?>

    <div class="event-registration-description mt-3">
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

<!-- TIME TABLE-->
<div class="container-lg bg-light p-0 mt-5">
    <!--
    <div class="timetable" style="--start-time: <?php echo esc_attr((string) $timetable_start_hour); ?>; --end-time: <?php echo esc_attr((string) $timetable_end_hour); ?>;">
    -->
    <div class="timetable">
        
        <div class="timetable--head" aria-hidden="true">
            <div class="timetable--inner-head">
                <div class="stage-headline m-0 ms-4"><?php echo $wordings['programm'] ?? ''; ?></div>
                <!--
                KEEP!
                <div class="stage-headline m-0"><?php echo $wordings['programm'] ?? ''; ?></div>
                <div class="stage-headline m-0"><?php echo $wordings['programm'] ?? ''; ?></div>
                -->
            </div>
        </div>

        <div class="timetable--body">
            
            <div class="hours" aria-hidden="true">
                <?php foreach ($timetable_hours as $hour_label) : ?>
                    <div><time datetime="<?php echo esc_attr($hour_label); ?>"><?php echo esc_html($hour_label); ?></time></div>
                <?php endforeach; ?>
            </div>

            <?php include('col-2.php'); ?>
            <!--
            <?php include('col-3.php'); ?>
            <?php include('col-4.php'); ?>
            -->

        </div>
    </div>
</div>

<div class="modal fade" id="event_registration_workshop_modal" tabindex="-1" aria-labelledby="event_registration_workshop_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h3" id="event_registration_workshop_modal_label"><?php echo $wordings['angebot_auswaehlen'] ?? ''; ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body js-workshop-modal-body">
                <p class="lead"><?php echo $wordings['bitte_waehlen_sie_das_gewuenschte_angebot_durch_klick_auf_das_angebot'] ?? ''; ?></p>
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
            <?php echo $wordings['weiter_in_der_anmeldung'] ?? ''; ?>
        </button>
</div>

<script>
    (function () {
        var activeSessionContainer = null;
        var modalElement = document.getElementById('event_registration_workshop_modal');
        var modalList = modalElement ? modalElement.querySelector('.js-workshop-modal-list') : null;
        var bootstrapModal = null;

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

        function updateSelectedWorkshopsField() {
            var field = document.getElementById('selected_workshops');

            if (!field) {
                return;
            }

            var ids = Array.prototype.map.call(
                document.querySelectorAll('.js-wokshop-container .js-workshop-item'),
                function (item) {
                    return item.getAttribute('data-workshop') || '';
                }
            ).filter(Boolean);

            ids = ids.filter(function (id, index) {
                return ids.indexOf(id) === index;
            });

            field.value = ids.join(',');
        }

        function updatePersonProgramDataField() {
            var field = document.getElementById('person_program_data');
            var timetable = document.querySelector('.timetable');

            if (!field || !timetable) {
                return;
            }

            field.value = timetable.innerHTML;
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

            var newWorkshop = selection.cloneNode(true);
            newWorkshop.classList.remove('workshop-select');
            newWorkshop.classList.remove('js-workshop-select');
            newWorkshop.classList.remove('workshop-select-denied');
            newWorkshop.classList.remove('event-registration-modal-workshop');
            newWorkshop.classList.remove('is-selected');
            newWorkshop.classList.add('selected-workshop-wrapper');
            newWorkshop.removeAttribute('aria-disabled');

            newWorkshop.querySelectorAll('.js-workshop-close').forEach(function (button) {
                button.style.display = 'flex';
                button.innerHTML = '';
                button.setAttribute('role', 'button');
                button.setAttribute('aria-label', 'Angebot entfernen');
                button.setAttribute('title', 'Angebot entfernen');
            });


            var targetContainer = activeSessionContainer.querySelector('.js-wokshop-container');
            if (targetContainer) {
                targetContainer.appendChild(newWorkshop);
            }

            fixWorkshopContainer(activeSessionContainer);
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
                event.preventDefault();
                selectWorkshop(selection);
                return;
            }

            var closeButton = event.target.closest('.js-workshop-close');
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
        updateSelectedWorkshopsField();
        updatePersonProgramDataField();
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


