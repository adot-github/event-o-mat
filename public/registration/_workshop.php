<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    $workshop_id      = !empty($id) ? absint($id) : 0;
    $slot_color       = !empty($str_slot_color) ? sanitize_hex_color_no_hash($str_slot_color) : 'eeeeee';
    $lang             = !empty($lang) ? sanitize_key($lang) : 'de';
    $show_like_button = !empty($show_like_button);
    $is_liked         = !empty($is_liked);

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

    $categories = $workshops_obj->get_categories_by_workshop_id($workshop_id, $lang);

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
<style>
    .accordion-button-text-size {font-size: 1rem !important; line-height: 1.5rem !important;
    }
    .accordion-item:nth-child(odd) .accordion-button {
    background-color: transparent !important;
    }
    .accordion-item:nth-child(even) .accordion-button {
    background-color: transparent !important;
    }
    .workshop-item .js-workshop-close {margin-top:-57px;}

    .workshop-item .accordion-button, .workshop-item .accordion-item{border-radius:0px !important;}
    
</style>
<div class="workshop-item js-workshop-item"
     data-workshop="<?php echo esc_attr($workshop_id); ?>"
     data-liked="<?php echo $is_liked ? '1' : '0'; ?>"
     <?php if ($is_booked_out) : ?>data-booked-out="1"<?php endif; ?>>

    <div class="icon-dark worskhop-remove js-workshop-close" style="display:none">
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

    <?php
        $show_description      = !empty($workshop['mem_workshop_description']);
        $show_description_long = !empty(trim($workshop['mem_workshop_description_long'] ?? ''));
    ?>

    <?php if ($show_description || $show_description_long) : ?>
        <div class="accordion workshop-description-accordion" id="workshop-description-<?php echo esc_attr($workshop_id); ?>">
            <div class="accordion-item">
                <p class="accordion-header" id="heading-description-<?php echo esc_attr($workshop_id); ?>">
                    <button class="accordion-button accordion-button-text-size collapsed p-1 ps-2 pe-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-description-<?php echo esc_attr($workshop_id); ?>" aria-expanded="false" aria-controls="collapse-description-<?php echo esc_attr($workshop_id); ?>">
                        Beschreibung
                    </button>
                </p>
                <div id="collapse-description-<?php echo esc_attr($workshop_id); ?>" class="accordion-collapse collapse" aria-labelledby="heading-description-<?php echo esc_attr($workshop_id); ?>" data-bs-parent="#workshop-description-<?php echo esc_attr($workshop_id); ?>">
                    <div class="accordion-body p-2">
                        <?php if ($show_description) : ?>
                            <div class="event-teaser">
                                <?php echo wp_kses_post($workshop['mem_workshop_description']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_description_long) : ?>
                             <hr>
                            <div class="event-details">
                                <?php echo wp_kses_post($workshop['mem_workshop_description_long']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($persons)) : ?>
        <ul class="speaker-list no-border">
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
        <ul class="room-list no-border">
            <li>
                
                <?php if (!empty($room['str_room_number'])) : ?>
                <?php echo $wordings['raum'] ?? 'raum'; ?>
                <?php echo esc_html($room['str_room_number']); ?> |
                <?php endif; ?>
                <?php echo esc_html($room['str_room'] ?? ''); ?>
            </li>
        </ul>
    <?php endif; ?>

    <?php if (!empty(trim($audience))) : ?>
        <ul class="audience-list no-border">
            <li>
                <?php echo event_registration_show_svg_icon('label.svg'); ?>
                <?php echo esc_html($audience); ?>
            </li>
        </ul>
    <?php endif; ?>

    <?php if (!empty(trim($categories))) : ?>
        <ul class="category-list no-border">
            <li>
                <?php echo esc_html($categories); ?>
            </li>
        </ul>
    <?php endif; ?>

    <?php if ($is_booked_out) : ?>

        <ul class="free-places-list">
            <li>ausgebucht</li>
        </ul>

    <?php elseif ($max_registrations > 0) : ?>

        <ul class="free-places-list">
                <li>
                    <?php echo $wordings['anzahl_plaetze'] ?? 'anzahl_plaetze'; ?> <?php echo esc_html($max_registrations); ?>
                    <?php if ($max_registrations > 0) : ?>
                        |
                        <span style="color:<?php echo esc_attr($tmp_color); ?>">
                            <?php echo $wordings['freie_plaetze'] ?? 'freie_plaetze'; ?> <?php echo esc_html($free_places); ?>
                        </span>
                    <?php endif; ?>
                </li>
        </ul>
    <?php endif; ?>

    <?php if (!empty($workshop['num_price']) && (float) $workshop['num_price'] != 0) : ?>
        <ul class="price-list no-border">
            <li>
                <?php echo $wordings['preis'] ?? 'preis'; ?> CHF <?php echo esc_html(number_format((float) $workshop['num_price'], 2, '.', "'")); ?>
            </li>
        </ul>
    <?php endif; ?>

    <?php if ($show_like_button) : ?>
        <button type="button"
                class="js-workshop-like-button workshop-like-button<?php echo $is_liked ? ' is-liked' : ''; ?>"
                data-workshop-id="<?php echo esc_attr($workshop_id); ?>"
                data-event-uid="<?php echo esc_attr($workshop['fky_event_uid'] ?? ''); ?>"
                aria-pressed="<?php echo $is_liked ? 'true' : 'false'; ?>"
                aria-label="Auf die Merkliste setzen">
            <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/db-custom/event-registration/public/img/like.svg'); ?>" alt="" width="24" height="24">
        </button>
    <?php endif; ?>
</div>