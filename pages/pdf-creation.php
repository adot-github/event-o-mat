<?php

$wp_load = dirname(__FILE__, 7) . '/wp-load.php';

if (!file_exists($wp_load)) {
    die('wp-load.php not found: ' . htmlspecialchars($wp_load, ENT_QUOTES, 'UTF-8'));
}

require_once $wp_load;

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-event-registration.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-persons.php';
require_once dirname(__DIR__) . '/classes/class-evtmgr-events.php';
require_once dirname(__DIR__) . '/classes/class-pdf-creation.php';

$type_of_pdf       = isset($type_of_pdf) && trim((string) $type_of_pdf) !== '' ? (string) $type_of_pdf : 'PDFs';
$type_of_pdf_sing  = isset($type_of_pdf_sing) && trim((string) $type_of_pdf_sing) !== '' ? (string) $type_of_pdf_sing : 'PDF';
$pdf_layout        = isset($pdf_layout) && trim((string) $pdf_layout) !== '' ? (string) $pdf_layout : '';
$file_name_field   = isset($file_name_field) && trim((string) $file_name_field) !== '' ? (string) $file_name_field : '';
$subfolder_for_pdf = isset($subfolder_for_pdf) && trim((string) $subfolder_for_pdf) !== '' ? sanitize_file_name((string) $subfolder_for_pdf) : 'diplomas';

if ($pdf_layout === '') {
    wp_die('Kein PDF-Layout definiert.');
}

if ($file_name_field === '') {
    wp_die('Kein Dateinamen-Feld definiert.');
}

if ($subfolder_for_pdf === '') {
    wp_die('Kein PDF-Unterordner definiert.');
}

try {
    $pdf_creator = new Event_Registration_Pdf_Creation(__DIR__);
    $layout      = $pdf_creator->load_pdf_layout($pdf_layout);

    $persons_obj = new class_evtmgr_persons();
    $event_registration = new Event_Registration_Context();
    $event_uid = $event_registration->get_cookie_event_uid(true);

    if (isset($before_pdf_creation_callback) && is_callable($before_pdf_creation_callback)) {
        call_user_func($before_pdf_creation_callback, $event_uid);
    }

    if (class_exists('Evtmgr_Events')) {
        $event_obj = new Evtmgr_Events();
    } elseif (class_exists('Evtmgr_Events')) {
        $event_obj = new Evtmgr_Events();
    } else {
        throw new RuntimeException('event class not found.');
    }

    $event = $event_obj->get_events_by_event_uid($event_uid, 'de');

    if (empty($event)) {
        throw new RuntimeException('Kein Kongress für Event Uid gefunden: ' . $event_uid);
    }

    $str_event_name_ = $event['str_event_name'] ?? $event['str_event_name_de'] ?? '';
    $event_date      = $event['dtm_event_date'] ?? '';
    $dtm_event_date    = $pdf_creator->format_date((string) $event_date);

    $persons = $persons_obj->get_persons_registered($event_uid);

    if (empty($persons)) {
        throw new RuntimeException('Keine registrierten Personen gefunden.');
    }

    $pdf_path = $pdf_creator->get_pdf_path($subfolder_for_pdf, $event_uid);

    $existing_pdf_files   = $pdf_creator->get_existing_pdf_files($pdf_path, $event_uid, $subfolder_for_pdf);
    $persons_without_file = $pdf_creator->get_persons_without_file($persons, $pdf_path, $file_name_field);
    $zip_download_data    = $pdf_creator->get_zip_download_data($pdf_path, $existing_pdf_files, $type_of_pdf, $event_uid, $subfolder_for_pdf);

    $selected_person_ids = array();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_person_ids'])) {
        $posted_person_ids = wp_unslash($_POST['selected_person_ids']);

        if (!is_array($posted_person_ids)) {
            $posted_person_ids = array($posted_person_ids);
        }

        $selected_person_ids = array_values(array_unique(array_filter(array_map('absint', $posted_person_ids))));
    }

    if (empty($selected_person_ids)) {
        $pdf_creator->show_page_header($type_of_pdf . ' für Teilnehmende');
        ?>
        <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> für Teilnehmende</h1>
        <h3 class="mb-1"><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></h3>
        <p><strong>Event Uid:</strong> <?php echo esc_html($event_uid); ?></p>

        <form method="post" action="" class="mb-4">
            <div class="pdf-person-select-wrap">
                <label for="selected_person_ids" class="form-label fw-semibold h4">Teilnehmende auswählen</label>
                <p class="form-text">Mehrfachauswahl mit Ctrl/Cmd oder Shift. Alle auswählen mit Ctrl+A.<br>
                Es werden nur für die ausgewählten Teilnehmenden PDF erstellt.</p>

                <select id="selected_person_ids" name="selected_person_ids[]" class="form-select pdf-person-select" multiple required>
                    <?php foreach ($persons as $person) : ?>
                        <?php $person_id = $pdf_creator->get_person_id($person); ?>
                        <?php if ($person_id !== '') : ?>
                            <option value="<?php echo esc_attr($person_id); ?>">
                                <?php echo esc_html($pdf_creator->person_label($person)); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary mt-3 rounded-pill"><?php echo esc_html($type_of_pdf); ?> erstellen</button>
        </form>

        <?php
        $pdf_creator->render_status_accordion(
            $existing_pdf_files,
            $persons_without_file,
            $type_of_pdf,
            $type_of_pdf_sing,
            $zip_download_data
        );
        $pdf_creator->show_page_footer();
        return;
    }

    $selected_person_id_map = array_flip(array_map('strval', $selected_person_ids));
    $selected_persons = array();

    foreach ($persons as $person) {
        $person_id = $pdf_creator->get_person_id($person);

        if ($person_id !== '' && isset($selected_person_id_map[(string) absint($person_id)])) {
            $selected_persons[] = $person;
        }
    }

    if (empty($selected_persons)) {
        throw new RuntimeException('Keine gültigen Teilnehmenden ausgewählt.');
    }

    /* ---- async AJAX generation job ---- */
    $job_id = wp_generate_uuid4();
    set_transient('evtmgr_pdf_job_' . $job_id, [
        'pdf_layout'       => $pdf_layout,
        'file_name_field'  => $file_name_field,
        'subfolder'        => $subfolder_for_pdf,
        'type_of_pdf'      => $type_of_pdf,
        'type_of_pdf_sing' => $type_of_pdf_sing,
        'event_uid'        => $event_uid,
        'event'            => $event,
        'str_event_name_'  => $str_event_name_,
        'dtm_event_date'   => $dtm_event_date,
        'persons'          => array_values($selected_persons),
    ], HOUR_IN_SECONDS);

    $total = count($selected_persons);

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
        <a href="<?php echo esc_url(remove_query_arg(array())); ?>" class="btn btn-secondary rounded-pill">
            Weitere <?php echo esc_html($type_of_pdf); ?> erstellen
        </a>
    </div>

    <script>
    (function () {
        var ajaxUrl   = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var nonce     = <?php echo wp_json_encode(wp_create_nonce('evtmgr_pdf_generate')); ?>;
        var jobId     = <?php echo wp_json_encode($job_id); ?>;
        var total     = <?php echo (int) $total; ?>;

        var bar       = document.getElementById('evtmgr-pdf-bar');
        var statusEl  = document.getElementById('evtmgr-pdf-status');
        var logEl     = document.getElementById('evtmgr-pdf-log');
        var doneEl    = document.getElementById('evtmgr-pdf-done');
        var summaryEl = document.getElementById('evtmgr-pdf-summary');
        var filesEl   = document.getElementById('evtmgr-pdf-files');
        var generated = [];

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
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function finish() {
            bar.classList.remove('progress-bar-animated');
            setProgress(total);
            summaryEl.textContent = generated.length + ' Datei(en) erstellt.';
            generated.forEach(function (f) {
                var li = document.createElement('li');
                li.className = 'list-group-item';
                li.innerHTML = esc(f.person) + ' — <span class="text-break"><a href="' + esc(f.file_url) + '" target="_blank" rel="noopener">' + esc(f.file_name) + '</a></span>';
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
                    action:     'evtmgr_pdf_generate_person',
                    nonce:      nonce,
                    job_id:     jobId,
                    person_idx: String(idx)
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.data) {
                    generated.push(d.data);
                    addLog(d.data.person, true);
                } else {
                    addLog((d.data && d.data.message) || ('Fehler bei Person ' + idx), false);
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
    return;
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
