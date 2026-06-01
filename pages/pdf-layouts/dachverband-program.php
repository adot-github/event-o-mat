<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Layout config:
 * Programm
 */

$content_body_file = __DIR__ . DIRECTORY_SEPARATOR . 'content-body.php';
$body_text = is_readable($content_body_file)
    ? file_get_contents($content_body_file)
    : '';

$content_body_css = __DIR__ . DIRECTORY_SEPARATOR . 'content-body.css';
if (is_readable($content_body_css)) {
    $body_css = trim(file_get_contents($content_body_css));
    $body_css = preg_replace('/^\s*<style[^>]*>/i', '', $body_css);
    $body_css = preg_replace('/<\/style>\s*$/i', '', $body_css);
    $body_css = "<style>\n" . trim($body_css) . "\n</style>";
} else {
    $body_css = '';
}

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
            'invoice_text'         => $body_text,
            'body_css'             => $body_css,
        ),
        'fr' => array(
            'teilnamebestaetigung' => 'CONFIRMATION<br>DE PARTICIPATION',
            'invoice_text'         => $body_text,
            'body_css'             => $body_css,
        ),
        'it' => array(
            'teilnamebestaetigung' => 'CONFERMA DI PARTECIPAZIONE',
            'invoice_text'         => $body_text,
            'body_css'             => $body_css,
        ),
    ),

    'html_template' => <<<'HTML'
<!doctype html>
<html lang="{str_language}">
<head>
    <meta charset="utf-8">
    <title>Programm</title>

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
        }
    </style>

    {body_css}

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

        <div class="evtmgr-event-title">
            {str_event_name}
        </div>

        <div class="evtmgr-event-subtitle">
            {str_event_subtitle}
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
