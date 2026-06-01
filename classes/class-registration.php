<?php

if (!defined('ABSPATH')) {
    exit;
}

class Event_Registration {
    const COOKIE_NAME      = 'registration_user_cookie';
    const STEP_COOKIE_NAME = 'event_registration_current_step';
    const NONCE_ACTION     = 'event_registration_action';
    const NONCE_NAME       = 'event_registration_nonce';
    const MAX_STEP         = 5;

    /**
     * @var wpdb
     */
    protected $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Ensure that the registration UUid cookie exists.
     *
     * This follows the behaviour of the old app: a successful setcookie() means
     * WordPress/PHP accepted the cookie header. A real browser-side check would
     * require the next request after the browser has stored the cookie.
     */
    public function ensure_registration_cookie() {
        $existing_cookie = $this->get_registration_cookie();

        if ($existing_cookie !== '') {
            return array(
                'success' => true,
                'message' => 'Für die Anmedung muss es erlaubt sein, dass wir ein Cookie mit einer Identifikations-Nummer setzen können. Ihr System erlaubt das und ist kompatibel für die Anmeldung.',
                'cookie'  => $existing_cookie,
            );
        }

        /*
         * Important: do not create a new UUid during step changes when the
         * browser has not sent the cookie back yet. The hidden form field keeps
         * the original UUid stable across POST requests.
         */
        $posted_cookie = $this->get_posted_registration_cookie();

        if ($posted_cookie !== '') {
            $this->set_registration_cookie($posted_cookie);

            return array(
                'success' => true,
                'message' => 'Für die Anmedung muss es erlaubt sein, dass wir ein Cookie mit einer Identifikations-Nummer setzen können. Ihr System erlaubt das und ist kompatibel für die Anmeldung.',
                'cookie'  => $posted_cookie,
            );
        }

        $uuid = wp_generate_uuid4();

        $set_result = $this->set_registration_cookie($uuid);

        if ($set_result) {
            return array(
                'success' => true,
                'message' => 'Für die Anmedung muss es erlaubt sein, dass wir ein Cookie mit einer Identifikations-Nummer setzen können. Ihr System erlaubt das und ist kompatibel für die Anmeldung.',
                'cookie'  => $uuid,
            );
        }

        return array(
            'success' => false,
            'message' => 'Ihr System ist nicht kompatibel für die Anmeldung. Sie müssen erlauben, dass wir ein Cookie mit einer Identifikations-Nummer setzen können.',
            'cookie'  => '',
        );
    }

    public function get_registration_cookie() {
        return !empty($_COOKIE[self::COOKIE_NAME])
            ? $this->sanitize_customer_cookie(wp_unslash($_COOKIE[self::COOKIE_NAME]))
            : '';
    }

    public function get_posted_registration_cookie() {
        if (empty($_POST['str_customer_cookie'])) {
            return '';
        }

        return $this->sanitize_customer_cookie(wp_unslash($_POST['str_customer_cookie']));
    }

    protected function set_registration_cookie($cookie_value) {
        $cookie_value = $this->sanitize_customer_cookie($cookie_value);

        if ($cookie_value === '') {
            return false;
        }

        $set_result = setcookie(
            self::COOKIE_NAME,
            $cookie_value,
            time() + (DAY_IN_SECONDS * 30),
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        $_COOKIE[self::COOKIE_NAME] = $cookie_value;

        return $set_result;
    }

    protected function sanitize_customer_cookie($cookie_value) {
        $cookie_value = sanitize_text_field((string) $cookie_value);

        return preg_match('/^[a-zA-Z0-9\-]{8,100}$/', $cookie_value)
            ? $cookie_value
            : '';
    }

    public function get_current_step() {
        if (!empty($_POST['current_step'])) {
            return max(1, min(self::MAX_STEP, (int) $_POST['current_step']));
        }

        if (!empty($_COOKIE[self::STEP_COOKIE_NAME])) {
            return max(1, min(self::MAX_STEP, (int) $_COOKIE[self::STEP_COOKIE_NAME]));
        }

        return 1;
    }

    public function persist_current_step($step) {
        $step = max(1, min(self::MAX_STEP, (int) $step));

        setcookie(
            self::STEP_COOKIE_NAME,
            (string) $step,
            time() + (DAY_IN_SECONDS * 30),
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            false
        );

        $_COOKIE[self::STEP_COOKIE_NAME] = (string) $step;
    }

    public function clear_registration_cookies() {
        $cookie_path   = COOKIEPATH ? COOKIEPATH : '/';
        $cookie_domain = COOKIE_DOMAIN;

        setcookie(
            self::COOKIE_NAME,
            '',
            time() - YEAR_IN_SECONDS,
            $cookie_path,
            $cookie_domain,
            is_ssl(),
            true
        );

        setcookie(
            self::STEP_COOKIE_NAME,
            '',
            time() - YEAR_IN_SECONDS,
            $cookie_path,
            $cookie_domain,
            is_ssl(),
            false
        );

        unset($_COOKIE[self::COOKIE_NAME], $_COOKIE[self::STEP_COOKIE_NAME]);

        return true;
    }

    public function verify_nonce() {
        if (empty($_POST[self::NONCE_NAME])) {
            return false;
        }

        return (bool) wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])),
            self::NONCE_ACTION
        );
    }

    public function get_post_action() {
        if (!empty($_POST['registration_action'])) {
            $action = sanitize_key(wp_unslash($_POST['registration_action']));

            if (in_array($action, array('prev', 'next', 'finish'), true)) {
                return $action;
            }
        }

        if (!empty($_POST['go_prev'])) {
            return 'prev';
        }

        if (!empty($_POST['go_next'])) {
            return 'next';
        }

        if (!empty($_POST['go_finish'])) {
            return 'finish';
        }

        return '';
    }

    public function collect_posted_values() {
        $values = array();

        $skip_keys = array(
            'current_step',
            'registration_step',
            'registration_action',
            self::NONCE_NAME,
            '_wp_http_referer',
            'str_customer_cookie',
            'go_prev',
            'go_next',
            'go_finish',
        );

        foreach ($_POST as $key => $value) {
            $key = sanitize_key($key);

            if ($key === '' || in_array($key, $skip_keys, true)) {
                continue;
            }

            $values[$key] = is_array($value)
                ? array_map('sanitize_text_field', wp_unslash($value))
                : sanitize_text_field(wp_unslash($value));
        }

        return $values;
    }

    public function get_value($values, $key, $default = '') {
        return isset($values[$key]) ? $values[$key] : $default;
    }

    public function event_registration_send_email_after_registration($registration_values, $event_uid, $customer_cookie, $lang = 'de') {
        $event_uid       = sanitize_text_field((string) $event_uid);
        $customer_cookie = sanitize_text_field((string) $customer_cookie);
        $lang            = sanitize_key((string) $lang);

        $GLOBALS['event_registration_last_email_error'] = '';
        $GLOBALS['event_registration_last_email_message'] = '';
        $GLOBALS['event_registration_last_email_subject'] = '';

        if (!is_array($registration_values)) {
            $registration_values = array();
        }

        $get_value = function($field_name, $default = '') use ($registration_values) {
            $field_name_lower = strtolower($field_name);

            foreach ($registration_values as $key => $value) {
                if (strtolower((string) $key) === $field_name_lower) {
                    return is_array($value) ? $default : (string) $value;
                }
            }

            return $default;
        };

        $to_email = sanitize_email(trim($get_value('str_email')));

        if ($to_email === '') {
            $GLOBALS['event_registration_last_email_error'] = 'Keine gültige Empfänger-E-Mail gefunden.';
            return false;
        }

        if (!class_exists('Evtmgr_Wordings')) {
            $GLOBALS['event_registration_last_email_error'] = 'Klasse Evtmgr_Wordings ist nicht geladen.';
            return false;
        }

        if (!class_exists('Evtmgr_Events')) {
            $GLOBALS['event_registration_last_email_error'] = 'Klasse Evtmgr_Events ist nicht geladen.';
            return false;
        }

        /*
        /* TODO
        $wordings_obj = new Evtmgr_Wordings();
        $wordings = $wordings_obj->get_wordings($lang, $event_uid);

        if (!is_array($wordings)) {
            $wordings = array();
        }
        */

        if (!isset($wordings) || !is_array($wordings)) {
            $wordings = array();
        }

        $event_obj = new Evtmgr_Events();
        $qry_events  = $event_obj->get_events_by_event_uid($event_uid, $lang);

        if (empty($qry_events) || !is_array($qry_events)) {
            $GLOBALS['event_registration_last_email_error'] = 'Kongressdaten konnten nicht geladen werden.';
            return false;
        }

        $event_name = $qry_events['str_event_name']
            ?? $qry_events['str_event_name_' . $lang]
            ?? $qry_events['str_event_name_de']
            ?? '';

        $event_name = trim(wp_strip_all_tags((string) $event_name));

        if ($event_name === '') {
            $event_name = get_bloginfo('name');
        }

        $email_from_raw = $qry_events['str_event_email_from'] ?? '';
        $email_bcc_raw  = $qry_events['str_event_email_bcc'] ?? '';

        $email_from = $this->extract_email_address($email_from_raw);

        if ($email_from === '') {
            $email_from = sanitize_email(get_option('admin_email'));
        }

        $email_bcc_list = $this->sanitize_email_list($email_bcc_raw);

        $first_name = $get_value('str_first_name');
        $last_name  = $get_value('str_last_name');

        $show_price_data = $get_value('person_billling_data');
        $show_user_data  = $this->event_registration_build_user_data_table($registration_values);

        $css = '
            <style type="text/css">
                body {padding:0;margin:0}
                h1,h2,h3,h4,h5 {font-family:Arial,Helvetica;font-weight:bold;color:#000000 !important;line-height:110%;margin:0;margin-top:10px;}
                h1 {font-size:22px}
                h2 {font-size:19px}
                h3 {font-size:16px}
                table {border-collapse:collapse;border:none}
                table td {border:none;}
                p, ul, ol, li, td {font-family:Helvetica;font-size:14px;line-height:110%;vertical-align:top;color:#000000 !important;}
                p .fhnw-footer {font-size:8pt !important}
                #topTitle {font-size:12pt;font-weight:bold;font-family:Arial;color:#000000 !important;margin:0;padding:0}
                #subTitle {font-size:12pt;font-weight:normal;font-family:Arial;color:#000000 !important;margin:0;padding:0}
                .left10 {padding-left:10px !important}
                .left20 {padding-left:30px !important}
                .left30 {padding-left:30px !important}
                .left40 {padding-left:40px !important}
                .left50 {padding-left:50px !important}
                .data-table td {padding:4px 8px;border-bottom:1px solid #ddd;}
                .data-table td:first-child {font-weight:bold;width:180px;}
            </style>
        ';

        $greeting_text = $wordings['t74GutenTag'] ?? 'Guten Tag';
        $notice_text   = $wordings['t76BitteBeachtenSieIhreAnmeldungIstVerbindlichAbmeldungen'] ?? '';
        $subject_text  = $wordings['t79IhreAnmeldung'] ?? 'Ihre Anmeldung';

        $show_email_text = '
            <p>' . esc_html($greeting_text) . ' ' . esc_html(trim($first_name . ' ' . $last_name)) . '<br><br>'
            . wp_kses_post($notice_text) .
            '<br><br></p>
        ';

        $subject = trim($event_name . ' – ' . wp_strip_all_tags((string) $subject_text));

        $message = '
            <!doctype html>
            <html>
            <head>
                <meta charset="UTF-8">
                ' . $css . '
            </head>
            <body>
                <table width="600" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <h1>' . esc_html($event_name) . '</h1>
                            <br>
                            ' . $show_email_text . '
                            <br>
                            ' . wp_kses_post($show_price_data) . '
                            <br>
                            ' . wp_kses_post($show_user_data) . '
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        if ($email_from !== '') {
            $headers[] = 'From: ' . $this->format_email_header_name($event_name) . ' <' . $email_from . '>';
        }

        if (!empty($email_bcc_list)) {
            $headers[] = 'Bcc: ' . implode(', ', $email_bcc_list);
        }

        $GLOBALS['event_registration_last_email_message'] = $message;
        $GLOBALS['event_registration_last_email_subject'] = $subject;

        $mail_error = '';

        $mail_failed_callback = function($wp_error) use (&$mail_error) {
            if (is_wp_error($wp_error)) {
                $mail_error = $wp_error->get_error_message();

                $error_data = $wp_error->get_error_data();

                if (!empty($error_data) && is_string($error_data)) {
                    $mail_error .= ' ' . $error_data;
                }
            }
        };

        add_action('wp_mail_failed', $mail_failed_callback, 10, 1);

        $sent = wp_mail($to_email, $subject, $message, $headers);

        remove_action('wp_mail_failed', $mail_failed_callback, 10);

        if (!$sent) {
            if ($mail_error === '') {
                $mail_error = 'wp_mail() returned false. Prüfen Sie die SMTP-/Mail-Konfiguration von WordPress bzw. der lokalen Entwicklungsumgebung.';
            }

            $GLOBALS['event_registration_last_email_error'] = $mail_error;
            return false;
        }

        $GLOBALS['event_registration_last_email_error'] = '';
        return true;
    }

    protected function extract_email_address($email_value) {
        $email_value = trim((string) $email_value);

        if ($email_value === '') {
            return '';
        }

        if (preg_match('/<([^>]+)>/', $email_value, $matches)) {
            $email_value = $matches[1];
        }

        return sanitize_email($email_value);
    }

    protected function sanitize_email_list($email_list) {
        $email_list = (string) $email_list;

        if ($email_list === '') {
            return array();
        }

        $parts = preg_split('/[,;]+/', $email_list);
        $emails = array();

        foreach ($parts as $part) {
            $email = $this->extract_email_address($part);

            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    protected function format_email_header_name($name) {
        $name = wp_strip_all_tags((string) $name);
        $name = trim(str_replace(array("\r", "\n"), '', $name));

        return $name !== '' ? $name : get_bloginfo('name');
    }

    public function event_registration_build_user_data_table($registration_values) {
        if (!is_array($registration_values)) {
            return '';
        }

        $get_value = function($field_name, $default = '') use ($registration_values) {
            $field_name_lower = strtolower($field_name);

            foreach ($registration_values as $key => $value) {
                if (strtolower((string) $key) === $field_name_lower) {
                    return is_array($value) ? $default : (string) $value;
                }
            }

            return $default;
        };

        $rows = array(
            'Anrede'                  => $get_value('str_salutation'),
            'Akad. Titel'             => $get_value('str_academic_title'),
            'Vorname'                 => $get_value('str_first_name'),
            'Nachname'                => $get_value('str_last_name'),
            'Adresse'                 => $get_value('str_address'),
            'PLZ'                     => $get_value('str_zip'),
            'Ort'                     => $get_value('str_city'),
            'Land'                    => $get_value('str_country'),
            'Berufliche Funktion'     => $get_value('str_job_title'),
            'E-Mail'                  => $get_value('str_email'),
            'Telefon'                 => $get_value('str_phone'),
            'Organisation'            => $get_value('str_institution'),
            'Abteilung/Institut'      => $get_value('str_institution_Division'),
            'Organisation Adresse'    => $get_value('str_institution_Address'),
            'Organisation PLZ'        => $get_value('str_institution_Zip'),
            'Organisation Ort'        => $get_value('str_institution_City'),
        );

        $html = '<h2>Ihre Angaben</h2>';
        $html .= '<table class="data-table" width="100%" cellpadding="0" cellspacing="0">';

        foreach ($rows as $label => $value) {
            if (trim((string) $value) === '') {
                continue;
            }

            $html .= '<tr>';
            $html .= '<td>' . esc_html($label) . '</td>';
            $html .= '<td>' . esc_html($value) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }


}
