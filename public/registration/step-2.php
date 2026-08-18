<?php

    if (!defined('ABSPATH')) {
        exit;
    }

    $registration_values = isset($registration_values) && is_array($registration_values)
        ? $registration_values
        : array();

    $wordings = isset($wordings) && is_array($wordings)
        ? $wordings
        : array();

    $event_uid   = isset($event_uid) ? sanitize_text_field((string) $event_uid) : '';
    $lang        = isset($lang) ? sanitize_key((string) $lang) : 'de';
    $lang_key    = strtoupper($lang);
    $debug_step2 = isset($_GET['debug_step2']) && $_GET['debug_step2'] === '1';

    $selected_workshops = isset($registration_values['selected_workshops'])
        ? sanitize_text_field((string) $registration_values['selected_workshops'])
        : '';

    $selected_workshop_ids = array();

    if ($selected_workshops !== '') {
        $selected_workshop_ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $selected_workshops)))));
    }

    $pricing_group_saved = isset($registration_values['pricing_group'])
        ? sanitize_text_field((string) $registration_values['pricing_group'])
        : '';

    $total_cost_saved = isset($registration_values['total_cost'])
        ? sanitize_text_field((string) $registration_values['total_cost'])
        : '';

    // Per-timezone validation: collect required timezones with no selection.
    $tz_obj               = new Evtmgr_Time_Zones();
    $missing_timezones    = $tz_obj->get_uncovered_required_timezones($event_uid, $selected_workshop_ids, $lang);
    $has_missing_selection = !empty($missing_timezones);

    $pricing_obj = new Evtmgr_Pricing();
    $qry_billings = $pricing_obj->build_registration_pricing_options($event_uid, $lang, $selected_workshop_ids, $wordings);
    $pricing_group_saved_normalized = $pricing_obj->normalize_pricing_group($pricing_group_saved);

    if (!function_exists('event_registration_format_discount_date')) {
        function event_registration_format_discount_date($date_value) {
            if (empty($date_value)) {
                return '';
            }

            $timestamp = strtotime((string) $date_value);

            if (!$timestamp) {
                return '';
            }

            return date_i18n(get_option('date_format'), $timestamp);
        }
    }

    if (!function_exists('event_registration_discount_text')) {
        function event_registration_discount_text($pricing, $lang_key) {
            $field = 'mem_pricing_description' . $lang_key;

            if (!empty($pricing[$field])) {
                return $pricing[$field];
            }

            if (!empty($pricing['mem_pricing_description'])) {
                return $pricing['mem_pricing_description'];
            }

            return '';
        }
    }

    if (!function_exists('event_registration_step2_format_price')) {
        function event_registration_step2_format_price($value) {
            if ($value === '' || $value === null) {
                return '';
            }

            return number_format((float) $value, 2, '.', "'");
        }
    }

    $selected_pricing_option = array();

    if (!empty($qry_billings)) {
        foreach ($qry_billings as $option) {
            $option_group = $pricing_obj->normalize_pricing_group($option['pricing_group'] ?? '');

            if ($pricing_group_saved_normalized !== '' && $option_group === $pricing_group_saved_normalized) {
                $selected_pricing_option = $option;
                break;
            }
        }

        if (empty($selected_pricing_option)) {
            $selected_pricing_option = $qry_billings[0];
        }
    }

    $calculated_total_cost = $total_cost_saved;

    if ($calculated_total_cost === '' && !empty($selected_pricing_option)) {
        $calculated_total_cost = (string) ($selected_pricing_option['total_cost'] ?? '');
    }

    $selected_pricing_group_value = !empty($selected_pricing_option)
        ? (string) ($selected_pricing_option['pricing_group'] ?? '')
        : $pricing_group_saved_normalized;

?>

<div class="container my-4 event-registration-step-2">

    <?php if ($debug_step2) : ?>
        <pre style="background:#111;color:#0f0;padding:12px;white-space:pre-wrap;overflow:auto;font-size:11px;">
STEP 2 DEBUG

event_uid: <?php echo esc_html($event_uid); ?>

lang: <?php echo esc_html($lang); ?>

selected_workshop_ids:
<?php print_r($selected_workshop_ids); ?>

pricing_group_saved: <?php echo esc_html($pricing_group_saved); ?>

total_cost_saved: <?php echo esc_html($total_cost_saved); ?>

qry_billings:
<?php print_r($qry_billings); ?>

selected_pricing_option:
<?php print_r($selected_pricing_option); ?>

registration_values:
<?php print_r($registration_values); ?>
        </pre>
    <?php endif; ?>

    <!--<h2>Schritt 2</h2>-->

    <?php if ($has_missing_selection) : ?>

        <?php foreach ($missing_timezones as $missing_tz) :
            $tz_name  = trim((string) ($missing_tz['str_timezone_name']       ?? ''));
            $tz_remark = trim((string) ($missing_tz['mem_remark_on_no_selection'] ?? ''));
        ?>
        <div class="alert alert-warning">
            <?php if ($tz_remark !== '') : ?>
                <?php echo wp_kses_post($tz_remark); ?>
            <?php elseif ($tz_name !== '') : ?>
                <?php echo esc_html($tz_name); ?>: <?php echo $wordings['sie_haben_keine_workshops_ausgewaehlt_bitte_gehen_sie_einen_schritt_zurueck'] ?? 'sie_haben_keine_workshops_ausgewaehlt_bitte_gehen_sie_einen_schritt_zurueck'; ?>
            <?php else : ?>
                <?php echo $wordings['sie_haben_keine_workshops_ausgewaehlt_bitte_gehen_sie_einen_schritt_zurueck'] ?? 'sie_haben_keine_workshops_ausgewaehlt_bitte_gehen_sie_einen_schritt_zurueck'; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    <?php else : ?>

        <header class="h3 mb-3">
            <?php
            echo $wordings['auswahl_fuer_die_preisberechnung'] ?? 'auswahl_fuer_die_preisberechnung';
            ?>
        </header>

        <fieldset>
            <div class="alert alert-info">
                <p class="mb-0">
                    <?php
                        echo $wordings['die_kosten_fuer_den_kongress_sind_nicht_fuer_alle_teilnehmenden_gleich'] ?? 'die_kosten_fuer_den_kongress_sind_nicht_fuer_alle_teilnehmenden_gleich';
                    ?>
                </p>
            </div>

            <?php if (!empty($qry_billings)) : ?>

                <section>
                    <div class="table-responsive">
                        <table class="table align-middle" style="width:100%">
                            <tbody>
                                <?php foreach ($qry_billings as $discount_index => $pricing_option) : ?>

                                    <?php
                                    $parent = $pricing_option['parent'] ?? array();
                                    $pricing_group_value = (string) ($pricing_option['pricing_group'] ?? '');
                                    $pricing_group_normalized = $pricing_obj->normalize_pricing_group($pricing_group_value);
                                    $total_price = isset($pricing_option['total_cost']) ? (float) $pricing_option['total_cost'] : 0.0;
                                    $valid_from = event_registration_format_discount_date($parent['dtm_date_valid_from'] ?? '');
                                    $valid_to = event_registration_format_discount_date($parent['dtm_date_valid_to'] ?? '');
                                    $text = event_registration_discount_text($parent, $lang_key);

                                    if ($pricing_group_saved_normalized !== '') {
                                        $checked = ($pricing_group_saved_normalized === $pricing_group_normalized);
                                    } else {
                                        $checked = (0 === (int) $discount_index);
                                    }

                                    $lines = !empty($pricing_option['lines']) && is_array($pricing_option['lines'])
                                        ? $pricing_option['lines']
                                        : array();

                                    $parent_line = !empty($lines[0]) ? $lines[0] : array();
                                    $group_class = ($discount_index % 2 === 0) ? 'pricing-group-odd' : '';
                                    ?>

                                    <tr class="<?php echo esc_attr($group_class); ?>">
                                        <td>
                                            <label class="d-block mb-0">
                                                <input type="radio"
                                                       name="pricing_group"
                                                       value="<?php echo esc_attr($pricing_group_value); ?>"
                                                       data-price="<?php echo esc_attr((string) $total_price); ?>"
                                                       <?php checked($checked); ?>>
                                                <strong><?php echo esc_html($parent['str_pricing_name'] ?? ''); ?></strong>
                                            </label>

                                            <?php if (!empty(trim($text))) : ?>
                                                <p class="mb-0 mt-1" style="line-height:110%;">
                                                    <em><?php echo wp_kses_post($text); ?></em>
                                                </p>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding-left:10px;">
                                            <?php if ($valid_from !== '') : ?>
                                                <?php echo esc_html('ab ' . $valid_from); ?>
                                            <?php endif; ?>

                                            <?php if ($valid_to !== '') : ?>
                                                <?php echo esc_html('bis ' . $valid_to); ?>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding-left:10px;" nowrap>
                                            <?php if (isset($parent_line['amount'])) : ?>
                                                <?php echo esc_html(event_registration_step2_format_price($parent_line['amount'])); ?> CHF
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php foreach (array_slice($lines, 1) as $line) : ?>
                                        <?php
                                        $is_total = !empty($line['is_total']);
                                        $row_class = $is_total
                                            ? 'event-registration-pricing-total'
                                            : 'event-registration-pricing-child';
                                        ?>
                                        <tr class="<?php echo esc_attr(trim($row_class . ' ' . $group_class)); ?>">
                                            <td style="padding-left:3rem;">
                                                <?php if ($is_total) : ?>
                                                    <?php echo esc_html($line['label'] ?? ''); ?>
                                                <?php else : ?>
                                                    <?php echo esc_html($line['label'] ?? ''); ?>
                                                <?php endif; ?>

                                                <?php if (!$is_total && !empty($line['description'])) : ?>
                                                    <p class="mb-0 mt-1" style="line-height:110%;">
                                                        <em><?php echo wp_kses_post($line['description']); ?></em>
                                                    </p>
                                                <?php endif; ?>
                                            </td>
                                            <td></td>
                                            <td style="padding-left:10px;" nowrap>
                                                <?php if ($is_total) : ?>
                                                    <?php echo esc_html(event_registration_step2_format_price($line['amount'] ?? 0)); ?> CHF
                                                <?php else : ?>
                                                    <?php echo esc_html(event_registration_step2_format_price($line['amount'] ?? 0)); ?> CHF
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3 d-none">
                        <label for="total_cost" class="form-label">Total Cost</label>
                        <input type="text"
                               name="total_cost"
                               id="total_cost"
                               class="form-control"
                               value="<?php echo esc_attr($calculated_total_cost); ?>"
                               readonly>
                    </div>
                </section>

            <?php else : ?>

                <div class="alert alert-warning">
                    <?php echo $wordings['es_sind_keine_preisoptionen_verfuegbar'] ?? 'es_sind_keine_preisoptionen_verfuegbar'; ?>
                </div>

            <?php endif; ?>
        </fieldset>

    <?php endif; ?>

    <input type="hidden"
           name="selected_workshops"
           value="<?php echo esc_attr(implode(',', $selected_workshop_ids)); ?>">

    <div class="event-registration-actions">
        <button type="submit"
                name="registration_action"
                value="prev"
                class="btn btn-secondary">
            <?php echo $wordings['zurueck'] ?? 'zurueck'; ?>
        </button>

        <?php if (!$has_missing_selection) : ?>
            <button type="submit"
                    name="registration_action"
                    value="next"
                    class="btn btn-primary">
                <?php
                echo $wordings['weiter_in_der_anmeldung'] ?? 'weiter_in_der_anmeldung';
                ?>
            </button>
        <?php endif; ?>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const totalCostField = document.getElementById('total_cost');
        const discountRadios = document.querySelectorAll('input[name="pricing_group"][data-price]');

        if (!totalCostField || !discountRadios.length) {
            return;
        }

        function updateTotalCost() {
            const selected = document.querySelector('input[name="pricing_group"][data-price]:checked');
            totalCostField.value = selected ? selected.dataset.price || '' : '';
        }

        discountRadios.forEach(function (radio) {
            radio.addEventListener('change', updateTotalCost);
        });

        updateTotalCost();
    });
</script>
