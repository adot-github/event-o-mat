<?php
/**
 * Workshop PDF creation page.
 * Creates one PDF per selected workshop and lists existing/missing PDFs.
 */

if (!defined('ABSPATH')) {
    $dir = __DIR__;
    $wp_load = '';

    for ($i = 0; $i < 10; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';

        if (file_exists($candidate)) {
            $wp_load = $candidate;
            break;
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    if ($wp_load === '') {
        die('<div style="padding:20px;font-family:Arial,sans-serif;color:#b00020;"><strong>wp-load.php wurde nicht gefunden. Startpfad: ' . htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8') . '</strong></div>');
    }

    require_once $wp_load;
}

if (!defined('ABSPATH')) {
    die('WordPress konnte nicht geladen werden.');
}

$required_files = array(
    dirname(__DIR__) . '/classes/class-event-registration.php',
    dirname(__DIR__) . '/classes/class-evtmgr-events.php',
    dirname(__DIR__) . '/classes/class-evtmgr-workshops.php',
    dirname(__DIR__) . '/classes/class-pdf-creation.php',
);

foreach ($required_files as $required_file) {
    if (!file_exists($required_file)) {
        wp_die('Benötigte Datei wurde nicht gefunden: ' . esc_html($required_file));
    }

    require_once $required_file;
}

$type_of_pdf       = isset($type_of_pdf) && trim((string) $type_of_pdf) !== '' ? (string) $type_of_pdf : 'Workshop-PDFs';
$type_of_pdf_sing  = isset($type_of_pdf_sing) && trim((string) $type_of_pdf_sing) !== '' ? (string) $type_of_pdf_sing : 'Workshop-PDF';
$pdf_layout        = isset($pdf_layout) && trim((string) $pdf_layout) !== '' ? (string) $pdf_layout : '';
$subfolder_for_pdf = isset($subfolder_for_pdf) && trim((string) $subfolder_for_pdf) !== '' ? sanitize_file_name((string) $subfolder_for_pdf) : 'workshop-booking-lists';

if ($pdf_layout === '') {
    wp_die('Kein PDF-Layout definiert.');
}

try {
    $pdf_creator = new Event_Registration_Pdf_Creation(__DIR__);
    $layout = $pdf_creator->load_pdf_layout($pdf_layout);

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

    $workshops_obj = new Evtmgr_Workshops();

    $event = $event_obj->get_events_by_event_uid($event_uid, 'de');

    if (empty($event)) {
        throw new RuntimeException('Kein Kongress für Event Uid gefunden: ' . $event_uid);
    }

    $str_event_name_ = $event['str_event_name_'] ?? $event['str_event_name_de'] ?? '';
    $str_event_subtitle = $event['str_event_subtitle'] ?? $event['str_event_subtitle_de'] ?? '';
    $dtm_event_date = $pdf_creator->format_date((string) ($event['dtm_event_date'] ?? ''));

    $workshops = $workshops_obj->get_workshops_for_pdf_list($event_uid);

    if (empty($workshops)) {
        throw new RuntimeException('Keine Workshops mit ysn_no_registration_possible = 0 gefunden.');
    }

    $pdf_path = $pdf_creator->get_pdf_path($subfolder_for_pdf, $event_uid);
    $existing_pdf_files = $pdf_creator->get_existing_pdf_files($pdf_path, $event_uid, $subfolder_for_pdf);
    $workshops_without_pdf = $workshops_obj->get_workshops_without_pdf($workshops, $pdf_path);
    $zip_data = $pdf_creator->get_zip_download_data($pdf_path, $existing_pdf_files, $type_of_pdf, $event_uid, $subfolder_for_pdf);

    $selected_workshop_ids = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_workshop_ids'])) {
        $posted_ids = wp_unslash($_POST['selected_workshop_ids']);
        $posted_ids = is_array($posted_ids) ? $posted_ids : array($posted_ids);
        $selected_workshop_ids = array_values(array_unique(array_filter(array_map('absint', $posted_ids))));
    }

    $workshop_label_callback = array($workshops_obj, 'workshop_pdf_label');

    if (empty($selected_workshop_ids)) {
        $pdf_creator->show_page_header($type_of_pdf . ' für Workshops');
        ?>
        <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> für Workshops</h1>
        <h3 class="mb-1"><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></h3>
        <p><strong>Event Uid:</strong> <?php echo esc_html($event_uid); ?></p>

        <form method="post" action="" class="mb-4">
            <div class="pdf-person-select-wrap">
                <label for="selected_workshop_ids" class="form-label fw-semibold h4">Workshops auswählen</label>
                <p class="form-text">Mehrfachauswahl mit Ctrl/Cmd oder Shift. Alle auswählen mit Ctrl+A.<br>Es werden nur für die ausgewählten Workshops PDF erstellt.</p>

                <select id="selected_workshop_ids" name="selected_workshop_ids[]" class="form-select pdf-person-select" multiple required>
                    <?php foreach ($workshops as $workshop) : ?>
                        <?php $workshop_id = absint($workshop['id'] ?? 0); ?>
                        <?php if ($workshop_id > 0) : ?>
                            <option value="<?php echo esc_attr($workshop_id); ?>"><?php echo esc_html($workshops_obj->workshop_pdf_label($workshop)); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><?php echo esc_html($type_of_pdf); ?> erstellen</button>
        </form>

        <?php
        $pdf_creator->render_status_accordion(
            $existing_pdf_files,
            $workshops_without_pdf,
            $type_of_pdf,
            $type_of_pdf_sing,
            $zip_data,
            $workshop_label_callback,
            'Workshops ohne'
        );
        $pdf_creator->show_page_footer();
        return;
    }

    $selected_map = array_flip(array_map('strval', $selected_workshop_ids));
    $selected_workshops = array();
    foreach ($workshops as $workshop) {
        $workshop_id = absint($workshop['id'] ?? 0);
        if ($workshop_id > 0 && isset($selected_map[(string) $workshop_id])) {
            $selected_workshops[] = $workshop;
        }
    }

    if (empty($selected_workshops)) {
        throw new RuntimeException('Keine gültigen Workshops ausgewählt.');
    }

    $pdf_creator->ensure_directory($pdf_path);

    $image_replacements = $pdf_creator->get_image_replacements($layout);
    $text_replacements = $pdf_creator->text_replacements($layout, 'de');

    $docraptor = $pdf_creator->create_docraptor_client();

    $generated_files = array();
    foreach ($selected_workshops as $workshop) {
        $workshop_id = absint($workshop['id'] ?? 0);
        $file_name = $workshops_obj->workshop_pdf_file_name($workshop);

        $participants = $workshops_obj->get_workshop_registered_persons($workshop_id, $event_uid);
        $presenters_text = $workshops_obj->get_workshop_presenters_text($workshop_id);

        $core_replacements = array(
            '{str_language}'           => 'de',
            '{str_event_name}'       => esc_html($str_event_name_),
            '{str_event_subtitle}'   => esc_html($str_event_subtitle),
            '{str_event_subtitle_de}' => esc_html($str_event_subtitle),
            '{dtm_event_date}'          => esc_html($dtm_event_date),
            '{id}'         => esc_html((string) $workshop_id),
            '{str_workshop_number}'     => esc_html($workshops_obj->workshop_value_ci($workshop, 'str_workshop_number')),
            '{str_workshop_title_de}'    => esc_html($workshops_obj->workshop_value_ci($workshop, 'str_workshop_title_de')),
            '{invoice_text}'          => $pdf_creator->render_workshop_participants_html($workshop, $participants, $presenters_text, $workshop_label_callback),
        );

        $all_replacements = array_merge($image_replacements, $text_replacements, $core_replacements);
        $html = $pdf_creator->render_html((string) $layout['html_template'], $all_replacements);
        $html = strtr($html, $all_replacements);

        $doc = new DocRaptor\Doc();
        $doc->setTest(true);
        $doc->setDocumentType('pdf');
        $doc->setName($file_name);
        $doc->setDocumentContent($html);

        $pdf = $docraptor->createDoc($doc);
        $target_path = rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name;
        file_put_contents($target_path, $pdf);

        $generated_files[] = array(
            'file_name' => $file_name,
            'file_url'  => $pdf_creator->get_pdf_url($subfolder_for_pdf, $event_uid, $file_name),
            'workshop'  => $workshops_obj->workshop_pdf_label($workshop),
        );
    }

    $existing_pdf_files = $pdf_creator->get_existing_pdf_files($pdf_path, $event_uid, $subfolder_for_pdf);
    $workshops_without_pdf = $workshops_obj->get_workshops_without_pdf($workshops, $pdf_path);
    $zip_data = $pdf_creator->get_zip_download_data($pdf_path, $existing_pdf_files, $type_of_pdf, $event_uid, $subfolder_for_pdf);

    $pdf_creator->show_page_header($type_of_pdf . ' erstellt');
    ?>
    <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> erstellt</h1>
    <p><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></p>

    <div class="alert alert-success"><?php echo esc_html(count($generated_files)); ?> Datei(en) wurden erstellt.</div>

    <ul class="list-group mb-4">
        <?php foreach ($generated_files as $file) : ?>
            <li class="list-group-item">
                <?php echo esc_html($file['workshop']); ?> —
                <a href="<?php echo esc_url($file['file_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($file['file_name']); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php
    $pdf_creator->render_status_accordion(
        $existing_pdf_files,
        $workshops_without_pdf,
        $type_of_pdf,
        $type_of_pdf_sing,
        $zip_data,
        $workshop_label_callback,
        'Workshops ohne'
    );
    ?>

    <a href="<?php echo esc_url(remove_query_arg(array())); ?>" class="btn btn-secondary mt-4">Weitere <?php echo esc_html($type_of_pdf); ?> erstellen</a>
    <?php
    $pdf_creator->show_page_footer();
} catch (Throwable $e) {
    if (!isset($pdf_creator) || !$pdf_creator instanceof Event_Registration_Pdf_Creation) {
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
