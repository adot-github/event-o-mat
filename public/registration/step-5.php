<?php

    if (!defined('ABSPATH')) {
        exit;
    }

    if (!class_exists('Evtmgr_Workshops')) {
        $event_registration_workshops_class = __DIR__ . '/../../classes/class-evtmgr-workshops.php';

        if (file_exists($event_registration_workshops_class)) {
            require_once $event_registration_workshops_class;
        }
    }

    /**
     * Step 5:
     * - Load all collected registration data for current cookie
     * - Save/update person in wp_evtmgr_persons
     * - Save/update selected workshops in wp_evtmgr_registrations_workshops
     * - Save/update billing data in wp_evtmgr_registrations_billing
     * - Send confirmation email
     */

    $debug_step5 = isset($_GET['debug_step5']) && $_GET['debug_step5'] === '1';

    $current_lang = isset($lang) && $lang !== ''
        ? sanitize_key($lang)
        : 'de';

    $registration_values = array();

    if (isset($registration_field_data) && is_object($registration_field_data)) {
        if (method_exists($registration_field_data, 'get_all_values_for_current_cookie')) {
            $registration_values = $registration_field_data->get_all_values_for_current_cookie($customer_cookie);
        } elseif (method_exists($registration_field_data, 'get_all_data_for_current_cookie')) {
            $registration_values = $registration_field_data->get_all_data_for_current_cookie($customer_cookie);
        }
    }

    if (!is_array($registration_values)) {
        $registration_values = array();
    }

    $person_id                    = false;
    $person_save_result           = array();
    $registration_workshops_saved = false;
    $registration_billing_saved   = false;
    $workshop_sync_result       = array();
    $workshop_sync_success      = false;
    $confirmation_email_sent      = false;
    $email_error                  = '';
    $error_messages               = array();

    if (empty($customer_cookie)) {
        $error_messages[] = 'Registrierungs-Cookie fehlt.';
    }

    if (empty($event_uid)) {
        $error_messages[] = 'Event-UID fehlt.';
    }

    if (empty($registration_values)) {
        $error_messages[] = 'Es wurden keine Registrierungsdaten gefunden.';
    }

    /**
     * 1. Save/update person.
     */
    if (empty($error_messages)) {
        $persons_obj = new class_evtmgr_persons();

        $person_save_result = $persons_obj->save_person_after_registration(
            $registration_values,
            $event_uid ?? '',
            $customer_cookie ?? '',
            $current_lang
        );

        if (is_array($person_save_result)) {
            $person_id = !empty($person_save_result['person_id'])
                ? absint($person_save_result['person_id'])
                : 0;

            if (empty($person_save_result['success']) || $person_id <= 0) {
                $error_messages[] = 'Person konnte nicht gespeichert werden: ' . ($person_save_result['message'] ?? 'Unbekannter Fehler.');

                if (!empty($person_save_result['error'])) {
                    $error_messages[] = $person_save_result['error'];
                }
            }
        } else {
            $person_id = absint($person_save_result);

            if ($person_id <= 0) {
                $error_messages[] = 'Person konnte nicht gespeichert werden.';
            }
        }
    }

    /**
     * 2. Main registration record is obsolete.
     *
     * The data previously stored by save_registration_after_person_save()
     * is now written directly to wp_evtmgr_persons in save_person_after_registration().
     */

    /**
     * 2. Save/update registration workshops.
     */
    if (empty($error_messages)) {
        $registrations_workshops_obj = new Evtmgr_Registrations_Workshops();

        $registration_workshops_saved = $registrations_workshops_obj->save_registration_workshops(
            $person_id,
            $event_uid ?? '',
            $customer_cookie ?? '',
            $registration_values
        );

        if (!$registration_workshops_saved) {
            $error_messages[] = 'Workshops konnten nicht gespeichert werden.';
        }
    }

    /**
     * 3. Save/update registration billing.
     */
    if (empty($error_messages)) {
        $registrations_billing_obj = new Evtmgr_Registrations_Billing();

        $registration_billing_saved = $registrations_billing_obj->save_registration_billing(
            $person_id,
            $event_uid ?? '',
            $customer_cookie ?? '',
            $registration_values,
            $current_lang
        );

        if (!$registration_billing_saved) {
            $error_messages[] = 'Rechnungsdaten konnten nicht gespeichert werden.';
        }
    }

    /**
     * 4. Sync workshop registration counters.
     */
    if (empty($error_messages)) {
        if (class_exists('Evtmgr_Workshops')) {
            $workshops_obj = new Evtmgr_Workshops();

            if (method_exists($workshops_obj, 'sync_registrations')) {
                $workshop_sync_result = $workshops_obj->sync_registrations($event_uid ?? '');
                $workshop_sync_success = !empty($workshop_sync_result['success']);

                if (!$workshop_sync_success) {
                    $sync_errors = !empty($workshop_sync_result['errors']) && is_array($workshop_sync_result['errors'])
                        ? implode(' ', $workshop_sync_result['errors'])
                        : 'Unbekannter Fehler.';

                    $error_messages[] = 'Workshop-Anmeldezahlen konnten nicht synchronisiert werden: ' . $sync_errors;
                }
            } else {
                $error_messages[] = 'sync_registrations() ist nicht verfügbar.';
            }
        } else {
            $error_messages[] = 'Evtmgr_Workshops ist nicht verfügbar.';
        }
    }

    /**
     * 4.5 Generate ticket PDF and attach to confirmation email.
     */
    $ticket_pdf_attachments = [];

    if (empty($error_messages) && $person_id > 0) {
        try {
            global $wpdb;

            $wpdb->query(
                "ALTER TABLE {$wpdb->prefix}evtmgr_persons
                 ADD COLUMN IF NOT EXISTS str_ticket_pdf VARCHAR(255) NOT NULL DEFAULT ''"
            );

            if (isset($persons_obj) && method_exists($persons_obj, 'person_update_ticket_pdf')) {
                $persons_obj->person_update_ticket_pdf($event_uid ?? '');
            }

            $ticket_person = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$wpdb->prefix}evtmgr_persons WHERE id = %d", $person_id),
                ARRAY_A
            );

            if (!empty($ticket_person)) {
                $pages_dir = get_stylesheet_directory() . '/db-custom/event-registration/pages/';

                require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-pdf-creation.php';

                $events_class_file = get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
                if (!class_exists('Evtmgr_Events') && file_exists($events_class_file)) {
                    require_once $events_class_file;
                }

                $pdf_creator = new Event_Registration_Pdf_Creation($pages_dir);
                $layout      = $pdf_creator->load_pdf_layout('dachverband-ticket.php');

                $ticket_event    = [];
                $str_event_name_ = '';
                $dtm_event_date  = '';

                if (class_exists('Evtmgr_Events')) {
                    $ev_obj          = new Evtmgr_Events();
                    $ticket_event    = (array) ($ev_obj->get_events_by_event_uid($event_uid ?? '', 'de') ?? []);
                    $str_event_name_ = $ticket_event['str_event_name'] ?? $ticket_event['str_event_name_de'] ?? '';
                    $dtm_event_date  = $pdf_creator->format_date((string) ($ticket_event['dtm_event_date'] ?? ''));
                }

                $person_lang = strtolower(trim($pdf_creator->value_ci($ticket_person, 'str_language', $current_lang)));
                if ($person_lang === '') {
                    $person_lang = $current_lang;
                }

                $file_name = $pdf_creator->file_name_from_person_field($ticket_person, 'str_ticket_pdf');

                if ($file_name !== '') {
                    $person_event_name     = $pdf_creator->event_text_by_language($ticket_event, 'str_event_name', $person_lang, $str_event_name_);
                    $person_event_subtitle = $pdf_creator->event_text_by_language($ticket_event, 'str_event_subtitle', $person_lang, '');

                    $image_replacements = $pdf_creator->get_image_replacements($layout);
                    $text_replacements  = $pdf_creator->text_replacements($layout, $person_lang);
                    $core_replacements  = $pdf_creator->person_replacements($ticket_person, [
                        '{str_language}'          => esc_attr($person_lang),
                        '{str_event_name}'        => esc_html($person_event_name),
                        '{str_event_subtitle}'    => esc_html($person_event_subtitle),
                        '{str_event_subtitle_de}' => esc_html($person_event_subtitle),
                        '{id}'                    => esc_html($pdf_creator->get_person_id($ticket_person)),
                        '{dtm_event_date}'        => esc_html($dtm_event_date),
                    ]);

                    $callback_replacements = [];
                    if (!empty($layout['per_person_callback']) && is_callable($layout['per_person_callback'])) {
                        $callback_replacements = (array) call_user_func(
                            $layout['per_person_callback'],
                            $ticket_person,
                            $ticket_event,
                            $person_lang
                        );
                    }

                    $all_replacements = array_merge($image_replacements, $text_replacements, $core_replacements, $callback_replacements);
                    $html             = $pdf_creator->render_html((string) $layout['html_template'], $all_replacements);
                    $html             = strtr($html, $all_replacements);

                    $docraptor = $pdf_creator->create_docraptor_client();

                    $doc = new DocRaptor\Doc();
                    $doc->setTest(true);
                    $doc->setDocumentType('pdf');
                    $doc->setName($file_name);
                    $doc->setDocumentContent($html);

                    $pdf_data = $docraptor->createDoc($doc);

                    $pdf_path  = $pdf_creator->get_pdf_path('tickets', $event_uid ?? '');
                    $pdf_creator->ensure_directory($pdf_path);
                    $full_path = rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name;
                    file_put_contents($full_path, $pdf_data);

                    $ticket_pdf_attachments = [$full_path];
                }
            }
        } catch (Throwable $e) {
            // PDF failure must not block the confirmation email.
        }
    }

    /**
     * 5. Send confirmation email.
     */

    if (empty($error_messages)) {
        $registration_mailer = null;

        if (isset($registration) && is_object($registration) && method_exists($registration, 'event_registration_send_email_after_registration')) {
            $registration_mailer = $registration;
        } elseif (class_exists('Event_Registration')) {
            $registration_mailer = new Event_Registration();
        }

        if ($registration_mailer && method_exists($registration_mailer, 'event_registration_send_email_after_registration')) {
            $confirmation_email_sent = $registration_mailer->event_registration_send_email_after_registration(
                $registration_values,
                $event_uid ?? '',
                $customer_cookie ?? '',
                $current_lang,
                $ticket_pdf_attachments
            );

            if (!$confirmation_email_sent) {
                $email_error = $GLOBALS['event_registration_last_email_error'] ?? '';

                if ($email_error !== '') {
                    $error_messages[] = 'Bestätigungs-E-Mail konnte nicht gesendet werden: ' . $email_error;
                } else {
                    $error_messages[] = 'Bestätigungs-E-Mail konnte nicht gesendet werden.';
                }
            } elseif ($person_id && method_exists($persons_obj ?? null, 'update_email_sent')) {
                $persons_obj->update_email_sent(
                    $person_id,
                    $GLOBALS['event_registration_last_email_message'] ?? ''
                );
            }
        } else {
            $error_messages[] = 'E-Mail-Methode ist nicht verfügbar.';
        }
    }

    if (
        empty($error_messages)
        && $person_id
        && $registration_workshops_saved
        && $registration_billing_saved
        && $confirmation_email_sent
        && isset($registration)
        && is_object($registration)
        && method_exists($registration, 'clear_registration_cookies')
    ) {
        $registration->clear_registration_cookies();
        $customer_cookie = '';
    }

?>

<div class="container my-4 registration-step-5">

    <h2><?php echo $wordings['ihre_registrierung_ist_abgeschlossen'] ?? 'ihre_registrierung_ist_abgeschlossen'; ?></h2>

    <?php if ($debug_step5) : ?>
        <pre style="background:#111;color:#0f0;padding:12px;white-space:pre-wrap;overflow:auto;">
    STEP 5 DEBUG

    customer_cookie:
    <?php echo esc_html($customer_cookie ?? ''); ?>

    event_uid:
    <?php echo esc_html($event_uid ?? ''); ?>

    lang:
    <?php echo esc_html($lang ?? ''); ?>

    registration_values:
    <?php print_r($registration_values); ?>

    person_save_result:
    <?php print_r($person_save_result); ?>

    person_id:
    <?php print_r($person_id); ?>

    registration_workshops_saved:
    <?php print_r($registration_workshops_saved); ?>

    registration_billing_saved:
    <?php print_r($registration_billing_saved); ?>

    workshop_sync_result:
    <?php print_r($workshop_sync_result); ?>

    workshop_sync_success:
    <?php print_r($workshop_sync_success); ?>

    confirmation_email_sent:
    <?php print_r($confirmation_email_sent); ?>

    email_error:
    <?php echo esc_html($email_error ?: ($GLOBALS['event_registration_last_email_error'] ?? '')); ?>

    last_query:
    <?php echo esc_html($GLOBALS['wpdb']->last_query ?? ''); ?>

    last_error:
    <?php echo esc_html($GLOBALS['wpdb']->last_error ?? ''); ?>

    error_messages:
    <?php print_r($error_messages); ?>

        </pre>
    <?php endif; ?>

    <?php if (
        empty($error_messages)
        && $person_id
        && $registration_workshops_saved
        && $registration_billing_saved
        && $workshop_sync_success
        && $confirmation_email_sent
    ) : ?>

        <div class="alert alert-success">
            <?php echo $wordings['ihre_registrierung_wurde_erfolgreich_gespeichert'] ?? 'ihre_registrierung_wurde_erfolgreich_gespeichert'; ?>
            <?php echo $wordings['eine_bestaetigungs_e_mail_wurde_an_die_angegebene_e_mail_adresse_versendet'] ?? 'eine_bestaetigungs_e_mail_wurde_an_die_angegebene_e_mail_adresse_versendet'; ?>
        </div>

        <?php if ($debug_step5) : ?>
            <p>
                Person id: <?php echo esc_html($person_id); ?><br>
                Workshops saved: <?php echo esc_html($registration_workshops_saved ? 'yes' : 'no'); ?><br>
                Billing id: <?php echo esc_html($registration_billing_saved); ?><br>
                Workshop sync: <?php echo esc_html($workshop_sync_success ? 'yes' : 'no'); ?><br>
                Confirmation email sent: <?php echo esc_html($confirmation_email_sent ? 'yes' : 'no'); ?>
            </p>
        <?php endif; ?>

    <?php else : ?>

        <div class="alert alert-danger">
            <?php echo $wordings['die_registrierung_konnte_nicht_vollstaendig_abgeschlossen_werden'] ?? 'die_registrierung_konnte_nicht_vollstaendig_abgeschlossen_werden'; ?>
        </div>

        <?php if (!empty($error_messages)) : ?>
            <ul>
                <?php foreach ($error_messages as $message) : ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($GLOBALS['wpdb']->last_error)) : ?>
            <div class="alert alert-warning">
                Datenbankmeldung:
                <?php echo esc_html($GLOBALS['wpdb']->last_error); ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="mt-4">
        <button type="submit"
                name="registration_action"
                value="new_registration"
                class="btn btn-secondary"
                formnovalidate>
            <?php echo $wordings['weitere_anmeldung_taetigen'] ?? 'weitere_anmeldung_taetigen'; ?>
        </button>
    </div>

</div>
