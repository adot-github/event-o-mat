<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Label layout config: Namensetiketten Dachverband
 *
 * The full label grid is built by etiketten-pdf-create.php and passed
 * via {pages_html}. This file only provides the page shell + logo image.
 *
 * Placeholders:
 *   {fontsize}    — e.g. "12.0pt"
 *   {pages_html}  — one or more <div class="page">...</div> blocks
 */
return [
    'name'      => 'Namensetiketten Dachverband',
    'asset_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets',

    'images' => [
        '{logo_data_uri}' => 'logo.png',
    ],

    'html_template' => <<<'HTML'
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page {
      size: A4;
      margin: 0;
    }

    html, body {
      margin: 0;
      padding: 0;
    }

    .page {
      width: 210mm;
      height: 297mm;
      position: relative;
      overflow: hidden;
      page-break-after: always;
    }

    .label {
      position: absolute;
      box-sizing: border-box;
      overflow: hidden;
      padding: 1.5mm 2mm;
      font-family: Arial, Helvetica, sans-serif;
      font-size: {fontsize};
      line-height: 1.2;
      color: #000;
    }

    .label-logo img {
      display: block;
      height: auto;
      margin-bottom: 0.5mm;
    }

    .label-name {
      font-weight: bold;
    }

    .label-jobtitle,
    .label-institution,
    .label-workshops {
      margin-top: 0.3mm;
    }
  </style>
</head>
<body>
{pages_html}
</body>
</html>
HTML,
];
