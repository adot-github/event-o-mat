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
     * Per-person dynamic replacements: address block, invoice text, bill total.
     * Called once per person inside the PDF generation loop.
     */
    'per_person_callback' => static function(array $person, array $event = [], string $person_lang = ''): array {
        global $wpdb;

        $p    = array_change_key_case($person, CASE_LOWER);
        $lang = $person_lang !== '' ? $person_lang : strtolower(trim($p['str_language'] ?? 'de'));

        $wordings = [
            'de' => [
                'rechnung'        => 'Rechnung',
                'rechnungsnummer' => 'Rechnungsnummer:',
                'gebuehr_fuer'    => 'Gebühr für:',
                'total'           => 'Total',
                'mwst'            => 'inkl. 8.1% MWSt.',
                'danke'           => 'Wir bedanken uns für die Überweisung innerhalb von 10 Tagen.',
            ],
            'fr' => [
                'rechnung'        => 'Facture',
                'rechnungsnummer' => 'N° de facture :',
                'gebuehr_fuer'    => 'Émoluments pour :',
                'total'           => 'Total',
                'mwst'            => 'TVA 8.1% incluse.',
                'danke'           => 'Nous vous remercions de votre virement dans un délai de 10 jours.',
            ],
            'it' => [
                'rechnung'        => 'Fattura',
                'rechnungsnummer' => 'N. fattura:',
                'gebuehr_fuer'    => 'Emolumento per:',
                'total'           => 'Totale',
                'mwst'            => 'IVA 8.1% inclusa.',
                'danke'           => 'La ringraziamo per il bonifico entro 10 giorni.',
            ],
        ];

        $w    = $wordings[$lang] ?? $wordings['de'];

        /* ---- language-specific event name ---- */
        $event_lc = array_change_key_case($event, CASE_LOWER);
        $str_event_name = '';
        foreach (['str_event_name_' . $lang, 'str_event_name_de', 'str_event_name'] as $key) {
            $val = trim($event_lc[$key] ?? '');
            if ($val !== '') {
                $str_event_name = $val;
                break;
            }
        }

        $type = (int) ($p['int_type_of_address'] ?? 1);

        /* ---- address block ---- */
        if ($type === 2) {
            $parts = array_filter([
                esc_html($p['str_institution'] ?? ''),
                esc_html($p['str_institution_division'] ?? ''),
                trim(esc_html($p['str_first_name'] ?? '') . ' ' . esc_html($p['str_last_name'] ?? '')),
                esc_html($p['str_institution_address'] ?? ''),
                trim(esc_html($p['str_institution_zip'] ?? '') . ' ' . esc_html($p['str_institution_city'] ?? '')),
            ]);
            $address_block = implode('<br>', $parts);
        } else {
            $address_block =
                trim(esc_html($p['str_first_name'] ?? '') . ' ' . esc_html($p['str_last_name'] ?? '')) . '<br>' .
                esc_html($p['str_address'] ?? '') . '<br>' .
                trim(esc_html($p['str_zip'] ?? '') . ' ' . esc_html($p['str_city'] ?? ''));
        }

        /* ---- billing rows ---- */
        $person_id = absint($p['id'] ?? $p['fky_person_id'] ?? 0);

        $rows = $person_id > 0 ? (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT str_billing_text, str_billing_text_detail, int_price
                   FROM {$wpdb->prefix}evtmgr_registrations_billing
                  WHERE fky_person_id = %d
               ORDER BY id ASC",
                $person_id
            ),
            ARRAY_A
        ) : [];

        $bill_total   = 0.0;
        $billing_rows = '';

        /* cell styles — inline for Outlook/PDF compatibility */
        $td   = 'padding:2px 5px;vertical-align:top;';
        $td_r = $td . 'text-align:right;white-space:nowrap;';

        foreach ($rows as $row) {
            $row         = array_change_key_case((array) $row, CASE_LOWER);
            $text        = esc_html($row['str_billing_text'] ?? '');
            $detail      = esc_html($row['str_billing_text_detail'] ?? '');
            $price       = (float) ($row['int_price'] ?? 0);
            $bill_total += $price;

            $billing_rows .=
                '<tr>' .
                    '<td style="' . $td . '">' . $text . '<br>' . $detail . '</td>' .
                    '<td style="' . $td_r . '">' . number_format($price, 2, '.', "'") . '</td>' .
                '</tr>';
        }

        $total_fmt  = number_format($bill_total, 2, '.', "'");
        $first_name = esc_html($p['str_first_name'] ?? '');
        $last_name  = esc_html($p['str_last_name'] ?? '');

        $invoice_text =
            '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:inherit;color:inherit;line-height:1.2">' .
            '<tr><td colspan="2" style="' . $td . '">' . $w['gebuehr_fuer'] . ' ' . $first_name . ' ' . $last_name . '</td></tr>' .
            $billing_rows .
            '<tr>' .
                '<td style="' . $td . 'border-top:1px solid #315d7d;font-weight:bold;"><strong>' . $w['total'] . '</strong></td>' .
                '<td style="' . $td_r . 'border-top:1px solid #315d7d;font-weight:bold;"><strong>' . $total_fmt . '</strong></td>' .
            '</tr>' .
            '<tr><td colspan="2" style="border:none;padding-top:20px;">' . $w['mwst'] . '</td></tr>' .
            '<tr><td colspan="2" style="border:none;padding-top:6px;">' . $w['danke'] . '</td></tr>' .
            '</table>';

        return [
            '{address_block}'        => $address_block,
            '{invoice_text}'         => $invoice_text,
            '{bill_total}'           => $total_fmt,
            '{wording_rechnung}'     => $w['rechnung'],
            '{wording_rechnungsnr}'  => $w['rechnungsnummer'],
            '{str_event_name}'       => esc_html($str_event_name),
        ];
    },

    'html_template' => <<<'HTML'
<!doctype html>
<html lang="{str_language}">
<head>
  <meta charset="utf-8">
  <title>{wording_rechnung}</title>
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

    .event-title h3 {
      margin: 0;
    }

    .event-subtitle {
      font-size: 12pt;
      line-height: 1.35;
      margin: 0;
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
      top: 254mm;
      z-index: 3;
      font-size: 8pt;
      line-height: 1.2;
      color: #315d7d;
    }

    .address_user_ez_2 {
      position: absolute;
      left: 130mm;
      top: 245mm;
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
      {address_block}
    </div>

    <div class="address_user_ez_1">
      {address_block}
    </div>

    <div class="address_user_ez_2">
      {address_block}
    </div>

    <div class="amount_ez_1">
      CHF {bill_total}
    </div>

    <div class="amount_ez_2">
      CHF {bill_total}
    </div>

    <div class="rg-datum">
      Bern, {dtm_event_date}
    </div>

    <div class="rg-nummer">
      {wording_rechnungsnr} {id}
    </div>

    <div class="event-title">
        <h3>{wording_rechnung}<br>
        {str_event_name}</h3>
        <div class="event-subtitle">
          {str_event_subtitle}
        </div>
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
