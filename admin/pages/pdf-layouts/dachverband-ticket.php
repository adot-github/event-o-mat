<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ticket layout config: Eintrittskarte / Ticket Dachverband
 */
return array(
    'name'                => 'Ticket Dachverband',
    'default_file_suffix' => 'ticket',
    'asset_dir'           => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets',

    'images' => array(
        '{logo_data_uri}' => 'logo.png',
    ),

    /*
     * Language-specific wordings.
     */
    'texts' => array(
        'de' => array(
            'ticket_label'    => 'Ihr Ticket',
            'ticket_fuer'     => 'Ticket für',
            'anmeldung_am'    => 'Ihre Anmeldung',
            'angebote_titel'  => 'Ihre gebuchten Angebote',
            'gruss'           => 'Wir freuen uns, Sie am {dtm_event_date} in Bern begrüssen zu dürfen.',
            'bitte_drucken'   => 'Bitte drucken Sie das Ticket aus und weisen Sie dieses an der Fachtagung beim Check-In vor.',
        ),
        'fr' => array(
            'ticket_label'    => 'Votre ticket',
            'ticket_fuer'     => 'Ticket pour',
            'anmeldung_am'    => 'Votre inscription',
            'angebote_titel'  => 'Vos offres réservées',
            'gruss'           => 'Nous nous réjouissons de vous accueillir à Berne le {dtm_event_date}.',
            'bitte_drucken'   => 'Veuillez imprimer ce ticket et le présenter lors du check-in au colloque.',
        ),
        'it' => array(
            'ticket_label'    => 'Il suo ticket',
            'ticket_fuer'     => 'Ticket per',
            'anmeldung_am'    => 'La sua iscrizione',
            'angebote_titel'  => 'Le sue offerte prenotate',
            'gruss'           => 'Ci rallegriamo di accoglierla a Berna il {dtm_event_date}.',
            'bitte_drucken'   => 'Si prega di stampare il ticket e presentarlo al check-in del convegno.',
        ),
    ),

    /*
     * Per-person dynamic replacements:
     * - language-correct event name/subtitle
     * - formatted registration date
     * - QR code image URL
     * - booked workshops HTML block
     */
    'per_person_callback' => static function (array $person, array $event = [], string $person_lang = ''): array {
        global $wpdb;

        $p    = array_change_key_case($person, CASE_LOWER);
        $lang = $person_lang !== '' ? $person_lang : strtolower(trim($p['str_language'] ?? 'de'));
        if ($lang === '') {
            $lang = 'de';
        }

        $event_lc = array_change_key_case($event, CASE_LOWER);

        /* ---- language-correct event name ---- */
        $str_event_name = '';
        foreach (['str_event_name_' . $lang, 'str_event_name_de', 'str_event_name'] as $key) {
            $val = trim($event_lc[$key] ?? '');
            if ($val !== '') {
                $str_event_name = $val;
                break;
            }
        }

        /* ---- language-correct event subtitle ---- */
        $str_event_subtitle = '';
        foreach (['str_event_subtitle_' . $lang, 'str_event_subtitle_de', 'str_event_subtitle'] as $key) {
            $val = trim($event_lc[$key] ?? '');
            if ($val !== '') {
                $str_event_subtitle = $val;
                break;
            }
        }

        /* ---- formatted registration date ---- */
        $days_de = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $raw_date = $p['dtm_date_created'] ?? '';
        $ts = $raw_date !== '' ? strtotime($raw_date) : 0;
        if ($ts > 0) {
            $weekday              = $days_de[(int) date('w', $ts)];
            $dtm_date_created_fmt = $weekday
                . ', ' . (int) date('j', $ts) . '.' . (int) date('n', $ts) . '.' . date('Y', $ts)
                . ', ' . date('H', $ts) . '.' . date('i', $ts) . ' Uhr';
        } else {
            $dtm_date_created_fmt = $raw_date;
        }

        /* ---- QR code image URL ---- */
        $cookie    = $p['str_registration_cookie'] ?? '';
        $check_url = get_site_url() . '?checking=' . rawurlencode($cookie);
        $qr_src    = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . rawurlencode($check_url);

        /* ---- booked workshops ---- */
        $person_id      = absint($p['id'] ?? $p['fky_person_id'] ?? 0);
        $event_uid      = $p['fky_event_uid'] ?? '';
        $workshops_html = '';

        /* helper: convert decimal time like "12.30" → "12.30", "9.00" → "9.00" */
        $fmt_time = static function (string $val): string {
            if (trim($val) === '') {
                return '';
            }
            $parts = explode('.', trim($val), 2);
            $hours = (int) $parts[0];
            $mins  = str_pad(substr($parts[1] ?? '00', 0, 2), 2, '0', STR_PAD_RIGHT);
            return $hours . '.' . $mins;
        };

        if ($person_id > 0 && $event_uid !== '') {
            $ws_rows = (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                         ws.str_workshop_number,
                         ws.str_workshop_title_de,
                         ws.str_workshop_subtitle_de,
                         tz.dtm_time_from,
                         tz.dtm_time_to
                       FROM {$wpdb->prefix}evtmgr_registrations_workshops AS rw
                      INNER JOIN {$wpdb->prefix}evtmgr_workshops AS ws
                              ON ws.id = rw.fky_workshop_id
                      INNER JOIN {$wpdb->prefix}evtmgr_timezones AS tz
                              ON tz.id = ws.fky_timezone_id
                      WHERE rw.fky_person_id = %d
                        AND ws.fky_event_uid  = %s
                        AND ws.ysn_no_registration_possible = 0
                      ORDER BY tz.int_sort_order, ws.str_workshop_number",
                    $person_id,
                    $event_uid
                ),
                ARRAY_A
            );

            foreach ($ws_rows as $ws) {
                $ws          = array_change_key_case((array) $ws, CASE_LOWER);
                $number      = esc_html(trim($ws['str_workshop_number'] ?? ''));
                $title       = esc_html(trim($ws['str_workshop_title_de'] ?? ''));
                $subtitle    = esc_html(trim($ws['str_workshop_subtitle_de'] ?? ''));
                $time_from   = $fmt_time((string) ($ws['dtm_time_from'] ?? ''));
                $time_to     = $fmt_time((string) ($ws['dtm_time_to'] ?? ''));
                $time_range  = $time_from !== '' ? $time_from . '–' . $time_to : '';

                $line1 = trim($number . ' ' . $title);
                $line2 = trim(($subtitle !== '' ? $subtitle . ' ' : '') . ($time_range !== '' ? $time_range : ''));

                $workshops_html .= '<div class="ws-row">';
                if ($line1 !== '') {
                    $workshops_html .= '<div class="ws-title">' . $line1 . '</div>';
                }
                if ($line2 !== '') {
                    $workshops_html .= '<div class="ws-detail">' . $line2 . '</div>';
                }
                $workshops_html .= '</div>';
            }
        }

        if ($workshops_html === '') {
            $workshops_html = '<p style="color:#888;font-style:italic;">—</p>';
        }

        return [
            '{str_event_name}'        => esc_html($str_event_name),
            '{str_event_subtitle}' => esc_html($str_event_subtitle),
            '{dtm_date_created_fmt}'  => esc_html($dtm_date_created_fmt),
            '{qr_img_src}'            => esc_url($qr_src),
            '{workshops_html}'        => $workshops_html,
        ];
    },

    'html_template' => <<<'HTML'
<!doctype html>
<html lang="{str_language}">
<head>
  <meta charset="utf-8">
  <title>{ticket_label}</title>
  <style>
    @page {
      size: A4;
      margin: 0;
    }

    html, body {
      margin: 0;
      padding: 0;
      width: 210mm;
      height: 297mm;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11pt;
      color: #0f4f79;
      background: #ffffff;
    }

    .page {
      position: relative;
      width: 210mm;
      height: 297mm;
      overflow: hidden;
      background: #ffffff;
    }

    .logo {
      position: absolute;
      top: 10mm;
      left: 20mm;
      width: 65mm;
      z-index: 3;
    }

    .logo img {
      display: block;
      width: 100%;
      height: auto;
    }

    .address {
      position: absolute;
      left: 20mm;
      top: 25mm;
      z-index: 3;
      font-size: 10pt;
      line-height: 1.2;
      color: #315d7d;
    }

    .content {
      position: absolute;
      top: 55mm;
      left: 20mm;
      right: 20mm;
      z-index: 2;
    }

    .ticket-heading {
      font-size: 22pt;
      font-weight: bold;
      color: #0f4f79;
      line-height: 1.1;
      margin-bottom: 5mm;
    }

    .qr {
      margin-bottom: 8mm;
    }

    .qr img {
      width: 32mm;
      height: auto;
      display: block;
    }

    .event-name {
      font-size: 16pt;
      font-weight: bold;
      margin: 0 0 1.5mm 0;
      line-height: 1.2;
    }

    .event-subtitle {
      font-size: 11pt;
      margin: 0 0 5mm 0;
      color: #315d7d;
      line-height: 1.3;
    }

    hr {
      border: none;
      border-top: 0.5pt solid #0f4f79;
      margin: 4mm 0;
    }

    .ticket-person {
      font-size: 12pt;
      margin: 3mm 0 1.5mm 0;
    }

    .ticket-date {
      font-size: 10pt;
      color: #315d7d;
      margin: 0 0 3mm 0;
    }

    .section-title {
      font-size: 12pt;
      font-weight: bold;
      margin: 4mm 0 2mm 0;
    }

    .ws-row {
      margin: 0 0 2mm 0;
    }

    .ws-title {
      font-size: 10pt;
      font-weight: bold;
      line-height: 1.3;
    }

    .ws-detail {
      font-size: 9pt;
      color: #315d7d;
      line-height: 1.3;
    }

    .print-hint {
      font-size: 13pt;
      line-height: 1.4;
      margin: 2mm 0 5mm 0;
    }

    .footer-text {
      margin-top: 6mm;
      font-size: 10pt;
      line-height: 1.4;
    }
  </style>
</head>
<body>
<div class="page">

  <div class="logo">
    <img src="{logo_data_uri}" alt="Logo">
  </div>

  <div class="address">
    Effingerstrasse 21 | 3011 Bern<br>
    www.lesen-schreiben-schweiz.ch
  </div>

  <div class="content">

    <div class="ticket-heading">{ticket_label}</div>
    <div class="print-hint">{bitte_drucken}</div>

    <div class="qr">
      <img src="{qr_img_src}" alt="QR-Code">
    </div>

    <div class="event-name">{str_event_name}</div>
    <div class="event-subtitle">{str_event_subtitle}</div>

  <hr>

  <div class="ticket-person"><strong>{ticket_fuer}:</strong> {str_first_name} {str_last_name}, {str_institution}</div>
  <div class="ticket-date"><strong>{anmeldung_am}:</strong> {dtm_date_created_fmt}</div>

  <hr>

  <div class="section-title">{angebote_titel}</div>
  {workshops_html}

  <hr>

    <div class="footer-text">{gruss}</div>

  </div><!-- .content -->
</div><!-- .page -->
</body>
</html>
HTML,
);
