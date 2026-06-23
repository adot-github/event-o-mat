<?php
/**
 * Workshop PDF creation page.
 * Creates one PDF per selected workshop with async progress bar.
 */

if (!defined('ABSPATH')) {
    $dir = __DIR__;
    $wp_load = '';
    for ($i = 0; $i < 10; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';
        if (file_exists($candidate)) { $wp_load = $candidate; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    if ($wp_load === '') {
        die('<div style="padding:20px;font-family:Arial,sans-serif;color:#b00020;"><strong>wp-load.php nicht gefunden.</strong></div>');
    }
    require_once $wp_load;
}

if (!defined('ABSPATH')) {
    die('WordPress konnte nicht geladen werden.');
}

foreach ([
    dirname(__DIR__) . '/classes/class-event-registration.php',
    dirname(__DIR__) . '/classes/class-evtmgr-events.php',
    dirname(__DIR__) . '/classes/class-evtmgr-workshops.php',
    dirname(__DIR__) . '/classes/class-pdf-creation.php',
    dirname(__DIR__) . '/classes/class-evtmgr-options.php',
] as $f) {
    if (!file_exists($f)) wp_die('Benötigte Datei nicht gefunden: ' . esc_html($f));
    require_once $f;
}

$type_of_pdf       = isset($type_of_pdf)       && trim((string) $type_of_pdf)       !== '' ? (string) $type_of_pdf       : 'Workshop-PDFs';
$type_of_pdf_sing  = isset($type_of_pdf_sing)  && trim((string) $type_of_pdf_sing)  !== '' ? (string) $type_of_pdf_sing  : 'Workshop-PDF';
$pdf_layout        = isset($pdf_layout)        && trim((string) $pdf_layout)        !== '' ? (string) $pdf_layout        : '';
$subfolder_for_pdf = isset($subfolder_for_pdf) && trim((string) $subfolder_for_pdf) !== '' ? sanitize_file_name((string) $subfolder_for_pdf) : 'workshop-booking-lists';

if ($pdf_layout === '') wp_die('Kein PDF-Layout definiert.');

try {
    $pdf_creator     = new Event_Registration_Pdf_Creation(__DIR__);
    $layout          = $pdf_creator->load_pdf_layout($pdf_layout);
    $workshops_obj   = new Evtmgr_Workshops();
    $event_obj       = new Evtmgr_Events();

    $event_registration = new Event_Registration_Context();
    $event_uid = $event_registration->get_cookie_event_uid(true);

    if (isset($before_pdf_creation_callback) && is_callable($before_pdf_creation_callback)) {
        call_user_func($before_pdf_creation_callback, $event_uid);
    }

    $event = $event_obj->get_events_by_event_uid($event_uid, 'de');
    if (empty($event)) throw new RuntimeException('Kein Kongress für Event Uid gefunden: ' . $event_uid);

    $str_event_name_    = $event['str_event_name_']        ?? $event['str_event_name_de'] ?? '';
    $str_event_subtitle = $event['str_event_subtitle']     ?? $event['str_event_subtitle_de'] ?? '';
    $dtm_event_date     = $pdf_creator->format_date((string) ($event['dtm_event_date'] ?? ''));

    $workshops = $workshops_obj->get_workshops_for_pdf_list($event_uid);
    if (empty($workshops)) throw new RuntimeException('Keine Workshops gefunden.');

    $pdf_path           = $pdf_creator->get_pdf_path($subfolder_for_pdf, $event_uid);
    $existing_pdf_files = $pdf_creator->get_existing_pdf_files($pdf_path, $event_uid, $subfolder_for_pdf);
    $workshops_without_pdf = $workshops_obj->get_workshops_without_pdf($workshops, $pdf_path);
    $zip_data           = $pdf_creator->get_zip_download_data($pdf_path, $existing_pdf_files, $type_of_pdf, $event_uid, $subfolder_for_pdf);
    $workshop_label_cb  = [$workshops_obj, 'workshop_pdf_label'];

    /* ---- parse POST selection ---- */
    $selected_workshop_ids = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_workshop_ids'])) {
        $posted = wp_unslash($_POST['selected_workshop_ids']);
        $posted = is_array($posted) ? $posted : [$posted];
        $selected_workshop_ids = array_values(array_unique(array_filter(array_map('absint', $posted))));
    }

    /* ================================================================
       SELECTION SCREEN
    ================================================================ */
    if (empty($selected_workshop_ids)) {
        $pdf_creator->show_page_header($type_of_pdf . ' für Workshops');
        ?>
        <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> für Workshops</h1>
        <h3 class="mb-1"><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></h3>
        <p><strong>Event Uid:</strong> <?php echo esc_html($event_uid); ?></p>

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

        <form method="post" action="" class="mb-4" id="pdf-ws-form">
            <div class="mb-3">
                <p class="form-label fw-semibold h4 mb-0">Workshops auswählen</p>
                <p class="form-text">Klick auf einen Eintrag verschiebt ihn. PDF wird nur für ausgewählte Workshops erstellt.</p>
            </div>

            <div class="dlb-wrap">
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

            <select name="selected_workshop_ids[]" id="dlb-hidden-select" multiple style="display:none">
                <?php foreach ($workshops as $ws) : ?>
                    <?php $wid = absint($ws['id'] ?? 0); ?>
                    <?php if ($wid > 0) : ?>
                        <option value="<?php echo esc_attr($wid); ?>"><?php echo esc_html($workshops_obj->workshop_pdf_label($ws)); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary mt-3 rounded-pill" id="dlb-submit"><?php echo esc_html($type_of_pdf); ?> erstellen</button>
        </form>

        <script>
        (function () {
            var items = <?php
                $dlb_items = [];
                foreach ($workshops as $ws) {
                    $wid = absint($ws['id'] ?? 0);
                    if ($wid > 0) {
                        $dlb_items[] = ['id' => (string) $wid, 'label' => $workshops_obj->workshop_pdf_label($ws)];
                    }
                }
                echo wp_json_encode($dlb_items);
            ?>;

            var availList  = document.getElementById('dlb-avail');
            var selList    = document.getElementById('dlb-sel');
            var availCount = document.getElementById('dlb-avail-count');
            var selCount   = document.getElementById('dlb-sel-count');
            var filterAvail = document.getElementById('dlb-filter-avail');
            var filterSel   = document.getElementById('dlb-filter-sel');
            var hiddenSel   = document.getElementById('dlb-hidden-select');
            var submitBtn   = document.getElementById('dlb-submit');
            var form        = document.getElementById('pdf-ws-form');

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
                    li.onclick = function () { move(li, 'sel'); };
                } else {
                    availList.appendChild(li);
                    li.onclick = function () { move(li, 'avail'); };
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

            items.forEach(function (p) { availList.appendChild(makeItem(p, 'avail')); });
            updateCounts();

            filterAvail.addEventListener('input', function () { applyFilter(filterAvail, availList); });
            filterSel.addEventListener('input',   function () { applyFilter(filterSel,   selList); });

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
                Array.from(hiddenSel.options).forEach(function (o) { o.selected = false; });
                selList.querySelectorAll('.list-group-item').forEach(function (li) {
                    var opt = hiddenSel.querySelector('option[value="' + li.dataset.id + '"]');
                    if (opt) opt.selected = true;
                });
            });
        }());
        </script>

        <?php
        $pdf_creator->render_status_accordion(
            $existing_pdf_files,
            $workshops_without_pdf,
            $type_of_pdf,
            $type_of_pdf_sing,
            $zip_data,
            $workshop_label_cb,
            'Workshops ohne'
        );
        $pdf_creator->show_page_footer();
        return;
    }

    /* ================================================================
       BUILD JOB AND SHOW PROGRESS SCREEN
    ================================================================ */
    $selected_map = array_flip(array_map('strval', $selected_workshop_ids));
    $selected_workshops = [];
    foreach ($workshops as $ws) {
        $wid = absint($ws['id'] ?? 0);
        if ($wid > 0 && isset($selected_map[(string) $wid])) {
            $selected_workshops[] = $ws;
        }
    }

    if (empty($selected_workshops)) throw new RuntimeException('Keine gültigen Workshops ausgewählt.');

    $job_id = wp_generate_uuid4();
    set_transient('evtmgr_pdf_ws_job_' . $job_id, [
        'pdf_layout'         => $pdf_layout,
        'subfolder'          => $subfolder_for_pdf,
        'event_uid'          => $event_uid,
        'event'              => $event,
        'str_event_name_'    => $str_event_name_,
        'str_event_subtitle' => $str_event_subtitle,
        'dtm_event_date'     => $dtm_event_date,
        'workshops'          => array_values($selected_workshops),
    ], HOUR_IN_SECONDS);

    $total = count($selected_workshops);

    $pdf_creator->show_page_header($type_of_pdf . ' wird erstellt');
    ?>
    <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> werden erstellt</h1>
    <p><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></p>

    <div class="progress mb-2" style="height:28px;" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
        <div id="evtmgr-pdf-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0 %</div>
    </div>
    <p id="evtmgr-pdf-status" class="text-muted mb-3">Starte …</p>

    <ul id="evtmgr-pdf-log" class="list-group mb-4" style="max-height:300px;overflow-y:auto;font-size:.9em;"></ul>

    <div id="evtmgr-pdf-done" class="d-none">
        <div class="alert alert-success" id="evtmgr-pdf-summary"></div>
        <ul id="evtmgr-pdf-files" class="list-group mb-4"></ul>
        <a href="<?php echo esc_url(remove_query_arg([])); ?>" class="btn btn-secondary rounded-pill">
            Weitere <?php echo esc_html($type_of_pdf); ?> erstellen
        </a>
    </div>

    <script>
    (function () {
        var ajaxUrl  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var nonce    = <?php echo wp_json_encode(wp_create_nonce('evtmgr_pdf_generate')); ?>;
        var jobId    = <?php echo wp_json_encode($job_id); ?>;
        var total    = <?php echo (int) $total; ?>;

        var bar      = document.getElementById('evtmgr-pdf-bar');
        var statusEl = document.getElementById('evtmgr-pdf-status');
        var logEl    = document.getElementById('evtmgr-pdf-log');
        var doneEl   = document.getElementById('evtmgr-pdf-done');
        var summaryEl= document.getElementById('evtmgr-pdf-summary');
        var filesEl  = document.getElementById('evtmgr-pdf-files');
        var generated= [];

        function setProgress(n) {
            var pct = total > 0 ? Math.round(n / total * 100) : 100;
            bar.style.width = pct + '%';
            bar.textContent = pct + ' %';
            bar.setAttribute('aria-valuenow', String(pct));
            statusEl.textContent = n + ' / ' + total;
        }

        function addLog(text, ok) {
            var li = document.createElement('li');
            li.className = 'list-group-item py-1 ' + (ok ? 'list-group-item-success' : 'list-group-item-danger');
            li.textContent = text;
            logEl.appendChild(li);
            logEl.scrollTop = logEl.scrollHeight;
        }

        function esc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function finish() {
            bar.classList.remove('progress-bar-animated');
            setProgress(total);
            summaryEl.textContent = generated.length + ' Datei(en) erstellt.';
            generated.forEach(function (f) {
                var li = document.createElement('li');
                li.className = 'list-group-item';
                li.innerHTML = esc(f.workshop) + ' — <span class="text-break"><a href="' + esc(f.file_url) + '" target="_blank" rel="noopener">' + esc(f.file_name) + '</a></span>';
                filesEl.appendChild(li);
            });
            doneEl.classList.remove('d-none');
        }

        function next(idx) {
            if (idx >= total) { finish(); return; }
            statusEl.textContent = idx + ' / ' + total + ' …';

            fetch(ajaxUrl, {
                method: 'POST',
                body: new URLSearchParams({
                    action:       'evtmgr_pdf_generate_workshop',
                    nonce:        nonce,
                    job_id:       jobId,
                    workshop_idx: String(idx)
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    generated.push(d.data);
                    addLog(d.data.workshop, true);
                } else {
                    addLog((d.data && d.data.message) || ('Fehler bei Workshop ' + idx), false);
                }
                setProgress(idx + 1);
                next(idx + 1);
            })
            .catch(function (e) {
                addLog('Netzwerkfehler: ' + e.message, false);
                setProgress(idx + 1);
                next(idx + 1);
            });
        }

        next(0);
    }());
    </script>
    <?php
    $pdf_creator->show_page_footer();

} catch (Throwable $e) {
    if (!isset($pdf_creator) || !($pdf_creator instanceof Event_Registration_Pdf_Creation)) {
        if (class_exists('Event_Registration_Pdf_Creation')) {
            $pdf_creator = new Event_Registration_Pdf_Creation(__DIR__);
        } else {
            wp_die(esc_html($e->getMessage()));
        }
    }
    $pdf_creator->show_page_header('Fehler');
    ?>
    <h1 class="mb-3">Fehler</h1>
    <div class="alert alert-danger"><?php echo esc_html($e->getMessage()); ?></div>
    <?php
    $pdf_creator->show_page_footer();
}
