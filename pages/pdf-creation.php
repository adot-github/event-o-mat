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

            <button type="submit" class="btn btn-primary mt-3"><?php echo esc_html($type_of_pdf); ?> erstellen</button>
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

    $pdf_creator->ensure_directory($pdf_path);

    $image_replacements = $pdf_creator->get_image_replacements($layout);
    $docraptor = $pdf_creator->create_docraptor_client();

    $generated_files = array();

    foreach ($selected_persons as $person) {
        $person_id   = $pdf_creator->get_person_id($person);
        $first_name  = $pdf_creator->value_ci($person, 'str_first_name');
        $last_name   = $pdf_creator->value_ci($person, 'str_last_name');
        $person_lang = strtolower(trim($pdf_creator->value_ci($person, 'str_language', 'de')));

        if ($person_lang === '') {
            $person_lang = 'de';
        }

        $file_name_from_person = $pdf_creator->file_name_from_person_field($person, $file_name_field);

        if ($file_name_from_person === '') {
            throw new RuntimeException('Für ' . trim($first_name . ' ' . $last_name) . ' fehlt der Dateiname im Feld ' . $file_name_field . '.');
        }

        $person_event_name = $pdf_creator->event_text_by_language(
            $event,
            'str_event_name',
            $person_lang,
            $str_event_name_
        );

        $person_event_subtitle = $pdf_creator->event_text_by_language(
            $event,
            'str_event_subtitle',
            $person_lang,
            ''
        );

        $text_replacements = $pdf_creator->text_replacements($layout, $person_lang);

        $core_replacements = $pdf_creator->person_replacements($person, array(
            '{str_language}'           => esc_attr($person_lang),
            '{str_event_name}'       => esc_html($person_event_name),
            '{str_event_subtitle}'   => esc_html($person_event_subtitle),
            '{str_event_subtitle_de}' => esc_html($person_event_subtitle),
            '{id}'           => esc_html($person_id),
            '{dtm_event_date}'          => esc_html($dtm_event_date),
        ));

        $all_replacements = array_merge(
            $image_replacements,
            $text_replacements,
            $core_replacements
        );

        $html = $pdf_creator->render_html((string) $layout['html_template'], $all_replacements);
        $html = strtr($html, $all_replacements);

        $doc = new DocRaptor\Doc();
        $doc->setTest(true);
        $doc->setDocumentType('pdf');
        $doc->setName($file_name_from_person);
        $doc->setDocumentContent($html);

        $pdf = $docraptor->createDoc($doc);

        $target_path = rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name_from_person;
        file_put_contents($target_path, $pdf);

        $generated_files[] = array(
            'file_name' => $file_name_from_person,
            'file_url'  => $pdf_creator->get_pdf_url($subfolder_for_pdf, $event_uid, $file_name_from_person),
            'person'    => trim($first_name . ' ' . $last_name),
        );
    }

    $existing_pdf_files   = $pdf_creator->get_existing_pdf_files($pdf_path, $event_uid, $subfolder_for_pdf);
    $persons_without_file = $pdf_creator->get_persons_without_file($persons, $pdf_path, $file_name_field);
    $zip_download_data    = $pdf_creator->get_zip_download_data($pdf_path, $existing_pdf_files, $type_of_pdf, $event_uid, $subfolder_for_pdf);

    $pdf_creator->show_page_header($type_of_pdf . ' erstellt');
    ?>
    <h1 class="mb-3"><?php echo esc_html($type_of_pdf); ?> erstellt</h1>
    <p><strong>Kongress:</strong> <?php echo esc_html($str_event_name_); ?></p>

    <div class="alert alert-success">
        <?php echo esc_html(count($generated_files)); ?> Datei(en) wurden erstellt.
    </div>

    <ul class="list-group mb-4">
        <?php foreach ($generated_files as $file) : ?>
            <li class="list-group-item">
                <?php echo esc_html($file['person']); ?> —
                <a href="<?php echo esc_url($file['file_url']); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html($file['file_name']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php
    $pdf_creator->render_status_accordion(
        $existing_pdf_files,
        $persons_without_file,
        $type_of_pdf,
        $type_of_pdf_sing,
        $zip_download_data
    );
    ?>

    <a href="<?php echo esc_url(remove_query_arg(array())); ?>" class="btn btn-secondary mt-4">Weitere <?php echo esc_html($type_of_pdf); ?> erstellen</a>
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
