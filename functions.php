<?php
add_action('admin_enqueue_scripts', function ($hook_suffix) {
    global $admin_page_hooks;
    $evtmgr_prefix = $admin_page_hooks['adot_evtmgr_events'] ?? '';
    $is_evtmgr = strpos($hook_suffix, 'evtmgr') !== false
              || ($evtmgr_prefix !== '' && strpos($hook_suffix, $evtmgr_prefix) !== false);
    if (!$is_evtmgr) {
        return;
    }
    wp_enqueue_style(
        'bootstrap-5',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
        array(),
        '5.3.8'
    );

    wp_enqueue_style(
        'event-registration-admin-theme',
        get_stylesheet_directory_uri() . '/db-custom/event-registration/admin/css/admin-bootstrap-theme.css',
        array('bootstrap-5'),
        '1.0.0'
    );

    wp_enqueue_style(
        'event-registration-dashboard',
        get_stylesheet_directory_uri() . '/db-custom/event-registration/pages/css/dashboard.css',
        array('bootstrap-5'),
        '1.0.0'
    );

    wp_enqueue_script(
        'bootstrap-5-bundle',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.3.8',
        true
    );
});

add_action('wp_footer', function () {
    if (!defined('IFRAME_REQUEST') || !IFRAME_REQUEST) {
        return;
    }
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    if (strpos($page, 'evtmgr') === false) {
        return;
    }
    echo '<link rel="stylesheet" href="' . esc_url(get_stylesheet_directory_uri() . '/db-custom/event-registration/pages/css/dashboard.css') . '" media="all" />' . "\n";
});

require_once get_stylesheet_directory() . '/db-custom/event-registration/public/functions.php';

/**
 * AJAX handler — generates one PDF per call, driven by the progress-bar JS
 * in pdf-creation.php. Job data is stored in a WP transient keyed by job_id.
 */
add_action('wp_ajax_evtmgr_pdf_generate_person', function () {
    if (!check_ajax_referer('evtmgr_pdf_generate', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ungültige Sicherheitsprüfung.'], 403);
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
    }

    $job_id     = sanitize_key(wp_unslash($_POST['job_id'] ?? ''));
    $person_idx = absint($_POST['person_idx'] ?? 0);

    if ($job_id === '') {
        wp_send_json_error(['message' => 'Kein job_id angegeben.']);
    }

    $job = get_transient('evtmgr_pdf_job_' . $job_id);
    if (empty($job) || !is_array($job)) {
        wp_send_json_error(['message' => 'Job nicht gefunden oder abgelaufen. Bitte neu starten.']);
    }

    $persons = $job['persons'] ?? [];
    if (!isset($persons[$person_idx])) {
        wp_send_json_error(['message' => 'Person-Index ' . $person_idx . ' nicht vorhanden.']);
    }

    $person          = $persons[$person_idx];
    $pdf_layout      = $job['pdf_layout']      ?? '';
    $file_name_field = $job['file_name_field'] ?? '';
    $subfolder       = $job['subfolder']       ?? 'pdfs';
    $event           = $job['event']           ?? [];
    $event_uid       = $job['event_uid']       ?? '';
    $str_event_name_ = $job['str_event_name_'] ?? '';
    $dtm_event_date  = $job['dtm_event_date']  ?? '';

    if ($pdf_layout === '') {
        wp_send_json_error(['message' => 'Kein PDF-Layout im Job.']);
    }

    $pages_dir = get_stylesheet_directory() . '/db-custom/event-registration/pages/';
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-pdf-creation.php';

    try {
        $pdf_creator = new Event_Registration_Pdf_Creation($pages_dir);
        $layout      = $pdf_creator->load_pdf_layout($pdf_layout);

        $person_id   = $pdf_creator->get_person_id($person);
        $first_name  = $pdf_creator->value_ci($person, 'str_first_name');
        $last_name   = $pdf_creator->value_ci($person, 'str_last_name');
        $person_lang = strtolower(trim($pdf_creator->value_ci($person, 'str_language', 'de')));
        if ($person_lang === '') {
            $person_lang = 'de';
        }

        $file_name_from_person = $pdf_creator->file_name_from_person_field($person, $file_name_field);
        if ($file_name_from_person === '') {
            wp_send_json_error([
                'message' => 'Für ' . trim($first_name . ' ' . $last_name) . ' fehlt der Dateiname im Feld ' . $file_name_field . '.',
            ]);
        }

        $person_event_name     = $pdf_creator->event_text_by_language($event, 'str_event_name',     $person_lang, $str_event_name_);
        $person_event_subtitle = $pdf_creator->event_text_by_language($event, 'str_event_subtitle', $person_lang, '');

        $image_replacements = $pdf_creator->get_image_replacements($layout);
        $text_replacements  = $pdf_creator->text_replacements($layout, $person_lang);
        $core_replacements  = $pdf_creator->person_replacements($person, [
            '{str_language}'          => esc_attr($person_lang),
            '{str_event_name}'        => esc_html($person_event_name),
            '{str_event_subtitle}'    => esc_html($person_event_subtitle),
            '{str_event_subtitle_de}' => esc_html($person_event_subtitle),
            '{id}'                    => esc_html($person_id),
            '{dtm_event_date}'        => esc_html($dtm_event_date),
        ]);

        $callback_replacements = [];
        if (!empty($layout['per_person_callback']) && is_callable($layout['per_person_callback'])) {
            $callback_replacements = (array) call_user_func($layout['per_person_callback'], $person, $event, $person_lang);
        }

        $all_replacements = array_merge($image_replacements, $text_replacements, $core_replacements, $callback_replacements);

        $html = $pdf_creator->render_html((string) $layout['html_template'], $all_replacements);
        $html = strtr($html, $all_replacements);

        $docraptor = $pdf_creator->create_docraptor_client();

        $doc = new DocRaptor\Doc();
        $doc->setTest(true);
        $doc->setDocumentType('pdf');
        $doc->setName($file_name_from_person);
        $doc->setDocumentContent($html);

        $pdf = $docraptor->createDoc($doc);

        $pdf_path = $pdf_creator->get_pdf_path($subfolder, $event_uid);
        $pdf_creator->ensure_directory($pdf_path);
        file_put_contents(rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name_from_person, $pdf);

        $file_url = $pdf_creator->get_pdf_url($subfolder, $event_uid, $file_name_from_person);

        wp_send_json_success([
            'person'    => trim($first_name . ' ' . $last_name),
            'file_name' => $file_name_from_person,
            'file_url'  => $file_url,
        ]);

    } catch (Throwable $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});