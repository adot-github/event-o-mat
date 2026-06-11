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

    <style>
        .event-registration-wrapper {
            max-width: 100%;
            margin: 1rem auto 6rem;
        }
        .event-registration-steps {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            margin: 1rem 0 1.5rem;
        }
        .event-registration-step {
            padding: .5rem .8rem;
            border: 0;
            border-bottom: 2px solid #d7dee8;
            color: #777;
            background: transparent;
            cursor: pointer;
        }
        .event-registration-step.is-active {
            border-color: #bfd400;
            color: #000;
            font-weight: 700;
        }
        .event-registration-step:hover,
        .event-registration-step:focus {
            color: #000;
            border-color: #9eaab8;
        }
        .event-registration-message,
        .event-registration-error {
            padding: .8rem 1rem;
            border-radius: .5rem;
            margin-bottom: 1rem;
        }
        .event-registration-message.is-success {
            background: #eef9f0;
            border: 1px solid #b9e2c0;
            color: #1d5f2b;
        }
        .event-registration-message.is-error,
        .event-registration-error {
            background: #fff3f3;
            border: 1px solid #f0b8b8;
            color: #8d2020;
        }
        .event-registration-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: .75rem;
        }

        .event-registration-wrapper p, .event-registration-wrapper li, .event-registration-wrapper td, .event-registration-wrapper th, .event-registration-wrapper label  {
            font-size:1rem;
        }
    </style>

    <div class="event-registration-wrapper">
        <?php foreach ($errors as $error) : ?>
            <div class="event-registration-error"><?php echo esc_html($error); ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <?php wp_nonce_field(Event_Registration::NONCE_ACTION, Event_Registration::NONCE_NAME); ?>
            <input type="hidden" name="current_step" value="<?php echo esc_attr($step); ?>">
            <input type="hidden" name="str_customer_cookie" value="<?php echo esc_attr($customer_cookie); ?>">

            <div class="event-registration-steps" aria-label="Registrationsschritte">
                <?php for ($i = 1; $i <= Event_Registration::MAX_STEP; $i++) : ?>
                    <?php if ((int) $step === Event_Registration::MAX_STEP) : ?>
                        <span class="event-registration-step <?php echo $i === (int) $step ? 'is-active' : ''; ?>">
                            <?php echo $wordings['schritt'] ?? ''; ?>&nbsp;<?php echo $i; ?>
                        </span>
                    <?php else : ?>
                        <button type="submit"
                                class="event-registration-step <?php echo $i === (int) $step ? 'is-active' : ''; ?>"
                                name="registration_step"
                                value="<?php echo esc_attr($i); ?>"
                                formnovalidate>
                            <?php echo $wordings['schritt'] ?? ''; ?>&nbsp;<?php echo $i; ?>
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

    <?php
    return ob_get_clean();
}
