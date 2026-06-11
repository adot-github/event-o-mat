<?php

if (!defined('ABSPATH')) {
    exit;
}

$registration_values = isset($registration_values) && is_array($registration_values)
    ? $registration_values
    : array();

$event_uid = isset($event_uid) ? sanitize_text_field((string) $event_uid) : '';
$lang      = isset($lang) ? sanitize_key((string) $lang) : 'de';

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

    <h2><?php echo '$Ihre gewählten Optionen£'; ?></h2>

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
            <?php echo '$Es wurden keine Workshops ausgewählt.£'; ?>
        </div>

    <?php endif; ?>

    <h2><?php echo '$Ihre Kosten£'; ?></h2>

    <?php if (!empty($pricing_lines)) : ?>

        <table class="table table-striped table-bordered">
            <tbody>
                <?php foreach ($pricing_lines as $line) : ?>
                    <?php $is_total = !empty($line['is_total']); ?>
                    <tr>
                        <th scope="row">
                            <?php if ($is_total) : ?>
                                <?php echo esc_html($line['label'] ?? ''); ?>
                            <?php else : ?>
                                <?php echo esc_html($line['label'] ?? ''); ?>
                            <?php endif; ?>
                        </th>
                        <td>
                            <?php if (!empty($line['description'])) : ?>
                                <div class="mb-1">
                                    <?php echo wp_kses_post($line['description']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td nowrap>
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
                        <th scope="row"><?php echo '$Total£'; ?></th>
                        <td>
                            <?php echo esc_html(event_registration_format_price($total_cost)); ?> CHF
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else : ?>

        <div class="alert alert-warning">
            <?php echo '$Es wurde keine Preisoption ausgewählt.£'; ?>
        </div>

        <?php if ($total_cost !== '') : ?>
            <p>
                <strong><?php echo '$Total:£'; ?></strong>
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
            <?php echo '$Zurück£'; ?>
        </button>

        <button type="submit"
                name="registration_action"
                value="next"
                class="btn btn-primary">
            <?php echo '$Weiter£'; ?>
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
