<?php

    if (!defined('ABSPATH')) {
        exit;
    }

    $registration_values = isset($registration_values) && is_array($registration_values)
        ? $registration_values
        : array();

    $event_uid   = isset($event_uid) ? sanitize_text_field((string) $event_uid) : '';
    $lang        = isset($lang) ? sanitize_key((string) $lang) : 'de';
    $debug_step3 = isset($_GET['debug_step3']) && $_GET['debug_step3'] === '1';

    $selected_workshops = isset($registration_values['selected_workshops'])
        ? sanitize_text_field((string) $registration_values['selected_workshops'])
        : '';

    $selected_workshop_ids = array();

    if ($selected_workshops !== '') {
        $selected_workshop_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', explode(',', $selected_workshops))
                )
            )
        );
    }

    $pricing_group = isset($registration_values['pricing_group'])
        ? sanitize_text_field((string) $registration_values['pricing_group'])
        : '';

    $total_cost = isset($registration_values['total_cost'])
        ? sanitize_text_field((string) $registration_values['total_cost'])
        : '';

    $workshops_obj = new Evtmgr_Workshops();
    $pricing_obj = new Evtmgr_Pricing();

    $selected_workshop_rows = array();

    foreach ($selected_workshop_ids as $workshop_id) {
        $workshop = $workshops_obj->get_workshop_by_id($workshop_id, $lang);

        if (!empty($workshop)) {
            $selected_workshop_rows[] = $workshop;
        }
    }

    $selected_pricing_option = $pricing_obj->find_registration_pricing_option(
        $event_uid,
        $lang,
        $selected_workshop_ids,
        $pricing_group
    );

    if (!empty($selected_pricing_option)) {
        $pricing_group = (string) ($selected_pricing_option['pricing_group'] ?? $pricing_group);

        if ($total_cost === '') {
            $total_cost = (string) ($selected_pricing_option['total_cost'] ?? '');
        }
    }

    if (!function_exists('event_registration_format_price')) {
        function event_registration_format_price($value) {
            if ($value === '' || $value === null) {
                return '';
            }

            return number_format((float) $value, 2, '.', "'");
        }
    }

    $pricing_lines = !empty($selected_pricing_option['lines']) && is_array($selected_pricing_option['lines'])
        ? $selected_pricing_option['lines']
        : array();

?>

<div class="container my-4 event-registration-step-3">

    <?php if ($debug_step3) : ?>
        <pre style="background:#111;color:#0f0;padding:12px;white-space:pre-wrap;overflow:auto;font-size:11px;">
STEP 3 DEBUG

event_uid: <?php echo esc_html($event_uid); ?>

lang: <?php echo esc_html($lang); ?>

selected_workshop_ids:
<?php print_r($selected_workshop_ids); ?>

pricing_group: <?php echo esc_html($pricing_group); ?>

total_cost: <?php echo esc_html($total_cost); ?>

pricing_lines:
<?php print_r($pricing_lines); ?>

selected_workshop_rows:
<?php print_r($selected_workshop_rows); ?>

registration_values:
<?php print_r($registration_values); ?>
        </pre>
    <?php endif; ?>

    <h2><?php echo $wordings['ihre_gewaehlten_optionen'] ?? 'ihre_gewaehlten_optionen'; ?></h2>

    <?php if (!empty($selected_workshop_rows)) : ?>

        <ul class="list-group mb-4">
            <?php foreach ($selected_workshop_rows as $workshop) : ?>
                <?php
                $workshop_id = !empty($workshop['id'])
                    ? absint($workshop['id'])
                    : 0;
                ?>
                <li class="list-group-item">
                    <?php if (!empty($workshop['str_workshop_number'])) : ?>
                        <strong><?php echo esc_html($workshop['str_workshop_number']); ?></strong>
                        &ndash;
                    <?php endif; ?>

                    <?php echo esc_html($workshop['str_workshop_title'] ?? ''); ?>

                    <?php if ($workshop_id > 0) : ?>
                        <span class="text-muted">#<?php echo esc_html($workshop_id); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php else : ?>

        <div class="alert alert-warning">
            <?php echo $wordings['es_wurden_keine_workshops_ausgewaehlt'] ?? 'es_wurden_keine_workshops_ausgewaehlt'; ?>
        </div>

    <?php endif; ?>

    <h2><?php echo $wordings['ihre_kosten'] ?? 'ihre_kosten'; ?></h2>

    <?php if (!empty($pricing_lines)) : ?>

        <table class="table table-striped table-bordered">
            <tbody>
                <?php foreach ($pricing_lines as $line) : ?>
                    <?php $is_total = !empty($line['is_total']); ?>
                    <tr>
                        <td style="text-align:left;font-weight:<?php echo $is_total ? 'bold' : 'normal'; ?>;padding:4px 8px;border-bottom:1px solid #ddd;">
                            <?php echo esc_html($line['label'] ?? ''); ?>
                            <?php if (!empty($line['description'])) : ?>
                                <br><?php echo wp_kses_post($line['description']); ?>
                            <?php endif; ?>
                        </td>
                        <td nowrap style="padding:4px 4px 4px 16px;border-bottom:1px solid #ddd;">
                            <?php if ($is_total) : ?>
                                <strong><?php echo esc_html(event_registration_format_price($line['amount'] ?? 0)); ?> CHF</strong>
                            <?php else : ?>
                                <?php echo esc_html(event_registration_format_price($line['amount'] ?? 0)); ?> CHF
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($selected_pricing_option['has_additional_lines'])) : ?>
                    <tr>
                        <th scope="row" style="text-align:left;padding:4px 8px;border-bottom:1px solid #ddd;"><?php echo $wordings['total'] ?? 'total'; ?></th>
                        <td style="padding:4px 4px 4px 16px;border-bottom:1px solid #ddd;">
                            <?php echo esc_html(event_registration_format_price($total_cost)); ?> CHF
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else : ?>

        <div class="alert alert-warning">
            <?php echo $wordings['es_wurde_keine_preisoption_ausgewaehlt'] ?? 'es_wurde_keine_preisoption_ausgewaehlt'; ?>
        </div>

        <?php if ($total_cost !== '') : ?>
            <p>
                <strong><?php echo $wordings['total'] ?? 'total'; ?></strong>
                <?php echo esc_html(event_registration_format_price($total_cost)); ?> CHF
            </p>
        <?php endif; ?>

    <?php endif; ?>

    <input type="hidden"
           name="selected_workshops"
           value="<?php echo esc_attr(implode(',', $selected_workshop_ids)); ?>">

    <input type="hidden"
           name="pricing_group"
           value="<?php echo esc_attr($pricing_group); ?>">

    <input type="hidden"
           name="total_cost"
           value="<?php echo esc_attr($total_cost); ?>">

    <textarea id="person_billling_data"
              name="person_billling_data"
              style="display:none;"></textarea>

    <div class="event-registration-actions mt-4">
        <button type="submit"
                name="registration_action"
                value="prev"
                class="btn btn-secondary">
            <?php echo $wordings['zurueck'] ?? 'zurueck'; ?>
        </button>

        <button type="submit"
                name="registration_action"
                value="next"
                class="btn btn-primary">
            <?php echo $wordings['weiter'] ?? 'weiter'; ?>
        </button>
    </div>

</div>


<script>
    (function () {
        function updatePersonBillingData() {
            const source = document.querySelector('.event-registration-step-3');
            const field = document.getElementById('person_billling_data');

            if (!source || !field) {
                return;
            }

            const clone = source.cloneNode(true);

            clone.querySelectorAll('.event-registration-actions').forEach(function (actions) {
                actions.remove();
            });

            const ownField = clone.querySelector('#person_billling_data');
            if (ownField) {
                ownField.remove();
            }

            field.value = clone.innerHTML;
        }

        document.addEventListener('submit', function () {
            updatePersonBillingData();
        }, true);

        document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            button.addEventListener('click', function () {
                updatePersonBillingData();
            });
        });
    })();
</script>
