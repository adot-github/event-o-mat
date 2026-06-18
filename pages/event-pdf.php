<?php
if (!defined('ABSPATH')) {
    exit;
}

$_pdf_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_pdf_classes_dir . 'class-helpers.php';
require_once $_pdf_classes_dir . 'class-evtmgr-events.php';
require_once $_pdf_classes_dir . 'class-evtmgr-slots.php';
require_once $_pdf_classes_dir . 'class-evtmgr-workshops.php';
require_once $_pdf_classes_dir . 'class-evtmgr-presenters.php';
require_once $_pdf_classes_dir . 'class-evtmgr-time-zones.php';
require_once $_pdf_classes_dir . 'class-evtmgr-pricing.php';
require_once $_pdf_classes_dir . 'class-evtmgr-sponsors.php';
require_once $_pdf_classes_dir . 'class-evtmgr-options.php';

require_once __DIR__ . '/vendor/autoload.php';

$events_obj = new Evtmgr_Events();
$event_uid  = $events_obj->get_current_event_uid(true);
$event      = $events_obj->get_current_event('de', true);

if (empty($event)) {
    echo '<div class="alert alert-danger">Kein Event gefunden.</div>';
    return;
}

$assets_dir = __DIR__ . '/assets/';

// ── Hilfsfunktionen ─────────────────────────────────────────────────────────

if (!function_exists('evtpdf_file_to_data_uri')) {
    function evtpdf_file_to_data_uri(string $path, string $mime = ''): string {
        if (!file_exists($path)) {
            return '';
        }
        $data = file_get_contents($path);
        if ($data === false) {
            return '';
        }
        if ($mime === '') {
            $mime = mime_content_type($path) ?: 'application/octet-stream';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}

if (!function_exists('evtpdf_e')) {
    function evtpdf_e(?string $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ── Formular ─────────────────────────────────────────────────────────────────

$should_generate = false;

if (isset($_POST['evtpdf_generate_action'])) {
    $nonce = sanitize_text_field(wp_unslash($_POST['evtpdf_generate_nonce'] ?? ''));
    if (wp_verify_nonce($nonce, 'evtpdf_generate_action')) {
        $should_generate = true;
    }
}
?>

<div class="container-fluid pt-3">
    <h2 class="mt-0">PDF Generierung – Event</h2>

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-1"><strong>Event:</strong> <?php echo esc_html($event['str_event_name'] ?? ''); ?></p>
            <p class="mb-3"><strong>UID:</strong> <?php echo esc_html($event_uid); ?></p>

            <form method="post" action="" id="evtpdf-generate-form" class="d-inline">
                <?php wp_nonce_field('evtpdf_generate_action', 'evtpdf_generate_nonce'); ?>
                <input type="hidden" name="evtpdf_generate_action" value="1">
                <button type="submit" name="evtpdf_generate" value="1"
                        id="evtpdf-generate-button" class="btn btn-primary">
                    <span class="evtpdf-btn-text">PDF jetzt generieren</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none"
                          id="evtpdf-generate-spinner" role="status" aria-hidden="true"></span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form    = document.getElementById('evtpdf-generate-form');
    var button  = document.getElementById('evtpdf-generate-button');
    var spinner = document.getElementById('evtpdf-generate-spinner');

    if (form && button && spinner) {
        form.addEventListener('submit', function () {
            button.disabled = true;
            spinner.classList.remove('d-none');
        });
    }
});
</script>

<?php
if (!$should_generate) {
    return;
}

try {
    $logo_uri = evtpdf_file_to_data_uri($assets_dir . 'logo-fhnw.png', 'image/png');
    $bg_uri   = evtpdf_file_to_data_uri($assets_dir . 'bild-seite-2.jpg', 'image/jpeg');
    $bg3_uri  = evtpdf_file_to_data_uri($assets_dir . 'campussaal.jpg',   'image/jpeg');

    // ── QR-Code ──────────────────────────────────────────────────────────────
    $qr_img = '';
    try {
        $qr_options               = new \chillerlan\QRCode\QROptions;
        $qr_options->eccLevel     = \chillerlan\QRCode\Common\EccLevel::L;
        $qr_options->addQuietzone = true;
        $qr_options->outputBase64 = true;
        $qr_uri = (new \chillerlan\QRCode\QRCode($qr_options))
            ->render('https://www.fhnw.ch/de/wirtschaft/aktuelles/veranstaltungen/alle/e-commerce-best-practice-day');
        $qr_img = '<img src="' . $qr_uri . '" alt="QR Code">';
    } catch (\Throwable $qr_ex) {
        $qr_img = '';
    }

    $event_name     = evtpdf_e($event['str_event_name']     ?? '');
    $event_subtitle = evtpdf_e($event['str_event_subtitle'] ?? '');
    $event_uid_safe = evtpdf_e($event_uid);

    $german_days = ['Sunday' => 'Sonntag', 'Monday' => 'Montag', 'Tuesday' => 'Dienstag',
                    'Wednesday' => 'Mittwoch', 'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 'Saturday' => 'Samstag'];

    $event_date_raw = trim((string) ($event['dtm_event_date'] ?? ''));
    if ($event_date_raw !== '') {
        $ts             = strtotime($event_date_raw);
        $day_name       = $german_days[date('l', $ts)] ?? date('l', $ts);
        $event_date_fmt = evtpdf_e($day_name . ', ' . date('j.n.Y', $ts));
    } else {
        $event_date_fmt = '';
    }

    $reg_opened_raw = trim((string) ($event['dtm_registration_opened'] ?? ''));
    if ($reg_opened_raw !== '') {
        $ts              = strtotime($reg_opened_raw);
        $day_name        = $german_days[date('l', $ts)] ?? date('l', $ts);
        $reg_opened_fmt  = evtpdf_e($day_name . ', ' . date('j.n.Y', $ts));
    } else {
        $reg_opened_fmt  = '';
    }

    $event_description = (string) ($event['mem_event_description_de'] ?? '');

    // ── Keynote-Speaker-Liste (Seite 3) ──────────────────────────────────────
    $slots_obj_pdf      = new Evtmgr_Slots();
    $workshops_obj_pdf  = new Evtmgr_Workshops();
    $presenters_obj_pdf = new Evtmgr_Presenters();

    $pdf_lang        = 'de';
    $pdf_slots       = $slots_obj_pdf->get_slots_for_output($event_uid, $pdf_lang);
    $speakers_html   = '';

    foreach ((array) $pdf_slots as $pdf_slot) {
        $pdf_slot_id   = absint($pdf_slot['id'] ?? 0);
        $pdf_slot_name = trim((string) ($pdf_slot['str_slot_name'] ?? ''));

        if ($pdf_slot_id <= 0 || $pdf_slot_name === '') {
            continue;
        }

        $pdf_workshops = $workshops_obj_pdf->get_workshops_all_by_slot($pdf_slot_id, $event_uid, $pdf_lang);

        if (empty($pdf_workshops)) {
            continue;
        }

        $pdf_lines = array();

        foreach ($pdf_workshops as $pdf_workshop) {
            $pdf_workshop_id = absint($pdf_workshop['id'] ?? 0);
            $time_from_raw   = trim((string) ($pdf_workshop['dtm_time_from'] ?? ''));
            $time_to_raw     = trim((string) ($pdf_workshop['dtm_time_to']   ?? ''));
            $time_from_fmt   = strlen($time_from_raw) >= 5 ? substr($time_from_raw, 0, 5) : '';
            $time_to_fmt     = strlen($time_to_raw)   >= 5 ? substr($time_to_raw,   0, 5) : '';

            if ($time_from_fmt !== '' && $time_to_fmt !== '') {
                $time_label = $time_from_fmt . '–' . $time_to_fmt . ' Uhr';
            } elseif ($time_from_fmt !== '') {
                $time_label = $time_from_fmt . ' Uhr';
            } else {
                $time_label = '';
            }

            $pdf_presenters = $presenters_obj_pdf->get_presenters_by_workshop_id($pdf_workshop_id, $pdf_lang);
            $speaker_parts  = array();

            foreach ($pdf_presenters as $pres) {
                $name         = trim((string) ($pres['str_first_name'] ?? '') . ' ' . (string) ($pres['str_last_name'] ?? ''));
                $str_name     = trim((string) ($pres['str_name']        ?? ''));
                $str_employer = trim((string) ($pres['str_employer']    ?? ''));
                $institution  = trim((string) ($pres['str_institution'] ?? ''));

                $suffix = array_filter(array($str_name, $str_employer));
                if (!empty($suffix)) {
                    $name = trim($name . ', ' . implode(', ', $suffix));
                }

                if ($name !== '' && $institution !== '') {
                    $speaker_parts[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($institution, ENT_QUOTES, 'UTF-8') . ')';
                } elseif ($name !== '') {
                    $speaker_parts[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                }
            }

            $speaker_text = implode(' / ', $speaker_parts);

            if ($speaker_text === '') {
                $speaker_text = htmlspecialchars(trim((string) ($pdf_workshop['str_workshop_title'] ?? '')), ENT_QUOTES, 'UTF-8');
            }

            if ($speaker_text === '') {
                continue;
            }

            $line = $time_label !== ''
                ? '<span class="ks-time">' . htmlspecialchars($time_label, ENT_QUOTES, 'UTF-8') . ':</span> ' . $speaker_text
                : $speaker_text;

            $pdf_lines[] = '<li>' . $line . '</li>';
        }

        if (empty($pdf_lines)) {
            continue;
        }

        $speakers_html .= '<div class="ks-slot">'
            . '<h3 class="ks-slot-title">' . htmlspecialchars($pdf_slot_name, ENT_QUOTES, 'UTF-8') . '</h3>'
            . '<ul class="ks-list">' . implode('', $pdf_lines) . '</ul>'
            . '</div>';
    }

    // ── Partner-/Sponsor-Logos (Seite 4) ─────────────────────────────────────
    $sponsors_obj  = new Evtmgr_Sponsors();
    $pdf_sponsors  = $sponsors_obj->get_sponsors_by_event_uid($event_uid, 'de');
    $sponsors_html = '';

    if (!empty($pdf_sponsors)) {
        $groups = array();
        foreach ($pdf_sponsors as $s) {
            $group            = trim((string) ($s['str_sponsor_group'] ?? ''));
            $groups[$group][] = $s;
        }

        foreach ($groups as $group_label => $items) {
            $logo_items = '';

            $upload_dir = wp_upload_dir();
            $upload_baseurl  = rtrim((string) ($upload_dir['baseurl'] ?? ''), '/');
            $upload_basedir  = rtrim((string) ($upload_dir['basedir'] ?? ''), '/');

            foreach ($items as $s) {
                $logo_raw = trim((string) ($s['str_sponsor_logo'] ?? ''));
                $name_raw = trim((string) ($s['str_sponsor_name'] ?? ''));

                if ($logo_raw === '') {
                    continue;
                }

                // Resolve to local file path for data-URI embedding.
                if (ctype_digit($logo_raw)) {
                    $local_path = (string) get_attached_file((int) $logo_raw);
                } elseif (preg_match('#^https?://#i', $logo_raw) && $upload_baseurl !== '' && strpos($logo_raw, $upload_baseurl) === 0) {
                    $local_path = $upload_basedir . substr($logo_raw, strlen($upload_baseurl));
                } elseif (strpos($logo_raw, '/') === 0) {
                    $local_path = ABSPATH . ltrim($logo_raw, '/');
                } else {
                    $local_path = '';
                }

                if ($local_path !== '' && file_exists($local_path)) {
                    $src_attr = evtpdf_file_to_data_uri($local_path);
                } else {
                    $src_attr = '';
                }

                if ($src_attr === '') {
                    continue;
                }

                $alt_attr = htmlspecialchars($name_raw !== '' ? $name_raw : 'Logo', ENT_QUOTES, 'UTF-8');
                $logo_items .= '<div class="sp-item"><img src="' . $src_attr . '" alt="' . $alt_attr . '"></div>';
            }

            if ($logo_items === '') {
                continue;
            }

            if ($group_label !== '') {
                $sponsors_html .= '<h3 class="sp-group-title">' . htmlspecialchars($group_label, ENT_QUOTES, 'UTF-8') . '</h3>';
            }

            $sponsors_html .= '<div class="sp-grid">' . $logo_items . '</div>';
        }
    }

    // ── Preise und Anmeldung (Seite 3) ──────────────────────────────────────
    $pricing_obj  = new Evtmgr_Pricing();
    $pr_parents   = $pricing_obj->get_pricing_top($event_uid, $pdf_lang);
    $pricing_html = '';

    foreach ((array) $pr_parents as $pr_parent) {
        $pr_pid   = absint($pr_parent['id'] ?? 0);
        $pr_pname = evtpdf_e($pr_parent['str_pricing_name'] ?? '');
        $pr_pdesc = trim((string) ($pr_parent['mem_pricing_description'] ?? ''));

        $pr_children = $pricing_obj->get_pricing_by_parent($pr_pid, $event_uid, $pdf_lang);

        $pr_pamount = $pricing_obj->normalize_price($pr_parent['num_price'] ?? 0);
        if ($pr_pamount == 0) {
            $pr_parent_price_fmt = 'kostenlos';
        } elseif ($pr_pamount == floor($pr_pamount)) {
            $pr_parent_price_fmt = 'CHF ' . number_format($pr_pamount, 0, '.', "'") . '.–';
        } else {
            $pr_parent_price_fmt = 'CHF ' . number_format($pr_pamount, 2, '.', "'");
        }

        if ($pr_pname !== '') {
            $pr_sec_price = '<td class="pr-price">' . evtpdf_e($pr_parent_price_fmt) . '</td>';
            $pricing_html .= '<tr class="pr-section"><td>' . $pr_pname . '</td>' . $pr_sec_price . '</tr>';
        }

        foreach ((array) $pr_children as $pr_child) {
            $pr_cname  = evtpdf_e($pr_child['str_pricing_name'] ?? '');
            $pr_cdesc  = trim((string) ($pr_child['mem_pricing_description'] ?? ''));
            $pr_amount = $pricing_obj->normalize_price($pr_child['num_price'] ?? 0);
            $pr_valid  = trim((string) ($pr_child['dtm_date_valid_to'] ?? ''));

            if ($pr_amount == 0) {
                $pr_price_fmt = 'kostenlos';
            } elseif ($pr_amount == floor($pr_amount)) {
                $pr_price_fmt = 'CHF ' . number_format($pr_amount, 0, '.', "'") . '.–';
            } else {
                $pr_price_fmt = 'CHF ' . number_format($pr_amount, 2, '.', "'");
            }

            $pr_name_cell = $pr_cname;
            if ($pr_cdesc !== '') {
                $pr_name_cell .= '<div class="pr-desc">' . evtpdf_e($pr_cdesc) . '</div>';
            }
            if ($pr_valid !== '' && strlen($pr_valid) >= 10) {
                $pr_valid_ts  = strtotime($pr_valid);
                $pr_name_cell .= '<div class="pr-valid">gültig bis ' . evtpdf_e(date('j.n.Y', $pr_valid_ts)) . '</div>';
            }

            $pricing_html .= '<tr class="pr-row">'
                . '<td class="pr-name">' . $pr_name_cell . '</td>'
                . '<td class="pr-price">' . evtpdf_e($pr_price_fmt) . '</td>'
                . '</tr>';
        }
    }

    if ($pricing_html !== '') {
        $pricing_html = '<table class="pr-table"><tbody>' . $pricing_html . '</tbody></table>';
    } else {
        $pricing_html = '<p>Keine Preise vorhanden.</p>';
    }

    // ── Timetable (Seite 4) ──────────────────────────────────────────────────
    $tz_obj      = new Evtmgr_Time_Zones();
    $tt_slots    = $slots_obj_pdf->get_slots_with_timezone($event_uid, $pdf_lang);
    $tt_parents  = $tz_obj->get_time_zones_top($event_uid, $pdf_lang);
    $tt_col_cnt  = count($tt_slots);

    $timetable_html  = '';

    if ($tt_col_cnt > 0 && !empty($tt_parents)) {
        // Only show parent groups that actually have slots assigned
        $active_parent_ids = array_values(array_unique(array_filter(
            array_map('absint', array_column($tt_slots, 'fky_timezone_id'))
        )));

        $tt_header = '<tr><th class="tt-th-time">Zeit</th>';
        foreach ($tt_slots as $tsl) {
            $tsl_color_raw = trim((string) ($tsl['str_color'] ?? ''));
            $tsl_color     = $tsl_color_raw !== '' ? '#' . ltrim($tsl_color_raw, '#') : '';
            $tsl_style     = $tsl_color !== '' ? ' style="background-color:' . htmlspecialchars($tsl_color, ENT_QUOTES, 'UTF-8') . ';"' : '';
            $tt_header .= '<th class="tt-th-slot"' . $tsl_style . '>' . evtpdf_e($tsl['str_slot_name'] ?? '') . '</th>';
        }
        $tt_header .= '</tr>';

        $tt_rows   = '';
        $tt_total  = $tt_col_cnt + 1;

        foreach ($tt_parents as $tt_parent) {
            $tt_pid   = absint($tt_parent['id'] ?? 0);
            if (!in_array($tt_pid, $active_parent_ids, true)) {
                continue;
            }
            $tt_pname    = evtpdf_e($tt_parent['str_timezone_name'] ?? '');
            $tt_children = $tz_obj->get_time_zones_by_parent($tt_pid, $event_uid, $pdf_lang);

            // Build child rows first; skip the whole section if no workshop content exists
            $tt_section_rows    = '';
            $tt_section_has_ws  = false;

            foreach ($tt_children as $tt_child) {
                if (empty($tt_child['ysn_show_timezone_in_output'])) {
                    continue;
                }
                $tt_cid   = absint($tt_child['id'] ?? 0);
                $tt_tfrom = trim((string) ($tt_child['dtm_time_from'] ?? ''));
                $tt_tto   = trim((string) ($tt_child['dtm_time_to']   ?? ''));
                $tt_tfrom = strlen($tt_tfrom) >= 5 ? substr($tt_tfrom, 0, 5) : '';
                $tt_tto   = strlen($tt_tto)   >= 5 ? substr($tt_tto,   0, 5) : '';
                $tt_time  = $tt_tfrom !== '' ? ($tt_tto !== '' ? $tt_tfrom . '–' . $tt_tto : $tt_tfrom) : '';
                $tt_cname = evtpdf_e($tt_child['str_timezone_name'] ?? '');
                $is_fw    = !empty($tt_child['ysn_show_fullwidth']);

                $tt_cells       = array();
                $tt_has_any_ws  = false;
                foreach ($tt_slots as $tsl) {
                    $tsl_id = absint($tsl['id'] ?? 0);
                    $tt_ws  = $workshops_obj_pdf->get_workshops_by_slot($tsl_id, $tt_cid, $event_uid, $pdf_lang);
                    $tt_cell = '';
                    foreach ((array) $tt_ws as $ttw) {
                        $ttw_num   = trim((string) ($ttw['str_workshop_number'] ?? ''));
                        $ttw_title = evtpdf_e(trim((string) ($ttw['str_workshop_title'] ?? '')));
                        if ($ttw_title === '') { continue; }
                        $tt_has_any_ws     = true;
                        $tt_section_has_ws = true;
                        $tt_cell .= '<div class="tt-ws">'
                            . ($ttw_num !== '' ? '<span class="tt-wnum">' . evtpdf_e($ttw_num) . '</span> ' : '')
                            . $ttw_title . '</div>';
                    }
                    $tt_cells[] = $tt_cell;
                }

                $tt_tcell = '<td class="tt-time">' . evtpdf_e($tt_time) . '</td>';
                if ($is_fw || !$tt_has_any_ws) {
                    $tt_section_rows .= '<tr class="tt-row-fw">' . $tt_tcell
                        . '<td colspan="' . $tt_col_cnt . '" class="tt-cell-fw">' . $tt_cname . '</td></tr>';
                } else {
                    $tt_section_rows .= '<tr class="tt-row">' . $tt_tcell;
                    foreach ($tt_cells as $tc) {
                        $tt_section_rows .= '<td class="tt-cell">' . $tc . '</td>';
                    }
                    $tt_section_rows .= '</tr>';
                }

            }

            // Only include section if at least one child timezone has workshops
            if (!$tt_section_has_ws) {
                continue;
            }
            if ($tt_pname !== '') {
                $tt_rows .= '<tr><td colspan="' . $tt_total . '" class="tt-section">' . $tt_pname . '</td></tr>';
            }
            $tt_rows .= $tt_section_rows;
        }
        $timetable_html = '<table class="tt-table"><thead>' . $tt_header . '</thead><tbody>' . $tt_rows . '</tbody></table>';
    } else {
        $timetable_html = '<p>Kein Timetable vorhanden.</p>';
    }

    $logo_img = $logo_uri !== ''
        ? '<img src="' . $logo_uri . '" alt="">'
        : '';

    $bg_style = $bg_uri !== ''
        ? 'background-image: url(\'' . $bg_uri . '\'); background-size: cover; background-position: center;'
        : 'background: #2c6ea8;';

    $bg3_inline = $bg3_uri !== ''
        ? ' style="background-image:url(\'' . $bg3_uri . '\');background-size:cover;background-position:center;"'
        : '';

    $pdf_dir = __DIR__ . '/pdf';
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }

    $filename = sanitize_file_name('event-' . $event_uid . '.pdf');

    $html = <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>{$event_name}</title>
  <style>
    @page {
      size: A4;
      margin: 0;
    }

    *, *::before, *::after {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      padding: 0;
      width: 210mm;
      background: #ffffff;
      font-family: Arial, Helvetica, sans-serif;
      color: #1f1f1f;
    }

    .page {
      position: relative;
      width: 210mm;
      height: 297mm;
      overflow: hidden;
      background: #ffffff;
      page-break-after: always;
    }

    /* ── Header ─────────────────────── */
    .header {
      position: absolute;
      left: 0;
      top: 0;
      width: 210mm;
      height: 20mm;
      background: #ffffff;
      border-bottom: 0.5pt solid #dddddd;
    }

    .logo {
      position: absolute;
      left: 10mm;
      top: 5mm;
      height: 10mm;
    }

    .logo img {
      display: block;
      height: 100%;
      width: auto;
    }

    /* ── Hero ───────────────────────── */
    .hero {
      position: absolute;
      left: 0;
      top: 20mm;
      width: 210mm;
      height: 90mm;
      {$bg_style}
    }

    .hero-page-1 {
      position: absolute;
      left: 0;
      top: 27mm;
      bottom:0;
      width: 195mm;
      {$bg_style}
    }


    /* ── Titel-Band ─────────────────── */
    .gelbe-flaeche {
      position: absolute;
      left: 0;
      top: 110mm;
      width: 210mm;
      height: 50mm;
      background: #efe729;
    }

    .gelbe-flaeche-front {
      position: absolute;
      left: 0;
      bottom:0;
      top: 230mm;
      width: 155mm;
      background: #efe729;
    }


    .title-wrap {
      position: absolute;
      left: 12mm;
      top: 116mm;
      width: 186mm;
    }

    .title-wrap-front {
      position: absolute;
      left: 12mm;
      top: 235mm;
      width: 125mm;
    }


    .event-title {
      margin: 0;
      font-size: 24pt;
      font-weight: bold;
      line-height: 1.15;
      color: #000000;
    }

    .event-subtitle {
      margin: 3mm 0 0;
      font-size: 15pt;
      font-weight: normal;
      line-height: 1.3;
      color: #000000;
    }

    .body-text {
      position: absolute;
      left: 12mm;
      top: 170mm;
      width: 190mm;
      font-size:1rem;
    }

    .page-3-wrap {
      position: absolute;
      left: 12mm;
      top: 30mm;
      width: 190mm;
      font-size:1rem;
    }
    


    /* ── Keynote-Speaker-Liste ──────── */
    .ks-slot {
      margin-bottom: 2mm;
    }

    .ks-slot-title {
      margin-top: 12pt;
      font-size: 12pt;
      font-weight: bold;
      color: #000000;
      border-bottom: 0.3pt solid #cccccc;
      padding-bottom: 0.5mm;
    }

    .ks-list {
      margin: 0;
      padding: 0 0 0 3mm;
      list-style: disc;
    }

    .ks-list li {
      margin: 0;
      font-size: 10pt;
      line-height: 1.2;
    }

    .ks-time {
      font-weight: bold;
    }

    /* ── Preise ─────────────────────── */
    .pr-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 9pt;
      margin-top: 2mm;
    }

    .pr-section td {
      background: #ffffff;
      color: #000000;
      font-weight: bold;
      font-size: 10pt;
      padding: 3mm 3mm 1mm;
      border-top: 1pt solid #000000;
    }

    .pr-row td {
      padding: 1.5mm 3mm;
      border-bottom: 0.3pt solid #eeeeee;
      vertical-align: top;
    }

    .pr-name {
      width: 75%;
    }

    .pr-price {
      width: 25%;
      text-align: right;
      font-weight: bold;
      white-space: nowrap;
    }

    .pr-desc {
      font-size: 7.5pt;
      color: #555555;
      margin-top: 0.5mm;
    }

    .pr-valid {
      font-size: 7pt;
      color: #888888;
      font-style: italic;
      margin-top: 0.5mm;
    }

    /* ── Timetable ──────────────────── */
    .tt-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      font-size: 8.5pt;
    }

    .tt-th-time {
      width: 18mm;
      background: #efe729;
      padding: 1mm 2mm;
      font-weight: bold;
      text-align: left;
      border: 0.3pt solid #cccccc;
    }

    .tt-th-slot {
      background: #efe729;
      padding: 1mm 2mm;
      font-weight: bold;
      text-align: left;
      border: 0.3pt solid #cccccc;
    }

    .tt-section {
      background: #333333;
      color: #ffffff;
      font-weight: bold;
      padding: 1mm 2mm;
      font-size: 7pt;
    }

    .tt-time {
      padding: 1mm 2mm;
      vertical-align: top;
      border: 0.3pt solid #dddddd;
      font-weight: bold;
      white-space: nowrap;
    }

    .tt-cell {
      padding: 1mm 2mm;
      vertical-align: top;
      border: 0.3pt solid #dddddd;
    }

    .tt-cell-fw {
      padding: 1mm 2mm;
      vertical-align: middle;
      border: 0.3pt solid #dddddd;
      background: #f8f8f8;
      color: #444444;
    }

    .tt-row-fw .tt-time {
      background: #f8f8f8;
    }

    .tt-ws {
      margin-bottom: 0.5mm;
      line-height: 1.2;
    }

    .tt-wnum {
      font-weight: bold;
    }

    /* ── Partner-Logos ──────────────── */
    .sp-group-title {
      margin: 4mm 0 2mm;
      font-size: 10pt;
      font-weight: bold;
      color: #000000;
      border-bottom: 0.5pt solid #cccccc;
      padding-bottom: 1mm;
    }

    .sp-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 4mm;
      margin-bottom: 4mm;
    }

    .sp-item {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40mm;
      height: 18mm;
      border: 0.3pt solid #dddddd;
      padding: 2mm;
    }

    .sp-item img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }

    /* ── QR-Code ────────────────────── */
    .qr-block {
      position: absolute;
      left: 10mm;
      bottom: 9mm;
      width: 24mm;
      height: 24mm;
    }

    .qr-block img {
      display: block;
      width: 100%;
      height: 100%;
    }

    /* ── Footer ─────────────────────── */
    .footer-band {
      position: absolute;
      left: 0;
      bottom: 0;
      width: 210mm;
      height: 6mm;
      background: #efe729;
    }
  </style>
</head>
<body>

  <!-- Seite 1 -->
  <div class="page">
    <div class="header">
      <div class="logo">{$logo_img}</div>
    </div>

    <div class="hero-page-1"></div>

    <div class="gelbe-flaeche-front"></div>

    <div class="title-wrap-front">
      <h1 class="event-title">{$event_name}</h1>
      <div class="event-subtitle">{$event_subtitle}</div>
      <div>{$event_date_fmt}</div>
      <div>Anmeldung ab: {$reg_opened_fmt}</div>
    </div>
  </div>








  <!-- Seite 2 -->
  <div class="page">
    <div class="header">
      <div class="logo">{$logo_img}</div>
    </div>

    <div class="hero"></div>

    <div class="gelbe-flaeche"></div>

    <div class="title-wrap">
      <h1 class="event-title">{$event_name}</h1>
      <div class="event-subtitle">{$event_subtitle}</div>
    </div>

    <div class="body-text">
        {$event_description}
    </div>

    <div class="qr-block">{$qr_img}</div>

    <div class="footer-band"></div>
  </div>



  <!-- Seite 3 – Preise und Anmeldung -->
  <div class="page">
    <div class="header">
      <div class="logo">{$logo_img}</div>
    </div>

    <div class="hero"{$bg3_inline}></div>

    <div class="gelbe-flaeche"></div>

    <div class="title-wrap">
      <h1 class="event-title">Campussaal Brugg-Windisch</h1>
      <div class="event-subtitle">Fachhochschule Nordwestschweiz FHNW, Bahnhofstrasse 6, 5210 Windisch (Schweiz)</div>
    </div>

    <div class="body-text">
        <h2>Preise und Anmeldung</h2>
        {$pricing_html}

        <h2>Anmeldung</h2>
        <p>https://www.fhnw.ch/de/wirtschaft/aktuelles/veranstaltungen/alle/e-commerce-best-practice-day</p>
    </div>

    <div class="footer-band"></div>
  </div>



  <!-- Seite 4 – Timetable -->
  <div class="page">
    <div class="header">
      <div class="logo">{$logo_img}</div>
    </div>

    <div class="page-3-wrap">
        <h2>Programm</h2>
        {$timetable_html}
    </div>

    <div class="footer-band"></div>
  </div>



  <!-- Seite 5 – Keynote-Speakers -->
  <div class="page">
    <div class="header">
      <div class="logo">{$logo_img}</div>
    </div>


    <div class="page-3-wrap">
        <h2>Keynote-Speakers</h2>
        {$speakers_html}
    </div>

    <div class="footer-band"></div>
  </div>



  <!-- Seite 7 – Partner & Sponsoren -->
  <div class="page">
    <div class="header">
      <div class="logo">{$logo_img}</div>
    </div>

    <div class="page-3-wrap">
        <h2>Partner &amp; Sponsoren</h2>
        {$sponsors_html}

        <h3>Partnerschaften</h3>
        An einer Partnerschaft interessiert? Prof. Dr. Darius Zumstein gibt Auskunft: darius.zumstein@fhnw.ch
    </div>

    <div class="footer-band"></div>
  </div>

</body>
</html>
HTML;

    $docraptor = new DocRaptor\DocApi();
    $docraptor->getConfig()->setUsername('u2UGJ0xRC-dYkb42Q--J');

    $doc = new DocRaptor\Doc();
    $doc->setTest(Evtmgr_Options::is_pdf_test_mode($event_uid));
    $doc->setDocumentType('pdf');
    $doc->setName($filename);
    $doc->setDocumentContent($html);

    $pdf = $docraptor->createDoc($doc);
    file_put_contents($pdf_dir . '/' . $filename, $pdf);

    echo '<div class="container-fluid pb-4">';
    echo '<div class="alert alert-success">PDF erfolgreich erstellt: ' . esc_html($filename) . '</div>';
    echo '</div>';

} catch (Throwable $e) {
    echo '<div class="container-fluid pb-4">';
    echo '<div class="alert alert-danger">Fehler: ' . esc_html($e->getMessage()) . '</div>';
    echo '</div>';
}
