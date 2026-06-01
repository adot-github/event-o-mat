<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Layout config:
 * Workshop-Buchungsliste
 */
return array(
    'name'                => 'Workshop-Buchungsliste',
    'default_file_suffix' => 'workshop-buchungsliste',
    'asset_dir'           => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets',

    'images' => array(
        '{logo_data_uri}' => 'logo.png',
    ),

    'texts' => array(
        'de' => array('invoice_text' => ''),
        'fr' => array('invoice_text' => ''),
        'it' => array('invoice_text' => ''),
    ),

    'html_template' => <<<'HTML'
<!doctype html>
<html lang="{str_language}">
<head>
    <meta charset="utf-8">
    <title>Workshop-Buchungsliste</title>

    <style>
        /*
         * First page stays controlled by the template layout.
         * Continued pages use the page box to start content 40mm from the top.
         */
        @page {
            size: A4;
            margin: 30mm 0 5mm 0;
        }

        @page:first {
            size: A4;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: auto;
            min-height: 297mm;
        }

        body {
            font-family: Calibri, Helvetica, sans-serif;
            color: #0f4f79;
            background: #ffffff;
        }

        .page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            overflow: visible;
            background: #ffffff;
        }

        .logo {
            position: absolute;
            top: 5mm;
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

        .evtmgr-event-title {
            position: absolute;
            top: 48mm;
            left: 20mm;
            font-size: 16pt;
            font-weight: bold;
            line-height: 1.2;
        }

        .evtmgr-event-subtitle {
            position: absolute;
            top: 54mm;
            left: 20mm;
            right:20mm;
            font-size: 16pt;
            font-weight: 300;
            line-height: 1;
        }

        .content {
            position: relative;
            width: 100%;
            text-align: left;
            z-index: 2;
            padding: 0 20mm 20mm;
            box-sizing: border-box;
        }

        /*
         * Use a real first-page spacer instead of padding-top on .content.
         * In Prince/DocRaptor, padding on a fragmented box can combine with
         * @page margins and create a large gap on page 2+.
         */
        .content::before {
            content: "";
            display: block;
            height: 70mm;
        }

        .content-text {
            position: static;
            font-size: 10pt;
            line-height: 1.2;
            text-align: left;





        .content-text {
            position: static;
            font-size: 9pt;
            line-height: 1.12;
            text-align: left;
        }

        .workshop-person-name {
            margin: 0 0 0.8mm;
            padding-top: 1mm;
            border-top: 0.35mm solid #444;
            font-size: 12pt;
            font-weight: 700;
            line-height: 1.1;
            break-after: avoid;
            page-break-after: avoid;
        }

        .workshop-title {
            margin: 0 0 1.2mm;
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.1;
            text-transform: uppercase;
            break-after: avoid;
            page-break-after: avoid;
        }

        .participants-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8.6pt;
        }

        .participants-table thead {
            display: table-header-group;
        }

        .participants-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .participants-table th,
        .participants-table td {
            padding: 0.55mm 1mm 0.5mm 0;
            border-top: 1px solid #000 !important;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }

        .participants-table th {
            font-weight: 400;
            text-align: left;
        }

        .col-num { width: 7mm; }
        .col-last { width: 17mm; }
        .col-first { width: 17mm; }
        .col-email { width: 43mm; }
        .col-job { width: 53mm; }
        .col-institution { width: auto; }

        .empty-row {
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="logo">
            <img src="{logo_data_uri}" alt="Logo">
        </div>

        <div class="evtmgr-event-title">
            {str_event_name}
        </div>

        <main class="content">
            <div class="content-text">
                {invoice_text}
            </div>
        </main>
    </div>
</body>
</html>
HTML,
);
