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

<div class="container-fluid my-4 registration-step-4">

    <fieldset>

        <div class="row mt-4">
            <div class="col-12 col-md-9 form-field radio-group">

                <h3 style="margin-bottom:10px;"><?php echo $wordings['angaben_zur_verrechnung'] ?? 'angaben_zur_verrechnung'; ?></h3>
                <p><?php echo $wordings['die_rechnung_wird_ausgestellt_auf'] ?? 'die_rechnung_wird_ausgestellt_auf'; ?></p>

                <label>
                    <input type="radio"
                           name="int_billing_address"
                           value="1"
                           class="js-billing-address"
                           <?php checked((string) $int_billing_address, '1'); ?>>
                    <?php echo $wordings['privatadresse'] ?? 'privatadresse'; ?>
                </label>

                <label>
                    <input type="radio"
                           name="int_billing_address"
                           value="2"
                           class="js-billing-address"
                           <?php checked((string) $int_billing_address, '2'); ?>>
                    <?php echo $wordings['organisation_arbeitgeber'] ?? 'organisation_arbeitgeber'; ?>
                </label>

            </div>
        </div>

        <h3><?php echo $wordings['angaben_zu_ihrer_person'] ?? 'angaben_zu_ihrer_person'; ?></h3>

        <div class="row mt-4">
            <div class="col-12 col-md-3 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_salutation">
                        <?php echo $wordings['anrede'] ?? 'anrede'; ?><span class="required-marker">*</span>
                    </label>
                    <select name="str_salutation"
                            id="str_salutation"
                            class="form-control"
                            required>
                        <option value="" disabled <?php selected($str_salutation, ''); ?>><?php echo $wordings['anrede'] ?? 'anrede'; ?></option>
                        <option value="<?php echo $wordings['herr'] ?? 'herr'; ?>" <?php selected($str_salutation, $wordings['herr'] ?? 'herr'); ?>><?php echo $wordings['herr'] ?? 'herr'; ?></option>
                        <option value="<?php echo $wordings['frau'] ?? 'frau'; ?>" <?php selected($str_salutation, $wordings['frau'] ?? 'frau'); ?>><?php echo $wordings['frau'] ?? 'frau'; ?></option>
                    </select>
                </div>
            </div>

            <div class="col-12 col-md-3" style="display:none-off;">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_academic_title">
                        <?php echo $wordings['akad_titel'] ?? 'akad_titel'; ?>
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
            <div class="col-12 col-md-6 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_first_name">
                        <?php echo $wordings['vorname'] ?? 'vorname'; ?><span class="required-marker">*</span>
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
                        <?php echo $wordings['nachname'] ?? 'nachname'; ?><span class="required-marker">*</span>
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
            <div class="col-12 col-md-6 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_address">
                        <?php echo $wordings['adresse'] ?? 'adresse'; ?><span class="required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_address"
                           id="str_address"
                           value="<?php echo esc_attr($str_address); ?>"
                           class="form-control"
                           required>
                </div>
            </div>

            <div class="col-12 col-md-2 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_zip">
                        <?php echo $wordings['plz'] ?? 'plz'; ?>
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
                        <?php echo $wordings['ort'] ?? 'ort'; ?><span class="required-marker">*</span>
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
            <div class="col-12 col-md-6 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_job_title">
                        <?php echo $wordings['berufliche_funktion'] ?? 'berufliche_funktion'; ?>
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
                        <?php echo $wordings['land'] ?? 'land'; ?><span class="required-marker">*</span>
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
            <div class="col-12 col-md-6 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_email">
                        <?php echo $wordings['e_mail'] ?? 'e_mail'; ?><span class="required-marker">*</span>
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
                        <?php echo $wordings['telefon'] ?? 'telefon'; ?>
                    </label>
                    <input type="tel"
                           name="str_phone"
                           id="str_phone"
                           value="<?php echo esc_attr($str_phone); ?>"
                           class="form-control">
                </div>
            </div>
        </div>

        <h3 class="mt-5 mb-3"><?php echo $wordings['angaben_zu_organisation_arbeitgeber'] ?? 'angaben_zu_organisation_arbeitgeber'; ?></h3>

        <div class="row row_int_billing_address_2">

            <div class="col-12 col-md-6 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution">
                        <?php echo $wordings['organisation_arbeitgeber'] ?? 'organisation_arbeitgeber'; ?><span class="required-marker js-org-required-marker">*</span>
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
                        <?php echo $wordings['abteilung_institut'] ?? 'abteilung_institut'; ?>
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
            <div class="col-12 col-md-6 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution_Address">
                        <?php echo $wordings['adresse'] ?? 'adresse'; ?><span class="required-marker js-org-required-marker">*</span>
                    </label>
                    <input type="text"
                           name="str_institution_Address"
                           id="str_institution_Address"
                           value="<?php echo esc_attr($str_institution_Address); ?>"
                           class="form-control js-organisation-required">
                </div>
            </div>

            <div class="col-12 col-md-2 mb-4 mb-md-0">
                <div class="material-input material-input-outline">
                    <label class="form-label-off" for="str_institution_Zip">
                        <?php echo $wordings['plz'] ?? 'plz'; ?><span class="required-marker js-org-required-marker">*</span>
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
                        <?php echo $wordings['ort'] ?? 'ort'; ?><span class="required-marker js-org-required-marker">*</span>
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
            <?php echo $wordings['zurueck'] ?? 'zurueck'; ?>
        </button>

        <button type="submit"
                name="registration_action"
                value="next"
                class="btn btn-primary">
            <?php echo $wordings['jetzt_anmelden'] ?? 'jetzt_anmelden'; ?>
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