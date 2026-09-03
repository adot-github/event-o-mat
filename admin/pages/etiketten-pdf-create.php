<style>
    legend {font-size:1rem;font-weight:400;margin-bottom:1rem;}
</style>
<?php

/**
 * Namensetiketten PDF creation.
 * Generates one A4 PDF with all selected persons' labels laid out in a grid.
 */

$wp_load = dirname(__FILE__, 8) . '/wp-load.php';

if (!file_exists($wp_load)) {
    die('wp-load.php not found: ' . htmlspecialchars($wp_load, ENT_QUOTES, 'UTF-8'));
}

require_once $wp_load;

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/../classes/class-event-registration.php';
require_once dirname(__DIR__) . '/../classes/class-evtmgr-persons.php';
require_once dirname(__DIR__) . '/../classes/class-evtmgr-events.php';
require_once dirname(__DIR__) . '/../classes/class-pdf-creation.php';
require_once dirname(__DIR__) . '/../classes/class-evtmgr-options.php';

$type_of_pdf      = 'Namensetiketten';
$type_of_pdf_sing = 'Namensetikette';
$subfolder_for_pdf = 'etiketten';

try {
    global $wpdb;

    $label_types = (array) $wpdb->get_results(
        "SELECT id, etk_name, etk_width, etk_height,
                page_margin_top, page_margin_left,
                horizontal_distance_between_etk,
                vertical_distance_between_etk
           FROM {$wpdb->prefix}evtmgr_etiketten
          ORDER BY id ASC",
        ARRAY_A
    );

    if (empty($label_types)) {
        throw new RuntimeException('Keine Etiketten-Typen in der Datenbank gefunden (wp_evtmgr_etiketten).');
    }

    $label_types_by_id = [];
    foreach ($label_types as $lt) {
        $label_types_by_id[(int) $lt['id']] = $lt;
    }

    $pdf_creator        = new Event_Registration_Pdf_Creation(__DIR__);
    $persons_obj        = new class_evtmgr_persons();
    $event_registration = new Event_Registration_Context();
    $event_uid          = $event_registration->get_cookie_event_uid(true);

    $event_obj = new Evtmgr_Events();
    $event     = $event_obj->get_events_by_event_uid($event_uid, 'de');

    if (empty($event)) {
        throw new RuntimeException('Kein Kongress für Event-UID gefunden: ' . $event_uid);
    }

    $str_event_name_ = $event['str_event_name'] ?? $event['str_event_name_de'] ?? '';

    $persons = $persons_obj->get_persons_registered($event_uid);

    if (empty($persons)) {
        throw new RuntimeException('Keine registrierten Personen gefunden.');
    }

    $selected_person_ids = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_person_ids'])) {
        $posted = wp_unslash($_POST['selected_person_ids']);
        if (!is_array($posted)) {
            $posted = [$posted];
        }
        $selected_person_ids = array_values(array_unique(array_filter(array_map('absint', $posted))));
    }

    /* ---- Show form ---- */

    if (empty($selected_person_ids)) {
        $pdf_creator->show_page_header($type_of_pdf . ' erstellen');
        ?>
        <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> erstellen</h1>
        <h3 class="mb-1"><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></h3>
        <p><strong>Event-UID:</strong> <?php echo esc_html($event_uid); ?></p>

        <form method="post" action="" class="mb-4" id="pdf-person-form">

            <div class="row g-3 mb-4">

                <div class="col-md-6">
                    <label for="pky_name_label_id" class="form-label ">Etiketten-Typ</label>
                    <select name="pky_name_label_id" id="pky_name_label_id" class="form-select">
                        <?php foreach ($label_types as $lt) : ?>
                            <option value="<?php echo esc_attr($lt['id']); ?>">
                                <?php echo esc_html($lt['etk_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="fontsize_1" class="form-label ">Textgrösse</label>
                    <select name="fontsize_1" id="fontsize_1" class="form-select">
                        <?php
                        for ($s = 6.0; $s <= 24.0; $s += 0.5) {
                            $val      = number_format($s, 1, '.', '');
                            $label    = (fmod($s, 1.0) === 0.0) ? (int) $s . ' pt' : $val . ' pt';
                            $selected = ($s == 12.0) ? ' selected' : '';
                            echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($label) . '</option>' . "\n";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="logo_width_mm" class="form-label ">Logo-Breite (mm)</label>
                    <select name="logo_width_mm" id="logo_width_mm" class="form-select">
                        <?php
                        $first_lt          = reset($label_types);
                        $default_logo_mm   = max(5, min(80, (int) (round((float) $first_lt['etk_width'] * 2 / 3 / 5) * 5)));
                        for ($mm = 5; $mm <= 80; $mm += 5) {
                            $sel = ($mm === $default_logo_mm) ? ' selected' : '';
                            echo '<option value="' . esc_attr($mm) . '"' . $sel . '>' . esc_html($mm) . ' mm</option>' . "\n";
                        }
                        ?>
                    </select>
                </div>

            </div>

            <style>
                .etk-opts { display:flex; flex-wrap:wrap; gap:.75rem 3rem; }
                .etk-opt { display:flex; align-items:center; flex:0 0 22rem; }
                .etk-opt__label { flex:0 0 12rem; font-size:1rem; font-weight:400; }
                .etk-opt__choices { display:flex; gap:1.5rem; }
                .etk-opt .form-check { display:flex; align-items:center; gap:.4rem; margin:0; padding:0; min-height:auto; }
                .etk-opt .form-check-input { float:none; margin:0; }
                .etk-opt .form-check-label { line-height:1; }
            </style>

            <div class="etk-opts mb-4">
                <?php
                /* field => default value (1=ja, 0=nein) */
                $radio_fields = [
                    'print_logo'        => ['label' => 'Logo drucken',             'default' => 1],
                    'print_akad_title'  => ['label' => 'Akad. Titel drucken',      'default' => 1],
                    'print_job_title'   => ['label' => 'Funktion drucken',          'default' => 1],
                    'print_institution' => ['label' => 'Institution drucken',       'default' => 1],
                    'print_work_shops'  => ['label' => 'Workshops drucken',         'default' => 0],
                    'print_border'      => ['label' => 'Rand der Etikette zeigen',  'default' => 0],
                ];
                foreach ($radio_fields as $field_name => $field_def) :
                    $default_val = $field_def['default'];
                ?>
                <div class="etk-opt">
                    <div class="etk-opt__label"><?php echo esc_html($field_def['label']); ?></div>
                    <div class="etk-opt__choices">
                        <div class="form-check">
                            <input class="form-check-input" type="radio"
                                name="<?php echo esc_attr($field_name); ?>"
                                id="<?php echo esc_attr($field_name); ?>_ja"
                                value="1"<?php echo $default_val === 1 ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="<?php echo esc_attr($field_name); ?>_ja">ja</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio"
                                name="<?php echo esc_attr($field_name); ?>"
                                id="<?php echo esc_attr($field_name); ?>_nein"
                                value="0"<?php echo $default_val === 0 ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="<?php echo esc_attr($field_name); ?>_nein">nein</label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-3">
                <p class="form-label fw-semibold h4 mb-0">Teilnehmende auswählen</p>
                <p class="form-text">Klick auf einen Eintrag verschiebt ihn. PDF wird nur für ausgewählte Teilnehmende erstellt.</p>
            </div>

            <style>
                .dlb-wrap { display:flex; gap:1rem; align-items:flex-start; }
                .dlb-col { flex:1; min-width:0; }
                .dlb-col label { display:block; font-weight:600; margin-bottom:.35rem; }
                .dlb-filter { width:100%; margin-bottom:.4rem; }
                .dlb-list { height:320px; overflow-y:auto; border:1px solid #dee2e6; border-radius:.375rem; padding:.25rem; }
                .dlb-list .list-group-item { cursor:pointer; border:none; }
                .dlb-list .list-group-item:hover { background:rgba(255,255,255,.25); }
                .dlb-list .list-group-item { padding:.35rem .75rem; font-size:.9rem; user-select:none; border-radius:.375rem !important; }
                .dlb-list .list-group-item.d-none { display:none !important; }
                .dlb-actions { display:flex; flex-direction:column; gap:.5rem; justify-content:center; padding-top:2rem; }
            </style>

            <div class="dlb-wrap mb-3">
                <div class="dlb-col">
                    <label>Verfügbar (<span id="dlb-avail-count">0</span>)</label>
                    <input type="text" class="form-control form-control-sm dlb-filter" id="dlb-filter-avail" placeholder="Filtern…">
                    <ul class="list-group dlb-list" id="dlb-avail"></ul>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2 rounded-pill" id="dlb-add-all">Alle hinzufügen</button>
                </div>

                <div class="dlb-actions">
                    <span class="text-muted">→</span>
                    <span class="text-muted">←</span>
                </div>

                <div class="dlb-col">
                    <label>Ausgewählt (<span id="dlb-sel-count">0</span>)</label>
                    <input type="text" class="form-control form-control-sm dlb-filter" id="dlb-filter-sel" placeholder="Filtern…">
                    <ul class="list-group dlb-list" id="dlb-sel"></ul>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2 rounded-pill" id="dlb-remove-all">Alle entfernen</button>
                </div>
            </div>

            <!-- hidden select synced before submit -->
            <select name="selected_person_ids[]" id="dlb-hidden-select" multiple style="display:none">
                <?php foreach ($persons as $person) : ?>
                    <?php $person_id = $pdf_creator->get_person_id($person); ?>
                    <?php if ($person_id !== '') : ?>
                        <option value="<?php echo esc_attr($person_id); ?>">
                            <?php echo esc_html($pdf_creator->person_label($person)); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary rounded-pill" id="dlb-submit"><?php echo esc_html($type_of_pdf); ?> generieren</button>
        </form>

        <script>
        (function () {
            var persons = <?php
                $dlb_persons = [];
                foreach ($persons as $person) {
                    $pid = $pdf_creator->get_person_id($person);
                    if ($pid !== '') {
                        $dlb_persons[] = ['id' => $pid, 'label' => $pdf_creator->person_label($person)];
                    }
                }
                echo wp_json_encode($dlb_persons);
            ?>;

            var availList  = document.getElementById('dlb-avail');
            var selList    = document.getElementById('dlb-sel');
            var availCount = document.getElementById('dlb-avail-count');
            var selCount   = document.getElementById('dlb-sel-count');
            var filterAvail = document.getElementById('dlb-filter-avail');
            var filterSel   = document.getElementById('dlb-filter-sel');
            var hiddenSel   = document.getElementById('dlb-hidden-select');
            var submitBtn   = document.getElementById('dlb-submit');
            var form        = document.getElementById('pdf-person-form');

            function esc(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function makeItem(p, side) {
                var li = document.createElement('li');
                li.className = 'list-group-item';
                li.dataset.id = p.id;
                li.dataset.label = p.label.toLowerCase();
                li.textContent = p.label;
                li.addEventListener('click', function () { move(li, side); });
                return li;
            }

            function move(li, fromSide) {
                if (fromSide === 'avail') {
                    selList.appendChild(li);
                    li.removeEventListener('click', li._clickHandler);
                    li.addEventListener('click', function () { move(li, 'sel'); });
                } else {
                    availList.appendChild(li);
                    li.removeEventListener('click', li._clickHandler);
                    li.addEventListener('click', function () { move(li, 'avail'); });
                }
                applyFilter(filterAvail, availList);
                applyFilter(filterSel, selList);
                updateCounts();
            }

            function applyFilter(input, list) {
                var q = input.value.toLowerCase();
                list.querySelectorAll('.list-group-item').forEach(function (li) {
                    li.classList.toggle('d-none', q !== '' && li.dataset.label.indexOf(q) === -1);
                });
            }

            function updateCounts() {
                availCount.textContent = availList.querySelectorAll('.list-group-item').length;
                selCount.textContent   = selList.querySelectorAll('.list-group-item').length;
                submitBtn.disabled     = selList.querySelectorAll('.list-group-item').length === 0;
            }

            // Populate available list
            persons.forEach(function (p) {
                availList.appendChild(makeItem(p, 'avail'));
            });
            updateCounts();

            filterAvail.addEventListener('input', function () { applyFilter(filterAvail, availList); });
            filterSel.addEventListener('input',   function () { applyFilter(filterSel,   selList);   });

            document.getElementById('dlb-add-all').addEventListener('click', function () {
                availList.querySelectorAll('.list-group-item:not(.d-none)').forEach(function (li) {
                    selList.appendChild(li);
                    li.onclick = function () { move(li, 'sel'); };
                });
                applyFilter(filterSel, selList);
                updateCounts();
            });

            document.getElementById('dlb-remove-all').addEventListener('click', function () {
                selList.querySelectorAll('.list-group-item:not(.d-none)').forEach(function (li) {
                    availList.appendChild(li);
                    li.onclick = function () { move(li, 'avail'); };
                });
                applyFilter(filterAvail, availList);
                updateCounts();
            });

            form.addEventListener('submit', function () {
                // Sync hidden select
                Array.from(hiddenSel.options).forEach(function (o) { o.selected = false; });
                selList.querySelectorAll('.list-group-item').forEach(function (li) {
                    var opt = hiddenSel.querySelector('option[value="' + li.dataset.id + '"]');
                    if (opt) opt.selected = true;
                });
            });
        }());
        </script>
        <?php
        $pdf_creator->show_page_footer();
        return;
    }

    /* ---- Resolve selected persons ---- */

    $selected_person_id_map = array_flip(array_map('strval', $selected_person_ids));
    $selected_persons = [];

    foreach ($persons as $person) {
        $pid = $pdf_creator->get_person_id($person);
        if ($pid !== '' && isset($selected_person_id_map[(string) absint($pid)])) {
            $selected_persons[] = $person;
        }
    }

    if (empty($selected_persons)) {
        throw new RuntimeException('Keine gültigen Teilnehmenden ausgewählt.');
    }

    /* ---- Options from POST ---- */

    $label_type_id = (int) ($_POST['pky_name_label_id'] ?? 0);
    $fontsize      = max(6.0, min(24.0, (float) ($_POST['fontsize_1'] ?? 12.0)));
    $print_logo    = ((int) ($_POST['print_logo'] ?? 1)) === 1;
    $print_akad    = ((int) ($_POST['print_akad_title'] ?? 1)) === 1;
    $print_job     = ((int) ($_POST['print_job_title'] ?? 1)) === 1;
    $print_inst    = ((int) ($_POST['print_institution'] ?? 1)) === 1;
    $print_ws      = ((int) ($_POST['print_work_shops'] ?? 1)) === 1;
    $print_border    = ((int) ($_POST['print_border'] ?? 0)) === 1;
    $logo_width_mm_raw = isset($_POST['logo_width_mm']) ? (int) $_POST['logo_width_mm'] : null;

    if (!isset($label_types_by_id[$label_type_id])) {
        throw new RuntimeException('Ungültiger Etiketten-Typ: ' . $label_type_id);
    }

    $size_def = $label_types_by_id[$label_type_id];
    $etk_w    = (float) $size_def['etk_width'];
    $etk_h    = (float) $size_def['etk_height'];
    $default_logo_mm = max(5, min(80, (int) (round($etk_w * 2 / 3 / 5) * 5)));
    $logo_width_mm   = max(5, min(80, $logo_width_mm_raw ?? $default_logo_mm));
    $margin_l = (float) $size_def['page_margin_left'];
    $margin_t = (float) $size_def['page_margin_top'];
    $gap_h    = (float) $size_def['horizontal_distance_between_etk'];
    $gap_v    = (float) $size_def['vertical_distance_between_etk'];

    /* Grid: how many labels fit per A4 page (210 × 297 mm) */
    $n_cols          = max(1, (int) floor((210 - $margin_l + $gap_h) / ($etk_w + $gap_h)));
    $n_rows          = max(1, (int) floor((297 - $margin_t + $gap_v) / ($etk_h + $gap_v)));
    $labels_per_page = $n_cols * $n_rows;

    /* ---- Sort alphabetically: last name, first name ---- */

    usort($selected_persons, function ($a, $b) use ($pdf_creator) {
        $cmp = strcmp(
            strtolower($pdf_creator->value_ci($a, 'str_last_name')),
            strtolower($pdf_creator->value_ci($b, 'str_last_name'))
        );
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp(
            strtolower($pdf_creator->value_ci($a, 'str_first_name')),
            strtolower($pdf_creator->value_ci($b, 'str_first_name'))
        );
    });

    /* ---- Load layout (for logo image) ---- */

    $layout             = $pdf_creator->load_pdf_layout('etiketten.php', $event_uid);
    $image_replacements = $pdf_creator->get_image_replacements($layout);
    $docraptor          = $pdf_creator->create_docraptor_client();

    $logo_data_uri = $image_replacements['{logo_data_uri}'] ?? '';
    $logo_html     = ($print_logo && $logo_data_uri !== '')
        ? '<div class="label-logo"><img src="' . $logo_data_uri . '" alt="Logo" style="width:' . $logo_width_mm . 'mm;height:auto;"></div>'
        : '';

    /* ---- Build label grid HTML ---- */

    $pages_html  = '';
    $page_labels = '';
    $idx         = 0;

    foreach ($selected_persons as $person) {
        $person_id   = $pdf_creator->get_person_id($person);
        $first_name  = trim($pdf_creator->value_ci($person, 'str_first_name'));
        $last_name   = trim($pdf_creator->value_ci($person, 'str_last_name'));
        $akad_title  = $print_akad ? trim($pdf_creator->value_ci($person, 'str_academic_title')) : '';
        $job_title   = $print_job  ? trim($pdf_creator->value_ci($person, 'str_job_title')) : '';
        $institution = $print_inst ? trim($pdf_creator->value_ci($person, 'str_institution')) : '';

        $name_parts = array_filter([$akad_title, $first_name, $last_name]);
        $name_line  = esc_html(implode(' ', $name_parts));

        $job_title_line   = $job_title   ? '<div class="label-jobtitle">'   . esc_html($job_title)   . '</div>' : '';
        $institution_line = $institution ? '<div class="label-institution">' . esc_html($institution) . '</div>' : '';

        /* Workshops */
        $workshops_block = '';
        if ($print_ws && $person_id !== '') {
            $ws_rows = (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ws.str_workshop_number, ws.str_workshop_title_de
                       FROM {$wpdb->prefix}evtmgr_registrations_workshops AS rw
                      INNER JOIN {$wpdb->prefix}evtmgr_workshops AS ws
                          ON ws.id = rw.fky_workshop_id
                      INNER JOIN {$wpdb->prefix}evtmgr_timezones AS tz
                          ON tz.id = ws.fky_timezone_id
                      WHERE rw.fky_person_id = %d
                        AND ws.fky_event_uid = %s
                        AND ws.ysn_no_registration_possible = 0
                      ORDER BY tz.int_sort_order, ws.str_workshop_number",
                    absint($person_id),
                    $event_uid
                ),
                ARRAY_A
            );

            if (!empty($ws_rows)) {
                $ws_lines = [];
                foreach ($ws_rows as $ws_row) {
                    $ws_row     = array_change_key_case((array) $ws_row, CASE_LOWER);
                    $ws_lines[] = esc_html(trim(
                        ($ws_row['str_workshop_number'] ?? '') . ' ' .
                        ($ws_row['str_workshop_title_de'] ?? '')
                    ));
                }
                $workshops_block = '<div class="label-workshops">' . implode('<br>', $ws_lines) . '</div>';
            }
        }

        /* Absolute position on the current page */
        $pos_in_page = $idx % $labels_per_page;
        $col  = $pos_in_page % $n_cols;
        $row  = (int) floor($pos_in_page / $n_cols);
        $left = number_format($margin_l + $col * ($etk_w + $gap_h), 2, '.', '');
        $top  = number_format($margin_t + $row * ($etk_h + $gap_v), 2, '.', '');
        $w    = number_format($etk_w, 2, '.', '');
        $h    = number_format($etk_h, 2, '.', '');

        $border_style = $print_border ? 'border:0.3mm solid #000;' : '';

        $page_labels .=
            '<div class="label" style="left:' . $left . 'mm;top:' . $top . 'mm;width:' . $w . 'mm;height:' . $h . 'mm;' . $border_style . '">' .
            $logo_html .
            '<div class="label-name">' . $name_line . '</div>' .
            $job_title_line .
            $institution_line .
            $workshops_block .
            '</div>';

        $idx++;

        /* Flush full page */
        if ($idx % $labels_per_page === 0) {
            $pages_html .= '<div class="page">' . $page_labels . '</div>';
            $page_labels = '';
        }
    }

    /* Final partial page */
    if ($page_labels !== '') {
        $pages_html .= '<div class="page">' . $page_labels . '</div>';
    }

    /* ---- Generate PDF ---- */

    $replacements = [
        '{fontsize}'   => number_format($fontsize, 1, '.', '') . 'pt',
        '{pages_html}' => $pages_html,
    ];

    $html = $pdf_creator->render_html((string) $layout['html_template'], $replacements);

    $file_name = 'etiketten_' . sanitize_file_name($event_uid) . '.pdf';

    $doc = new DocRaptor\Doc();
    $doc->setTest(Evtmgr_Options::is_pdf_test_mode($event_uid));
    $doc->setDocumentType('pdf');
    $doc->setName($file_name);
    $doc->setDocumentContent($html);

    $pdf      = $docraptor->createDoc($doc);
    $pdf_path = $pdf_creator->get_pdf_path($subfolder_for_pdf, $event_uid);
    $pdf_creator->ensure_directory($pdf_path);
    file_put_contents(rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name, $pdf);

    $file_url      = $pdf_creator->get_pdf_url($subfolder_for_pdf, $event_uid, $file_name);
    $n_pages       = (int) ceil(count($selected_persons) / $labels_per_page);

    $pdf_creator->show_page_header($type_of_pdf . ' erstellt');
    ?>
    <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> erstellt</h1>
    <p><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></p>

    <div class="alert alert-success">
        <?php echo esc_html(count($selected_persons)); ?> Etiketten generiert
        (<?php echo esc_html($n_cols); ?> × <?php echo esc_html($n_rows); ?> pro Seite,
        <?php echo esc_html($n_pages); ?> Seite<?php echo $n_pages !== 1 ? 'n' : ''; ?>).
    </div>

    <p>
        <a href="<?php echo esc_url($file_url); ?>" class="btn btn-primary rounded-pill" target="_blank" rel="noopener">
            PDF herunterladen
        </a>
    </p>

    <a href="<?php echo esc_url(remove_query_arg([])); ?>" class="btn btn-secondary mt-2 rounded-pill">
        Neue Etiketten erstellen
    </a>
    <?php
    $pdf_creator->show_page_footer();

} catch (Throwable $e) {
    if (!isset($pdf_creator) || !$pdf_creator instanceof Event_Registration_Pdf_Creation) {
        $pdf_creator = new Event_Registration_Pdf_Creation(__DIR__);
    }
    $pdf_creator->show_page_header('Fehler');
    ?>
    <h1 class="mb-3">Fehler</h1>
    <div class="alert alert-danger">
        <?php echo esc_html($e->getMessage()); ?>
    </div>
    <?php
    $pdf_creator->show_page_footer();
}
