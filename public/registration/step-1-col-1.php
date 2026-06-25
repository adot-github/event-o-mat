<?php
    $col_slot_id   = isset($col_slot_id) ? (int) $col_slot_id : 0;
    $col_index     = isset($col_index)   ? (int) $col_index   : 0;
    $col_slot      = isset($col_slot)    ? (array) $col_slot  : array();
    $col_lang      = $lang ?? 'de';
    $col_slot_name  = $col_slot['str_slot_name_' . $col_lang]
        ?? $col_slot['str_slot_name_de']
        ?? ($wordings['programm'] ?? 'programm');
    $col_slot_color_raw   = trim((string) ($col_slot['str_color'] ?? ''));
    $col_slot_color       = $col_slot_color_raw !== '' ? '#' . ltrim($col_slot_color_raw, '#') : '';
    $col_slot_color_light = '';
    if ($col_slot_color_raw !== '') {
        $hex = str_pad(ltrim($col_slot_color_raw, '#'), 6, '0');
        if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        $col_slot_color_light = sprintf(
            'rgb(%d,%d,%d)',
            (int) round($r + (255 - $r) * 0.75),
            (int) round($g + (255 - $g) * 0.75),
            (int) round($b + (255 - $b) * 0.75)
        );
    }
    $col_sessions = array_values(array_filter(
        $timetable_sessions,
        static fn($s) => empty($s['fullwidth'])
            && (
                !($s['is_slot_restricted'] ?? false)
                || in_array($col_slot_id, $s['session_slot_ids'], true)
            )
    ));
?>
<div class="stage" style="--column: <?php echo $col_index + 2; ?>;">
        <h2 class="stage-headline m-0"<?php if ($col_slot_color !== '') : ?> style="background-color: <?php echo esc_attr($col_slot_color); ?>;"<?php endif; ?>><?php echo esc_html($col_slot_name); ?></h2>

        <ol class="session-list">
            <?php foreach ($col_sessions as $session) : ?>
                <?php
                    $li_color_raw = trim((string) ($session['timezone_color'] ?? ''));
                    if ($li_color_raw === '') { $li_color_raw = $col_slot_color_raw; }
                    $li_bg_color = '';
                    if ($li_color_raw !== '') {
                        $li_hex = str_pad(ltrim($li_color_raw, '#'), 6, '0');
                        if (strlen($li_hex) === 3) { $li_hex = $li_hex[0].$li_hex[0].$li_hex[1].$li_hex[1].$li_hex[2].$li_hex[2]; }
                        $li_r = hexdec(substr($li_hex,0,2)); $li_g = hexdec(substr($li_hex,2,2)); $li_b = hexdec(substr($li_hex,4,2));
                        $li_bg_color = sprintf('rgb(%d,%d,%d)',
                            (int) round($li_r + (255 - $li_r) * 0.8),
                            (int) round($li_g + (255 - $li_g) * 0.8),
                            (int) round($li_b + (255 - $li_b) * 0.8)
                        );
                    }
                ?>
                <li class="session <?php echo esc_attr($session['session_class']); ?>"
                    style="--start: <?php echo esc_attr($session['time_from']); ?>; --end: <?php echo esc_attr($session['time_to']); ?>;<?php if ($li_bg_color !== '') echo ' background-color:' . $li_bg_color . ';'; ?>">
                        <div data-slot="<?php echo esc_attr((string) $session['slot_id']); ?>"
                                data-timezone="<?php echo esc_attr((string) $session['timezone_id']); ?>"
                                class="js-session-container session-eno session-1 track-all">

                            <?php if (!empty($debug_step1)) : ?>
                                <?php
                                $dbg_per_slot_summary = array();
                                foreach ((array) ($session['workshops_per_slot'] ?? array()) as $dbg_sid => $dbg_sd) {
                                    $dbg_per_slot_summary[$dbg_sid] = $dbg_sd['workshop_count'] ?? 0;
                                }
                                ?>
                                <details class="alert alert-warning small mb-2" open>
                                    <summary>
                                        Col slot_id=<?php echo esc_html((string) $col_slot_id); ?> |
                                        Tz <?php echo esc_html((string) $session['timezone_id']); ?> |
                                        session_slot_ids: <?php echo esc_html(json_encode($session['session_slot_ids'] ?? [])); ?> |
                                        wps keys/counts: <?php echo esc_html(json_encode($dbg_per_slot_summary)); ?>
                                    </summary>
                                    <pre class="mb-0 mt-2" style="white-space:pre-wrap;max-height:320px;overflow:auto;"><?php echo esc_html(print_r($session['debug'], true)); ?></pre>
                                </details>
                            <?php endif; ?>

                            <?php if (!empty($session['show_time_in_timezone_output'])) : ?>
                                <span class="time">
                                    <?php echo esc_html($session['time_label_from']); ?>–<?php echo esc_html($session['time_label_to']); ?> Uhr
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($session['show_text_in_timezone_output'])) : ?>
                                <h3 class="session-title mb-1 mt-2">
                                        <?php echo esc_html($session['timezone_name']); ?>
                                </h3>

                                <?php if (trim((string) $session['timezone_text']) !== '') : ?>
                                    <?php echo wp_kses_post($session['timezone_text']); ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (!empty($session['presenters'])) : ?>
                                <ul class="speaker-list icon-inverted no-border">
                                    <?php foreach ($session['presenters'] as $presenter) : ?>
                                        <li>
                                            <?php if (!empty($presenter['academic_title'])) : ?>
                                                <?php echo esc_html($presenter['academic_title']); ?>
                                            <?php endif; ?>

                                            <?php echo esc_html($presenter['name']); ?>

                                            <?php if (!empty($presenter['details'])) : ?>
                                                | <?php echo esc_html($presenter['details']); ?><br>
                                            <?php else : ?>
                                                <br>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php
                            $col_slot_workshops = $session['workshops_per_slot'][$col_slot_id]
                                ?? array('workshop_count' => 0, 'single_workshops' => array(), 'selected_workshops' => array(), 'workshop_options' => array());
                            $col_workshop_count = (int) ($col_slot_workshops['workshop_count'] ?? 0);
                            ?>
                            <?php if ($col_workshop_count === 1) : ?>
                                <div class="workshop mt-1">
                                    <?php foreach ($col_slot_workshops['single_workshops'] as $workshop_item) : ?>
                                        <div class="workshop-item">
                                            <?php echo $workshop_item['html']; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($col_workshop_count > 1) : ?>
                                <div class="time" style="display:none";>
                                    <?php echo esc_html($session['time_label_from']); ?>–<?php echo esc_html($session['time_label_to']); ?>
                                </div>

                                <div class="mt-2">
                                    <span class="mr-1">
                                        <?php echo $wordings['anzahl_angebote_zur_auswahl'] ?? 'anzahl_angebote_zur_auswahl'; ?>
                                        <?php echo esc_html((string) $col_workshop_count); ?>
                                    </span>

                                    <a href="#" class="btn btn-select-workshop btn-sm js-workshop-add ps-2 pe-2">
                                        <?php echo $wordings['angebot_auswaehlen'] ?? 'angebot_auswaehlen'; ?>
                                    </a>
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
                                    <?php foreach ($col_slot_workshops['selected_workshops'] as $workshop_item) : ?>
                                        <div class="col-md-12 mt-3 selected-workshop-wrapper"
                                                data-workshop="<?php echo esc_attr((string) $workshop_item['id']); ?>">
                                            <div class="workshop">
                                                <?php echo $workshop_item['html']; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="js-workshop-options" hidden>
                                    <?php foreach ($col_slot_workshops['workshop_options'] as $workshop_item) : ?>
                                        <template class="js-workshop-option-template"
                                                    data-workshop="<?php echo esc_attr((string) $workshop_item['id']); ?>">
                                            <div class="col-md-12 event-registration-modal-workshop js-workshop-select workshop-select"
                                                    data-workshop="<?php echo esc_attr((string) $workshop_item['id']); ?>">
                                                <div class="workshop p-1 m-1">
                                                    <?php echo $workshop_item['html']; ?>
                                                </div>
                                            </div>
                                        </template>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                </li>
            <?php endforeach; ?>
        </ol>
</div>