<?php
    /**
     * Public event registration process.
     *
     * Usage as shortcode:
     * [event_registration event_uid="xxxx-2026" lang="de"]
     */

    if (!defined('ABSPATH')) {
        exit;
    }

    if (!defined('EVENT_REGISTRATION_DIR')) {
        define('EVENT_REGISTRATION_DIR', __DIR__);
    }

    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-registration.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-registration-steps-field-data.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-events.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-time-zones.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-slots.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-workshops.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-persons.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-presenters.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-rooms.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-audience.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-wordings.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-pricing.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-registrations.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-registrations-workshops.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-registrations-billing.php';
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-evtmgr-workshop-likes.php';

    add_action('init', function () {
        add_shortcode('event_registration', 'event_registration_shortcode');
    });

    function event_registration_shortcode($atts = array()) {
        $atts = shortcode_atts(
            array(
                'event_uid' => 'LLL-2022',
                'lang'      => 'de',
            ),
            $atts,
            'event_registration'
        );

        $registration            = new Event_Registration();
        $registration_field_data = new Evtmgr_Registration_Steps_Field_Data();
        $wordings_obj            = new Evtmgr_Wordings();
        $event_uid               = sanitize_text_field((string) $atts['event_uid']);
        $lang                    = sanitize_key((string) $atts['lang']);
        $step                    = $registration->get_current_step();
        $errors                  = array();

        $cookie_result = $registration->ensure_registration_cookie();
        $customer_cookie = !empty($cookie_result['cookie'])
            ? sanitize_text_field($cookie_result['cookie'])
            : $registration->get_registration_cookie();

        if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '')) {
            if (!$registration->verify_nonce()) {
                $errors[] = 'Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.';
            } else {
                $posted_step = isset($_POST['current_step'])
                    ? absint(wp_unslash($_POST['current_step']))
                    : (int) $step;

                if ($posted_step < 1) {
                    $posted_step = 1;
                }

                $posted_values = $registration->collect_posted_values();

                if (!empty($posted_values)) {
                    $saved = $registration_field_data->insert_step_fields(
                        $posted_values,
                        $customer_cookie
                    );

                    if (false === $saved) {
                        $errors[] = 'Die Daten konnten nicht gespeichert werden.';
                    }
                }

                $step   = $posted_step;
                $action = $registration->get_post_action();

                if ('new_registration' === $action) {
                    $registration->clear_registration_cookies();
                    $customer_cookie = '';
                    $_POST['str_customer_cookie'] = '';
                    $step = 1;
                } elseif ('next' === $action) {
                    $step++;
                } elseif ('prev' === $action) {
                    $step--;
                } elseif (isset($_POST['registration_step'])) {
                    $step = absint(wp_unslash($_POST['registration_step']));
                }

                $step = max(1, min(Event_Registration::MAX_STEP, (int) $step));
                $registration->persist_current_step($step);
            }
        } else {
            $registration->persist_current_step($step);
        }

        $registration_values = $registration_field_data->get_all_values_for_current_cookie($customer_cookie);
        $wordings = $wordings_obj->get_wordings($lang, $event_uid);

        if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '')) {
            $registration_values = array_merge($registration_values, $registration->collect_posted_values());
        }

        ob_start();
?>

<div class="event-registration-wrapper">
    <?php foreach ($errors as $error) : ?>
        <div class="event-registration-error"><?php echo esc_html($error); ?></div>
    <?php endforeach; ?>

    <div id="event-registration-loading-overlay" class="event-registration-loading-overlay" style="display:none;">
        <div class="event-registration-loading-box">
            <div class="event-registration-spinner" aria-hidden="true"></div>
            <div class="event-registration-loading-text">
                <?php echo esc_html($wordings['bitte_warten'] ?? 'Bitte warten!'); ?>
            </div>
        </div>
    </div>

    <style>
        .event-registration-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.85);
        }

        .event-registration-loading-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .event-registration-spinner {
            width: 48px;
            height: 48px;
            border: 5px solid rgba(0, 0, 0, 0.15);
            border-top-color: #0f4f79;
            border-radius: 50%;
            animation: event-registration-spin 0.8s linear infinite;
        }

        .event-registration-loading-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f4f79;
        }

        @keyframes event-registration-spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <form method="post" action="">
        <?php wp_nonce_field(Event_Registration::NONCE_ACTION, Event_Registration::NONCE_NAME); ?>
        <input type="hidden" name="current_step" value="<?php echo esc_attr($step); ?>">
        <input type="hidden" name="str_customer_cookie" value="<?php echo esc_attr($customer_cookie); ?>">

        <div class="event-registration-steps" aria-label="Registrierungsschritte">
            <?php for ($i = 1; $i <= Event_Registration::MAX_STEP; $i++) : ?>
                <?php if ((int) $step === Event_Registration::MAX_STEP) : ?>
                    <span class="event-registration-step <?php echo $i === (int) $step ? 'is-active' : ''; ?>">
                        <?php echo $wordings['schritt'] ?? 'schritt'; ?>&nbsp;<?php echo $i; ?>
                    </span>
                <?php else : ?>
                    <button type="submit"
                            class="event-registration-step <?php echo $i === (int) $step ? 'is-active' : ''; ?>"
                            name="registration_step"
                            value="<?php echo esc_attr($i); ?>"
                            formnovalidate>
                        <?php echo $wordings['schritt'] ?? 'schritt'; ?>&nbsp;<?php echo $i; ?>
                    </button>
                <?php endif; ?>
            <?php endfor; ?>
        </div>

        <?php
        $step_file = EVENT_REGISTRATION_DIR . '/registration/step-' . (int) $step . '.php';
        if (file_exists($step_file)) {
            require $step_file;
        }
        ?>
    </form>
</div>

<script>
    (function () {
        var form    = document.querySelector('.event-registration-wrapper form');
        var overlay = document.getElementById('event-registration-loading-overlay');

        if (!form || !overlay) {
            return;
        }

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            var currentStepInput = form.querySelector('input[name="current_step"]');
            var isFinalSubmit = submitter
                && submitter.name === 'registration_action'
                && submitter.value === 'next'
                && currentStepInput
                && currentStepInput.value === String(<?php echo (int) Event_Registration::MAX_STEP - 1; ?>);

            if (!isFinalSubmit) {
                return;
            }

            overlay.style.display = 'flex';

            // Deferred: disabling the submitter synchronously here would drop
            // its name=value pair from the submitted form data.
            window.setTimeout(function () {
                form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                    button.disabled = true;
                });
            }, 0);
        });
    })();
</script>

<?php
        return ob_get_clean();
    }
