<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Diploma layout config:
 * Teilnahmebestätigung Dachverband
 */
return array(
    'name'                => 'Teilnahmebestätigung Dachverband',
    'default_file_suffix' => 'teilnahmebestaetigung',
    'asset_dir'           => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets',

    /*
     * Placeholders in the HTML template => image file in asset_dir.
     */
    'images' => array(
        '{logo_data_uri}'      => 'logo.png',
        '{bg_data_uri}'        => 'bg.png',
        '{signature_data_uri}' => 'signature.png',
    ),

    /*
     * Per-person dynamic replacements: language-correct event name + subtitle.
     * Overrides the core replacements resolved by pdf-creation.php.
     */
    'per_person_callback' => static function(array $person, array $event = [], string $person_lang = ''): array {
        $p    = array_change_key_case($person, CASE_LOWER);
        $lang = $person_lang !== '' ? $person_lang : strtolower(trim($p['str_language'] ?? 'de'));
        if ($lang === '') {
            $lang = 'de';
        }

        $event_lc = array_change_key_case($event, CASE_LOWER);

        $str_event_name = '';
        foreach (['str_event_name_' . $lang, 'str_event_name_de', 'str_event_name'] as $key) {
            $val = trim($event_lc[$key] ?? '');
            if ($val !== '') { $str_event_name = $val; break; }
        }

        $str_event_subtitle = '';
        foreach (['str_event_subtitle_' . $lang, 'str_event_subtitle_de', 'str_event_subtitle'] as $key) {
            $val = trim($event_lc[$key] ?? '');
            if ($val !== '') { $str_event_subtitle = $val; break; }
        }

        return [
            '{str_event_name}'     => esc_html($str_event_name),
            '{str_event_subtitle}' => esc_html($str_event_subtitle),
        ];
    },

    /*
     * Language-specific content placeholders.
     * These values may contain HTML and may also contain other placeholders
     * such as {str_first_name}, {str_last_name}, {dtm_event_date}.
     */
    'texts' => array(
        'de' => array(
            'teilnamebestaetigung' => 'TEILNAHME-<br> BESTÄTIGUNG',
            'confirmation_text'    => 'Der Schweizer Dachverband<br>
Lesen und Schreiben bestätigt, dass<br><br>
<span class="participant-name">{str_first_name} {str_last_name}</span><br><br>
am {dtm_event_date} an der nationalen<br>
Fachtagung Grundkompetenzen<br>
teilgenommen hat.',
            'geschaeftsfuehrer'    => 'Geschäftsführer',
        ),
        'fr' => array(
            'teilnamebestaetigung' => 'CONFIRMATION<br>DE PARTICIPATION',
            'confirmation_text'    => 'L\'association faîtière suisse<br>
Lecture et écriture confirme que<br><br>
<span class="participant-name">{str_first_name} {str_last_name}</span><br><br>
a participé, le {dtm_event_date}, au<br>
colloque national sur les compétences de base.',
            'geschaeftsfuehrer'    => 'Directeur général',
        ),
        'it' => array(
            'teilnamebestaetigung' => 'CONFERMA DI PARTECIPAZIONE',
            'confirmation_text'    => 'L\'associazione mantello svizzera<br>
Leggere e scrivere conferma che<br><br>
<span class="participant-name">{str_first_name} {str_last_name}</span><br><br>
ha partecipato il {dtm_event_date} al convegno nazionale<br>
sulle competenze di base.',
            'geschaeftsfuehrer'    => 'Amministratore delegato',
        ),
    ),

    'html_template' => <<<'HTML'
<!doctype html>
<html lang="{str_language}">
<head>
  <meta charset="utf-8">
  <title>Teilnahme-Bestätigung</title>
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
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
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

    .bg-mark {
      position: absolute;
      top: 82mm;
      left: 50%;
      transform: translateX(-50%);
      width: 130mm;
      opacity: 1;
      z-index: 1;
    }

    .bg-mark img {
      display: block;
      width: 100%;
      height: auto;
    }

    .content {
      position: absolute;
      top: 42mm;
      left: 0;
      width: 100%;
      text-align: center;
      z-index: 2;
      padding: 0 22mm;
      box-sizing: border-box;
    }

    .main-title {
      margin: 0 0 20mm 0;
      font-size: 10mm;
      line-height: 1.1;
      font-weight: 700;
      letter-spacing: 1.8mm;
      text-transform: uppercase;
    }

    .event-title {
      margin: 0 0 12mm 0;
      font-size: 8.2mm;
      line-height: 1.25;
    }

    .event-subtitle {
      margin: 0 0 38mm 0;
      font-size: 6.8mm;
      line-height: 1.35;
    }

    .body-text {
      margin: 0 auto 23mm auto;
      max-width: 105mm;
      font-size: 4.5mm;
      line-height: 1.35;
    }

    .participant-name {
      display: inline-block;
      min-width: 70mm;
      border-bottom: 0.4mm solid rgba(15, 79, 121, 0.25);
      padding-bottom: 1mm;
      margin: 0 1mm;
      font-weight: 700;
    }

    .footer-left {
      position: absolute;
      left: 20mm;
      bottom: 33.5mm;
      z-index: 3;
      font-size: 4mm;
      line-height: 1.3;
    }

    .signature-block {
      position: absolute;
      right: 26mm;
      bottom: 29mm;
      width: 44mm;
      text-align: left;
      z-index: 3;
      color: #315d7d;
    }

    .signature-block img {
      display: block;
      width: 36mm;
      height: auto;
      margin: 6mm 0 0 0;
    }

    .signature-name {
      font-size: 4mm;
      line-height: 1.15;
    }

    .address {
      position: absolute;
      left: 20mm;
      bottom: 12mm;
      z-index: 3;
      font-size: 3.3mm;
      line-height: 1.35;
      color: #315d7d;
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="logo">
      <img src="{logo_data_uri}" alt="Logo">
    </div>

    <div class="bg-mark">
      <img src="{bg_data_uri}" alt="">
    </div>

    <div class="content">
      <h1 class="main-title">
        {teilnamebestaetigung}
      </h1>

      <div class="event-title">
        {str_event_name}
      </div>

      <div class="event-subtitle">
        {str_event_subtitle}
      </div>

      <div class="body-text">
        {confirmation_text}
      </div>
    </div>

    <div class="footer-left">
      Bern, {dtm_event_date}
    </div>

    <div class="signature-block">
      <img src="{signature_data_uri}" alt="Unterschrift">
      <div class="signature-name">
        Christian Maag<br>
        {geschaeftsfuehrer}
      </div>
    </div>

    <div class="address">
      Effingerstrasse 21 | 3011 Bern<br>
      www.lesen-schreiben-schweiz.ch
    </div>
  </div>
</body>
</html>
HTML,
);
