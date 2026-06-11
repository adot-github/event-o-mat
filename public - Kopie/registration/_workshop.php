<?php

if (!defined('ABSPATH')) {
    exit;
}

$workshop_id = !empty($id) ? absint($id) : 0;
$slot_color  = !empty($str_slot_color) ? sanitize_hex_color_no_hash($str_slot_color) : 'eeeeee';
$lang        = !empty($lang) ? sanitize_key($lang) : 'de';

$workshops_obj = new Evtmgr_Workshops();
$presenters_obj   = new Evtmgr_Presenters();
$rooms_obj     = new Evtmgr_Rooms();
$audience_obj  = new Evtmgr_Audience();

$workshop = $workshops_obj->get_workshop_by_id($workshop_id, $lang);

if (empty($workshop)) {
    return;
}

$workshop_id = !empty($workshop['id'])
    ? absint($workshop['id'])
    : $workshop_id;

$persons = $presenters_obj->get_presenters_by_workshop_id($workshop_id, $lang);
$rooms   = $rooms_obj->get_room_by_workshop_id($workshop_id, $lang);

$audience = $audience_obj->get_target_audience_by_workshop_id($workshop_id, $lang);
$audience = str_ireplace(array('<br>', '<br/>', '<br />'), ' | ', $audience);

$max_registrations     = !empty($workshop['int_max_number_of_registrations']) ? (int) $workshop['int_max_number_of_registrations'] : 0;
$current_registrations = isset($workshop['int_number_of_registrations']) ? (int) $workshop['int_number_of_registrations'] : 0;

$is_booked_out = !empty($workshop['ysn_booked_out']);

if (
    $max_registrations > 0
    && $current_registrations >= $max_registrations
) {
    $is_booked_out = true;
}

$free_places = $max_registrations - $current_registrations;

if ($free_places < 0) {
    $free_places = 0;
}

$tmp_color = '';

if ($max_registrations > 0 && $current_registrations > 0) {
    $tmp_state = $current_registrations / $max_registrations;

    if ($tmp_state >= 0.8) {
        $tmp_color = 'FireBrick';
    } elseif ($tmp_state >= 0.5) {
        $tmp_color = 'DarkOrange';
    } else {
        $tmp_color = 'ForestGreen';
    }
}

if (!function_exists('event_registration_show_svg_icon')) {
    function event_registration_show_svg_icon($filename) {
        $filename = sanitize_file_name($filename);

        $path = plugin_dir_path(__FILE__) . '../icons/' . $filename;

        if (!is_readable($path)) {
            return '';
        }

        return file_get_contents($path);
    }
}

?>

<div class="workshop-item js-workshop-item" data-workshop="<?php echo esc_attr($workshop_id); ?>">

    <div class="icon-svg icon-dark worskhop-remove js-workshop-close" style="display:none">
        <?php echo event_registration_show_svg_icon('close.svg'); ?>
    </div>

    <?php
        $workshop_number = trim((string) ($workshop['str_workshop_number'] ?? ''));
        $workshop_title  = trim((string) ($workshop['str_workshop_title'] ?? ''));

        $display_title = $workshop_title;

        if ($workshop_number !== '') {
            $display_title = trim($workshop_number . ' | ' . $workshop_title);
        }
    ?>

    <div class="event-title" style="border-top-colour_off:#<?php echo esc_attr($slot_color); ?>">
        <h3 class="session-title m-0 mb-2">
            <?php echo esc_html($display_title); ?>
        </h3>
    </div>

    <?php if (!empty($workshop['mem_workshop_description'])) : ?>
        <div class="event-details">
            <?php echo wp_kses_post($workshop['mem_workshop_description']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty(trim($workshop['mem_workshop_description_long'] ?? ''))) : ?>
        <div class="event-details">
            <?php echo wp_kses_post($workshop['mem_workshop_description_long']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($persons)) : ?>
        <ul class="speaker-list m-0 no-border">
                <?php foreach ($persons as $person) : ?>
                <li>
                    <?php if (!empty($person['str_academic_title'])) : ?>
                        <?php echo esc_html($person['str_academic_title']); ?>
                    <?php endif; ?>

                    <?php echo esc_html(trim(($person['str_first_name'] ?? '') . ' ' . ($person['str_last_name'] ?? ''))); ?>

                    <?php if (!empty($person['str_job_title']) && !empty($person['str_institution'])) : ?>
                        | <?php echo esc_html($person['str_job_title']); ?>, <?php echo esc_html($person['str_institution']); ?><br>
                    <?php elseif (!empty($person['str_job_title'])) : ?>
                        | <?php echo esc_html($person['str_job_title']); ?><br>
                    <?php elseif (!empty($person['str_institution'])) : ?>
                        | <?php echo esc_html($person['str_institution']); ?><br>
                    <?php else : ?>
                        <br>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($rooms)) : ?>
        <?php $room = $rooms[0]; ?>
        <ul class="room-list mt-2 mb-0 no-border">
            <li>
                <?php if (!empty($room['str_room_number'])) : ?>
                <?php echo esc_html($room['str_room_number']); ?> |
                <?php endif; ?>
                <?php echo esc_html($room['str_room'] ?? ''); ?>
            </li>
        </ul>
    <?php endif; ?>

    <?php if (!empty(trim($audience))) : ?>
        <ul class="audience-list mt-2 mb-0 no-border">
            <li>
                <?php echo event_registration_show_svg_icon('label.svg'); ?>
                <?php echo esc_html($audience); ?>
            </li>
        </ul>
    <?php endif; ?>

    <?php if ($is_booked_out) : ?>

        <ul class="free-places-list mt-2">
            <li>ausgebucht</li>
        </ul>

    <?php elseif ($max_registrations > 0) : ?>

        <ul class="free-places-list mt-2 mb-0">
                <li>
                    Anzahl Plätze: <?php echo esc_html($max_registrations); ?>
                    <?php if ($max_registrations > 0) : ?>
                        |
                        <span style="color:<?php echo esc_attr($tmp_color); ?>">
                            Freie Plätze: <?php echo esc_html($free_places); ?>
                        </span>
                    <?php endif; ?>
                </li>
        </ul>
    <?php endif; ?>

    <?php if (!empty($workshop['num_price']) && (float) $workshop['num_price'] != 0) : ?>
        <ul class="price-list mt-2 mb-0 no-border">
            <li>
                CHF <?php echo esc_html(number_format((float) $workshop['num_price'], 2, '.', "'")); ?>
            </li>
        </ul>
    <?php endif; ?>
</div>