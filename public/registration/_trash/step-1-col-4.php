            <div class="stage" style="--column: 5;">
                <h2 class="stage-headline m-0">Programm</h2>

                <ol class="session-list">
                    <?php foreach ($timetable_sessions as $session) : ?>
                        <li class="session <?php echo esc_attr($session['session_class']); ?>"
                            style="--start: <?php echo esc_attr($session['time_from']); ?>; --end: <?php echo esc_attr($session['time_to']); ?>;">

                            <div data-slot="<?php echo esc_attr((string) $session['slot_id']); ?>"
                                 data-timezone="<?php echo esc_attr((string) $session['timezone_id']); ?>"
                                 class="js-session-container session-eno session-1 track-all">

                                <?php if (!empty($debug_step1)) : ?>
                                    <details class="alert alert-warning small mb-2" open>
                                        <summary>
                                            Step 1 Debug — timezone <?php echo esc_html((string) $session['timezone_id']); ?>,
                                            source: <?php echo esc_html((string) ($session['debug']['source_mode'] ?? '')); ?>,
                                            slot: <?php echo esc_html((string) ($session['debug']['final_slot_id'] ?? 0)); ?>,
                                            workshops: <?php echo esc_html((string) ($session['debug']['final_workshop_count'] ?? 0)); ?>
                                        </summary>
                                        <pre class="mb-0 mt-2" style="white-space:pre-wrap;max-height:320px;overflow:auto;"><?php echo esc_html(print_r($session['debug'], true)); ?></pre>
                                    </details>
                                <?php endif; ?>

                                <?php if (!empty($session['show_time_in_timezone_output'])) : ?>
                                    <span class="time">
                                        <?php echo esc_html($session['time_label_from']); ?>–<?php echo esc_html($session['time_label_to']); ?>
                                    </span>
                                <?php endif; ?>

                                <h3 class="session-title m-0">
                                        <?php echo esc_html($session['timezone_name']); ?>
                                    </h3>

                                    <?php if (trim((string) $session['timezone_text']) !== '') : ?>
                                        <p><?php echo wp_kses_post($session['timezone_text']); ?></p>
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

                                <?php if ($session['workshop_count'] === 1) : ?>
                                    <div class="workshop">
                                        <?php foreach ($session['single_workshops'] as $workshop_item) : ?>
                                            <div class="workshop-item">
                                                <?php echo $workshop_item['html']; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($session['workshop_count'] > 1) : ?>
                                    <div class="time" style="display:none";>
                                        <?php echo esc_html($session['time_label_from']); ?>–<?php echo esc_html($session['time_label_to']); ?>
                                    </div>

                                    <div class="mt-2">
                                        <span class="mr-1">
                                            <?php echo $wordings['anzahl_angebote_zur_auswahl'] ?? 'anzahl_angebote_zur_auswahl'; ?>
                                            <?php echo esc_html((string) $session['workshop_count']); ?>
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
                                        <?php foreach ($session['selected_workshops'] as $workshop_item) : ?>
                                            <div class="col-md-12 mt-1 selected-workshop-wrapper"
                                                 data-workshop="<?php echo esc_attr((string) $workshop_item['id']); ?>">
                                                <div class="workshop">
                                                    <?php echo $workshop_item['html']; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="js-workshop-options" hidden>
                                        <?php foreach ($session['workshop_options'] as $workshop_item) : ?>
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