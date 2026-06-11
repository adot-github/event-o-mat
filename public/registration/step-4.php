<style>
        :root {
        --mk-primary: #e91e63;
        --mk-gray: #d2d6da;
        --mk-muted: #7b809a;
        --mk-bg: #fff;
        }

        .material-input {
        position: relative;
        display: block;
        }

        .material-input .form-control {
        width: 100%;
        background-color: transparent;
        box-shadow: none;
        }

        .material-input .form-control:focus {
        outline: 0;
        box-shadow: none;
        }

        /* Outline */

        .material-input-outline .form-control {
        border:none;
        border-bottom: 2px solid var(--mk-gray);
        border-radius: 0;
        }

        .material-input-outline .form-control:focus {
        border-color: var(--mk-primary);
        }

        .material-input-outline label {
        position: absolute;
        top: 50%;
        left: 0.75rem;
        z-index: 2;
        margin: 0;
        padding: 0 0.25rem;
        color: var(--mk-muted);
        background: var(--mk-bg);
        pointer-events: none;
        transform: translateY(-50%);
        transform-origin: left top;
        transition:
            top 0.2s ease,
            left 0.2s ease,
            transform 0.2s ease,
            color 0.2s ease;
        }

        .material-input-outline.is-focused label,
        .material-input-outline.is-filled label {
        top: 0;
        left: 0.6rem;
        color: var(--mk-primary);
        transform: translateY(-50%) scale(0.85);
        }

        .material-input-outline.is-filled:not(.is-focused) label {
        color: var(--mk-muted);
        }

</style>

<style>
    .registration-step-4 .form-label {
        display: block;
        width: 100%;
        margin-bottom: 0.35rem;
        font-weight: 600;
    }

    .registration-step-4 input,
    .registration-step-4 select,
    .registration-step-4 textarea {
        width: 100%;
        max-width: 100%;
    }

    .registration-step-4 input[type="radio"] {
        width: auto;
    }

    .registration-step-4 .required-marker {
        color: #b00020;
        margin-left: 0.15rem;
    }

    .registration-step-4 .form-field {
        margin-bottom: 1rem;
    }

    .registration-step-4 .radio-group label {
        display: block;
        margin-bottom: 0.35rem;
        font-weight: 400;
    }

    .registration-step-4 .radio-group input[type="radio"] {
        width: auto;
        margin-right: 0.5rem;
        transform: scale(1.5);
    }
</style>
<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * TEMP DEBUG:
 * Open the page with ?debug_step4=1
 * Remove this block when everything works.
 */
if (isset($_GET['debug_step4']) && $_GET['debug_step4'] === '1') {
    echo '<pre style="background:#111;color:#0f0;padding:12px;white-space:pre-wrap;overflow:auto;">';
    echo "STEP 4 LOADED\n\n";

    echo "Customer cookie:\n";
    echo isset($customer_cookie) ? esc_html($customer_cookie) : 'NO $customer_cookie';
    echo "\n\n";

    echo "registration_values:\n";
    print_r(isset($registration_values) ? $registration_values : 'NO $registration_values');

    echo "\n\nall_data_for_current_cookie:\n";
    print_r(isset($all_data_for_current_cookie) ? $all_data_for_current_cookie : 'NO $all_data_for_current_cookie');

    echo "\n\nPOST:\n";
    print_r($_POST);

    echo '</pre>';
}

/**
 * The caller currently loads saved values into $registration_values.
 * Older versions used $all_data_for_current_cookie.
 */
$saved_step_data = array();

if (isset($registration_values) && is_array($registration_values)) {
    $saved_step_data = $registration_values;
} elseif (isset($all_data_for_current_cookie) && is_array($all_data_for_current_cookie)) {
    $saved_step_data = $all_data_for_current_cookie;
}

/**
 * Support both possible data shapes:
 *
 * 1. Simple:
 *    array(
 *      'strfirstname' => 'Max',
 *      'strlastname'  => 'Muster'
 *    )
 *
 * 2. Rows from DB:
 *    array(
 *      array('str_field_name' => 'str_first_name', 'str_field_value' => 'Max'),
 *      array('str_field_name' => 'str_last_name', 'str_field_value' => 'Muster')
 *    )
 */
$normalized_saved_step_data = array();

foreach ($saved_step_data as $key => $value) {
    if (is_array($value)) {
        $field_name = '';

        if (isset($value['str_field_name'])) {
            $field_name = $value['str_field_name'];
        } elseif (isset($value['str_field_name'])) {
            $field_name = $value['str_field_name'];
        } elseif (isset($value['field_name'])) {
            $field_name = $value['field_name'];
        } elseif (isset($value['name'])) {
            $field_name = $value['name'];
        }

        if ($field_name !== '') {
            if (isset($value['str_field_value'])) {
                $normalized_saved_step_data[$field_name] = $value['str_field_value'];
            } elseif (isset($value['str_field_value'])) {
                $normalized_saved_step_data[$field_name] = $value['str_field_value'];
            } elseif (isset($value['field_value'])) {
                $normalized_saved_step_data[$field_name] = $value['field_value'];
            } elseif (isset($value['value'])) {
                $normalized_saved_step_data[$field_name] = $value['value'];
            }

            continue;
        }
    }

    if (is_string($key)) {
        $normalized_saved_step_data[$key] = $value;
    }
}

/**
 * Case-insensitive value lookup.
 *
 * Your stored DB keys are lowercase:
 * - strsalutation
 * - intbillingaddress
 * - strfirstname
 * - strinstitution_zip
 *
 * Your HTML field names use mixed case:
 * - str_salutation
 * - int_billing_address
 * - str_first_name
 * - str_institution_Zip
 *
 * This helper makes both work.
 */
$get_saved_value = function($field_name, $default = '') use ($normalized_saved_step_data) {
    $field_name_lower = strtolower($field_name);

    foreach ($normalized_saved_step_data as $saved_key => $saved_value) {
        if (strtolower($saved_key) === $field_name_lower) {
            return $saved_value;
        }
    }

    foreach ($_POST as $posted_key => $posted_value) {
        if (strtolower($posted_key) === $field_name_lower) {
            return is_array($posted_value)
                ? ''
                : sanitize_text_field(wp_unslash($posted_value));
        }
    }

    return $default;
};

$int_billing_address = $get_saved_value('int_billing_address', '1');

$str_salutation           = $get_saved_value('str_salutation');
$str_academic_title        = $get_saved_value('str_academic_title');
$str_first_name            = $get_saved_value('str_first_name');
$str_last_name             = $get_saved_value('str_last_name');
$str_address              = $get_saved_value('str_address');
$str_zip                  = $get_saved_value('str_zip');
$str_city                 = $get_saved_value('str_city');
$str_country              = $get_saved_value('str_country', 'Schweiz');
$str_job_title             = $get_saved_value('str_job_title');
$str_email                = $get_saved_value('str_email');
$str_phone                = $get_saved_value('str_phone');

$str_institution          = $get_saved_value('str_institution');
$str_institution_Division = $get_saved_value('str_institution_Division');
$str_institution_Address  = $get_saved_value('str_institution_Address');
$str_institution_Zip      = $get_saved_value('str_institution_Zip');
$str_institution_City     = $get_saved_value('str_institution_City');

?>


<div class="container my-4 registration-step-4">

    <fieldset>

        <div class="row mt-4">
            <div class="col-12 col-md-9 form-field radio-group">

                <h3 style="margin-bottom:10px;"><?php echo $wordings['angaben_zur_verrechnung'] ?? ''; ?></h3>
                <p><?php echo $wordings['die_rechung_wird_ausgestellt_auf'] ?? ''; ?></p>

                <label>
                    <input type="radio"
                           name="int_billing_address"
                           value="1"
                           class="js-billing-address"
                           <?php checked((string) $int_billing_address, '1'); ?>>
                    <?php echo $wordings['privatadresse'] ?? ''; ?>
                </label>

                <label>
                    <input type="radio"
                           name="int_billing_address"
                           value="2"
                           class="js-billing-address"
                           <?php checked((string) $int_billing_address, '2'); ?>>
                    <?php echo $wordings['organisation_arbeitgeber'] ?? ''; ?>
                </label>

            </div>
        </div>

        <h3><?php echo $wordings['angaben_zu_ihrer_person'] ?? ''; ?></h3>

        <div class="row mt-4">
            <div class="col-12 col-md-3">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_salutation">
                        <?php echo $wordings['anrede'] ?? ''; ?><span class="required-marker">*</span>
                    </label>
                    <select name="str_salutation"
                            id="str_salutation"
                            class="form-control"
                            required>
                        <option value="" disabled <?php selected($str_salutation, ''); ?>>Anrede</option>
                        <option value="Herr" <?php selected($str_salutation, 'Herr'); ?>>Herr</option>
                        <option value="Frau" <?php selected($str_salutation, 'Frau'); ?>>Frau</option>
                    </select>
                </div>
            </div>

            <div class="col-12 col-md-3" style="display:none-off;">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_academic_title">
                        <?php echo $wordings['akad_titel'] ?? ''; ?>
                    </label>
                    <input type="text"
                           name="str_academic_title"
                           id="str_academic_title"
                           value="<?php echo esc_attr($str_academic_title); ?>"
                           class="form-control">
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_first_name">
                        <?php echo $wordings['vorname'] ?? ''; ?><span class="required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_first_name"
                           id="str_first_name"
                           value="<?php echo esc_attr($str_first_name); ?>"
                           class="form-control"
                           required>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_last_name">
                        <?php echo $wordings['nachname'] ?? ''; ?><span class="required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_last_name"
                           id="str_last_name"
                           value="<?php echo esc_attr($str_last_name); ?>"
                           class="form-control"
                           required>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_address">
                        <?php echo $wordings['adresse'] ?? ''; ?><span class="required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_address"
                           id="str_address"
                           value="<?php echo esc_attr($str_address); ?>"
                           class="form-control"
                           required>
                </div>
            </div>

            <div class="col-12 col-md-2">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_zip">
                        <?php echo $wordings['plz'] ?? ''; ?>
                    </label>
                    <input type="text"
                           name="str_zip"
                           id="str_zip"
                           value="<?php echo esc_attr($str_zip); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_city">
                        <?php echo $wordings['ort'] ?? ''; ?><span class="required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_city"
                           id="str_city"
                           value="<?php echo esc_attr($str_city); ?>"
                           class="form-control"
                           required>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_job_title">
                        <?php echo $wordings['berufliche_funktion'] ?? ''; ?>
                    </label>
                    <input type="text"
                           name="str_job_title"
                           id="str_job_title"
                           value="<?php echo esc_attr($str_job_title); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_country">
                        <?php echo $wordings['land'] ?? ''; ?><span class="required-marker">*</span>
                    </label>
                    <input type="text"
                           list="country-list"
                           id="str_country"
                           name="str_country"
                           value="<?php echo esc_attr($str_country); ?>"
                           class="form-control"
                           required>

                    <datalist id="country-list">
                        <option value="Schweiz"></option>
                        <option value="Deutschland"></option>
                        <option value="Österreich"></option>
                        <option value="Belgien"></option>
                        <option value="Italien"></option>
                        <option value="Frankreich"></option>
                        <option value="Luxemburg"></option>
                    </datalist>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_email">
                        <?php echo $wordings['e_mail'] ?? ''; ?><span class="required-marker">*</span>
                    </label>
                    <input type="email"
                           name="str_email"
                           id="str_email"
                           value="<?php echo esc_attr($str_email); ?>"
                           class="form-control"
                           required>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_phone">
                        <?php echo $wordings['telefon'] ?? ''; ?>
                    </label>
                    <input type="tel"
                           name="str_phone"
                           id="str_phone"
                           value="<?php echo esc_attr($str_phone); ?>"
                           class="form-control">
                </div>
            </div>
        </div>

        <h3 class="mt-5 mb-3"><?php echo $wordings['angaben_zu_organisation_arbeitgeber'] ?? ''; ?></h3>

        <div class="row row_int_billing_address_2">

            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution">
                        <?php echo $wordings['organisation_arbeitgeber'] ?? ''; ?><span class="required-marker js-org-required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_institution"
                           id="str_institution"
                           value="<?php echo esc_attr($str_institution); ?>"
                           class="form-control js-organisation-required">
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution_Division">
                        <?php echo $wordings['abteilung_institut'] ?? ''; ?>
                    </label>
                    <input type="text"
                           name="str_institution_Division"
                           id="str_institution_Division"
                           value="<?php echo esc_attr($str_institution_Division); ?>"
                           class="form-control">
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12 col-md-6">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution_Address">
                        <?php echo $wordings['adresse'] ?? ''; ?><span class="required-marker js-org-required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_institution_Address"
                           id="str_institution_Address"
                           value="<?php echo esc_attr($str_institution_Address); ?>"
                           class="form-control js-organisation-required">
                </div>
            </div>

            <div class="col-12 col-md-2">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution_Zip">
                        <?php echo $wordings['plz'] ?? ''; ?><span class="required-marker js-org-required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_institution_Zip"
                           id="str_institution_Zip"
                           value="<?php echo esc_attr($str_institution_Zip); ?>"
                           class="form-control js-organisation-required">
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution_City">
                        <?php echo $wordings['ort'] ?? ''; ?><span class="required-marker js-org-required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_institution_City"
                           id="str_institution_City"
                           value="<?php echo esc_attr($str_institution_City); ?>"
                           class="form-control js-organisation-required">
                </div>
            </div>

        </div>

    </fieldset>

    <div class="mt-4">
        <button type="submit"
                name="registration_action"
                value="prev"
                class="btn btn-secondary"
                formnovalidate>
            <?php echo $wordings['zurueck'] ?? ''; ?>
        </button>

        <button type="submit"
                name="registration_action"
                value="next"
                class="btn btn-primary">
            <?php echo $wordings['jetzt_anmelden'] ?? ''; ?>
        </button>
    </div>

</div>

<script>
    (function () {
        const billingRadios = document.querySelectorAll('input[name="int_billing_address"]');
        const organisationRequiredFields = document.querySelectorAll('.js-organisation-required');
        const organisationRequiredMarkers = document.querySelectorAll('.js-org-required-marker');

        function getBillingAddressValue() {
            const checked = document.querySelector('input[name="int_billing_address"]:checked');
            return checked ? checked.value : '1';
        }

        function updateOrganisationRequiredFields() {
            const isOrganisation = getBillingAddressValue() === '2';

            organisationRequiredFields.forEach(function (field) {
                field.required = isOrganisation;
            });

            organisationRequiredMarkers.forEach(function (marker) {
                marker.style.display = isOrganisation ? '' : 'none';
            });
        }

        billingRadios.forEach(function (radio) {
            radio.addEventListener('change', updateOrganisationRequiredFields);
        });

        updateOrganisationRequiredFields();
    })();
</script>

<script>
  function initMaterialInputs(scope = document) {
    scope.querySelectorAll('.material-input').forEach(group => {
      const input = group.querySelector('.form-control');

      if (!input) return;

      const updateFilledState = () => {
        group.classList.toggle('is-filled', input.value.trim() !== '');
      };

      input.addEventListener('focus', () => {
        group.classList.add('is-focused');
      });

      input.addEventListener('blur', () => {
        group.classList.remove('is-focused');
        updateFilledState();
      });

      input.addEventListener('input', updateFilledState);
      input.addEventListener('change', updateFilledState);

      updateFilledState();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initMaterialInputs();
  });
</script>