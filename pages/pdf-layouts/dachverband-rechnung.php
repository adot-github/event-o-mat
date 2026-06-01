<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice layout config:
 * Rechnung Dachverband
 */
return array(
    'name'                => 'Rechnung Dachverband',
    'default_file_suffix' => 'invoice',
    'asset_dir'           => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets',

    /*
     * Placeholders in the HTML template => image file in asset_dir.
     */
    'images' => array(
        '{logo_data_uri}'      => 'logo.png',
        '{bg_data_uri}'        => 'ez-schein-dachverband-de.png',
        '{signature_data_uri}' => 'signature.png',
    ),

    /*
     * Language-specific content placeholders.
     * These values may contain HTML and may also contain other placeholders
     * such as {str_first_name}, {str_last_name}, {dtm_event_date}.
     */
    'texts' => array(
        'de' => array(
            'teilnamebestaetigung' => 'TEILNAHME-<br> BESTÄTIGUNG',
            'invoice_text'    => '
BETRAG 110.00 CHF<br>
Datum: 25.11.2025<br>
Zeit : 9.30–17.00 Uhr<br>
Ort : Welle 7, Schanzenstrasse 5, 3008 Bern<br>
Gebühr für: Jacqueline Gabi Pauli<br>
Die Rechnung gilt gleichzeitig als Teilnahmebestätigung<br>
Wir bedanken uns für die Überweisung innerhalb von 10 Tagen nach Rechnungsstellung.',
        ),
        'fr' => array(
            'teilnamebestaetigung' => 'CONFIRMATION<br>DE PARTICIPATION',
            'invoice_text'    => 'L\'association faîtière suisse<br>
Lecture et écriture confirme que<br><br>
<span class="participant-name">{str_first_name} {str_last_name}</span><br><br>
a participé, le {dtm_event_date}, au<br>
colloque national sur les compétences de base.',
        ),
        'it' => array(
            'teilnamebestaetigung' => 'CONFERMA DI PARTECIPAZIONE',
            'invoice_text'    => 'L\'associazione mantello svizzera<br>
Leggere e scrivere conferma che<br><br>
<span class="participant-name">{str_first_name} {str_last_name}</span><br><br>
ha partecipato il {dtm_event_date} al convegno nazionale<br>
sulle competenze di base.',
        ),
    ),

    'html_template' => <<<'HTML'
<!doctype html>
<html lang="{str_language}">
<head>
  <meta charset="utf-8">
  <title>Rechnung</title>
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

    .ez-schein {
      position: absolute;
      top: 208mm;
      left: 0;
      width: 210mm;
      opacity: 1;
      z-index: 1;
    }

    .ez-schein img {
      display: block;
      width: 100%;
      height: auto;
    }

    .content {
      position: absolute;
      top: 42mm;
      left: 0;
      width: 100%;
      text-align: left;
      z-index: 2;
      padding: 0 20mm;
      box-sizing: border-box;
    }

    .event-title {
        position: absolute;
      top: 110mm;
      left: 20mm;
      font-size: 10pt;
      font-weight:bold;
      line-height: 1.2;
    }

    .event-subtitle {
      margin: 0 0 38mm 0;
      font-size: 12pt;
      line-height: 1.35;
    }

    .invoice-text {
      position: absolute;
      left: 20mm;
      top: 100mm;
      right:20mm;
      font-size: 10pt;
      line-height: 1.2;
      align:left;
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

    .address {
      position: absolute;
      left: 20mm;
      top: 25mm;
      z-index: 3;
      font-size: 10pt;
      line-height: 1.2;
      color: #315d7d;
    }
    .address_user {
      position: absolute;
      left: 120mm;
      top: 60mm;
      z-index: 3;
      font-size: 10pt;
      line-height: 1.2;
      color: #315d7d;
    }

    .address_user_ez_1 {
      position: absolute;
      left: 10mm;
      top: 255mm;
      z-index: 3;
      font-size: 8pt;
      line-height: 1.2;
      color: #315d7d;
    }

    .address_user_ez_2 {
      position: absolute;
      left: 130mm;
      top: 247mm;
      z-index: 3;
      font-size: 8pt;
      line-height: 1.2;
      color: #315d7d;
    }

    .amount_ez_1 {
      position: absolute;
      left: 29mm;
      top: 280mm;
      z-index: 3;
      font-size: 12pt;
      line-height: 1.2;
      color: #315d7d;
    }

    .amount_ez_2 {
      position: absolute;
      left: 88mm;
      top: 285mm;
      z-index: 3;
      font-size: 12pt;
      line-height: 1.2;
      color: #315d7d;
    }

    .rg-datum {
    position: absolute;
      left: 20mm;
      top: 90mm;
      z-index: 3;
      font-size: 10pt;
      line-height: 1.2;
      color: #315d7d;
    }
    .rg-nummer {
        position: absolute;
      left: 20mm;
      top: 95mm;
      z-index: 3;
      font-size: 10pt;
      line-height: 1.2;
      color: #315d7d;
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

    <div class="address_user">
      {str_first_name} {str_last_name}<br>
      {str_address}<br>
      {str_zip} {str_city}
    </div>

    <div class="address_user_ez_1">
      {str_first_name} {str_last_name}<br>
      {str_address}<br>
      {str_zip} {str_city}
    </div>

    <div class="address_user_ez_2">
      {str_first_name} {str_last_name}<br>
      {str_address}<br>
      {str_zip} {str_city}
    </div>

    <div class="amount_ez_1">
      CHF 100.00
    </div>

    <div class="amount_ez_2">
      CHF 100.00
    </div>

    <div class="rg-datum">
      Bern, {dtm_event_date}
    </div>

    <div class="rg-nummer">
      Rechnungsnummer: {xxxxxxxxxxxxxxx}
    </div>

    <div class="event-title">
        <h3>Rechnung<br>
        {str_event_name}</h3>
    </div>

      <div class="event-subtitle">
        {str_event_subtitle}
      </div>

    <div class="content">
      <div class="invoice-text">
        {invoice_text}
      </div>
    </div>

    <div class="ez-schein">
      <img src="{bg_data_uri}" alt="">
    </div>


  </div>
</body>
</html>
HTML,
);
