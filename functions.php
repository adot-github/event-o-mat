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

    wp_enqueue_script(
        'bootstrap-5-bundle',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.3.8',
        true
    );

    wp_enqueue_script(
        'event-registration-admin-detect-color',
        get_stylesheet_directory_uri() . '/db-custom/event-registration/admin/js/admin-detect-color.js',
        array(),
        '1.0.0',
        true
    );
});

(function () {
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
    if (!in_array($action, ['adot_iframe_left', 'adot_iframe_right'], true)) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if ($page === '' || strpos($page, 'evtmgr') === false) {
        return;
    }
    ob_start(function ($html) {
        return str_replace(
            '<head>',
            '<head><style>html,body{background:var(--evtmgr-bg)!important}</style>',
            $html
        );
    });
})();

add_action('wp_footer', function () {
    if (!defined('IFRAME_REQUEST') || !IFRAME_REQUEST) {
        return;
    }
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    if (strpos($page, 'evtmgr') === false) {
        return;
    }
    echo '<link rel="stylesheet" href="' . esc_url(get_stylesheet_directory_uri() . '/db-custom/event-registration/admin/css/admin-bootstrap-theme.css') . '" media="all" />' . "\n";
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

    $pages_dir = get_stylesheet_directory() . '/db-custom/event-registration/admin/pages/';
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-pdf-creation.php';
    if (!class_exists('Evtmgr_Options')) {
        require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-options.php';
    }

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

        // Invoices show the billing creation date instead of the event date.
        if ($pdf_layout === 'dachverband-rechnung.php') {
            global $wpdb;
            $billing_date_created = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT dtm_date_created FROM {$wpdb->prefix}evtmgr_registrations_billing
                     WHERE fky_person_id = %d AND fky_event_uid = %s
                     ORDER BY dtm_date_created DESC LIMIT 1",
                    $person_id,
                    $event_uid
                )
            );
            $dtm_event_date = $pdf_creator->format_date((string) ($billing_date_created ?? ''));
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
        $doc->setTest(Evtmgr_Options::is_pdf_test_mode($event_uid));
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

/**
 * AJAX handler — generates one workshop PDF per call, driven by the
 * progress-bar JS in pdf-creation-workshops.php.
 */
add_action('wp_ajax_evtmgr_pdf_generate_workshop', function () {
    if (!check_ajax_referer('evtmgr_pdf_generate', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ungültige Sicherheitsprüfung.'], 403);
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
    }

    $job_id       = sanitize_key(wp_unslash($_POST['job_id'] ?? ''));
    $workshop_idx = absint($_POST['workshop_idx'] ?? 0);

    if ($job_id === '') {
        wp_send_json_error(['message' => 'Kein job_id angegeben.']);
    }

    $job = get_transient('evtmgr_pdf_ws_job_' . $job_id);
    if (empty($job) || !is_array($job)) {
        wp_send_json_error(['message' => 'Job nicht gefunden oder abgelaufen. Bitte neu starten.']);
    }

    $workshops = $job['workshops'] ?? [];
    if (!isset($workshops[$workshop_idx])) {
        wp_send_json_error(['message' => 'Workshop-Index ' . $workshop_idx . ' nicht vorhanden.']);
    }

    $workshop           = $workshops[$workshop_idx];
    $pdf_layout         = $job['pdf_layout']         ?? '';
    $subfolder          = $job['subfolder']          ?? 'workshop-booking-lists';
    $event_uid          = $job['event_uid']          ?? '';
    $event              = $job['event']              ?? [];
    $str_event_name_    = $job['str_event_name_']    ?? '';
    $str_event_subtitle = $job['str_event_subtitle'] ?? '';
    $dtm_event_date     = $job['dtm_event_date']     ?? '';

    if ($pdf_layout === '') {
        wp_send_json_error(['message' => 'Kein PDF-Layout im Job.']);
    }

    $pages_dir = get_stylesheet_directory() . '/db-custom/event-registration/admin/pages/';
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-pdf-creation.php';
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-workshops.php';
    if (!class_exists('Evtmgr_Options')) {
        require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-options.php';
    }

    try {
        $pdf_creator   = new Event_Registration_Pdf_Creation($pages_dir);
        $workshops_obj = new Evtmgr_Workshops();
        $layout        = $pdf_creator->load_pdf_layout($pdf_layout);

        $workshop_id      = absint($workshop['id'] ?? 0);
        $workshop_label   = $workshops_obj->workshop_pdf_label($workshop);
        $file_name        = $workshops_obj->workshop_pdf_file_name($workshop);
        $participants     = $workshops_obj->get_workshop_registered_persons($workshop_id, $event_uid);
        $presenters_text  = $workshops_obj->get_workshop_presenters_text($workshop_id);
        $workshop_label_cb = [$workshops_obj, 'workshop_pdf_label'];

        $image_replacements = $pdf_creator->get_image_replacements($layout);
        $text_replacements  = $pdf_creator->text_replacements($layout, 'de');
        $core_replacements  = [
            '{str_language}'           => 'de',
            '{str_event_name}'         => esc_html($str_event_name_),
            '{str_event_subtitle}'     => esc_html($str_event_subtitle),
            '{str_event_subtitle_de}'  => esc_html($str_event_subtitle),
            '{dtm_event_date}'         => esc_html($dtm_event_date),
            '{id}'                     => esc_html((string) $workshop_id),
            '{str_workshop_number}'    => esc_html($workshops_obj->workshop_value_ci($workshop, 'str_workshop_number')),
            '{str_workshop_title_de}'  => esc_html($workshops_obj->workshop_value_ci($workshop, 'str_workshop_title_de')),
            '{invoice_text}'           => $pdf_creator->render_workshop_participants_html($workshop, $participants, $presenters_text, $workshop_label_cb),
        ];

        $all_replacements = array_merge($image_replacements, $text_replacements, $core_replacements);
        $html = $pdf_creator->render_html((string) $layout['html_template'], $all_replacements);
        $html = strtr($html, $all_replacements);

        $docraptor = $pdf_creator->create_docraptor_client();
        $doc = new DocRaptor\Doc();
        $doc->setTest(Evtmgr_Options::is_pdf_test_mode($event_uid));
        $doc->setDocumentType('pdf');
        $doc->setName($file_name);
        $doc->setDocumentContent($html);

        $pdf      = $docraptor->createDoc($doc);
        $pdf_path = $pdf_creator->get_pdf_path($subfolder, $event_uid);
        $pdf_creator->ensure_directory($pdf_path);
        file_put_contents(rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name, $pdf);

        $file_url = $pdf_creator->get_pdf_url($subfolder, $event_uid, $file_name);

        wp_send_json_success([
            'workshop'  => $workshop_label,
            'file_name' => $file_name,
            'file_url'  => $file_url,
        ]);

    } catch (Throwable $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});