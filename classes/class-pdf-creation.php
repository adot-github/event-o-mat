<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Event_Registration_Pdf_Creation {

    protected string $base_dir;

    public function __construct(string $base_dir) {
        $this->base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR);
    }

    public function load_docraptor_autoload(): void {
        $autoload_candidates = array(
            $this->base_dir . '/vendor/autoload.php',
            dirname($this->base_dir) . '/vendor/autoload.php',
            dirname($this->base_dir, 2) . '/vendor/autoload.php',
        );

        foreach ($autoload_candidates as $autoload_file) {
            if (file_exists($autoload_file)) {
                require_once $autoload_file;
                return;
            }
        }

        throw new RuntimeException('DocRaptor autoload.php wurde nicht gefunden.');
    }

    public function create_docraptor_client(): \DocRaptor\DocApi {
        $this->load_docraptor_autoload();

        $docraptor = new \DocRaptor\DocApi();
        $docraptor->getConfig()->setUsername('u2UGJ0xRC-dYkb42Q--J');

        return $docraptor;
    }

    public function load_pdf_layout(string $pdf_layout_file): array {
        $pdf_layout_file = basename($pdf_layout_file);
        $layout_file     = $this->base_dir . DIRECTORY_SEPARATOR . 'pdf-layouts' . DIRECTORY_SEPARATOR . $pdf_layout_file;

        if (!file_exists($layout_file)) {
            throw new RuntimeException('PDF layout file not found: ' . $pdf_layout_file);
        }

        $layout = require $layout_file;

        if (!is_array($layout)) {
            throw new RuntimeException('PDF layout file must return an array: ' . $pdf_layout_file);
        }

        if (empty($layout['html_template'])) {
            throw new RuntimeException('PDF layout is missing html_template: ' . $pdf_layout_file);
        }

        if (empty($layout['asset_dir'])) {
            $layout['asset_dir'] = $this->base_dir . DIRECTORY_SEPARATOR . 'assets';
        }

        if (empty($layout['images']) || !is_array($layout['images'])) {
            $layout['images'] = array();
        }

        if (empty($layout['texts']) || !is_array($layout['texts'])) {
            $layout['texts'] = array();
        }

        return $layout;
    }

    public function image_to_data_uri(string $filename, string $asset_dir = ''): string {
        $asset_dir  = $asset_dir !== '' ? rtrim($asset_dir, DIRECTORY_SEPARATOR) : $this->base_dir . DIRECTORY_SEPARATOR . 'assets';
        $asset_path = $asset_dir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($asset_path)) {
            throw new RuntimeException('Datei nicht gefunden: ' . $filename);
        }

        $data = file_get_contents($asset_path);

        if ($data === false) {
            throw new RuntimeException('Datei konnte nicht gelesen werden: ' . $filename);
        }

        $mime = mime_content_type($asset_path);

        if (!$mime) {
            $mime = 'image/png';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    public function get_image_replacements(array $layout): array {
        $asset_dir = $layout['asset_dir'] ?? ($this->base_dir . DIRECTORY_SEPARATOR . 'assets');
        $images    = $layout['images'] ?? array();

        $replacements = array();

        foreach ($images as $placeholder => $filename) {
            $replacements[$placeholder] = $this->image_to_data_uri((string) $filename, (string) $asset_dir);
        }

        return $replacements;
    }

    public function value_ci(array $row, string $field_name, string $default = ''): string {
        return Event_Registration_Helpers::value_ci($row, $field_name, $default);
    }

    public function safe_file_part(string $value): string {
        $value = wp_strip_all_tags($value);
        $value = remove_accents($value);
        $value = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $value);
        $value = trim((string) $value, '-_');
        $value = preg_replace('/-+/', '-', $value);

        return $value !== '' ? strtolower($value) : 'pdf';
    }

    public function format_date(string $date_value): string {
        $date_value = trim($date_value);

        if ($date_value === '') {
            return '';
        }

        $timestamp = strtotime($date_value);

        if (!$timestamp) {
            return $date_value;
        }

        return wp_date('d.m.Y', $timestamp);
    }

    public function render_html(string $html_template, array $replacements): string {
        $html = $html_template;

        for ($i = 0; $i < 3; $i++) {
            $previous_html = $html;

            foreach ($replacements as $placeholder => $value) {
                $html = str_replace((string) $placeholder, (string) $value, $html);
            }

            if ($html === $previous_html) {
                break;
            }
        }

        return $html;
    }

    public function get_person_id(array $person): string {
        $person_id = $this->value_ci($person, 'id');

        if ($person_id === '') {
            $person_id = $this->value_ci($person, 'id');
        }

        if ($person_id === '') {
            $person_id = $this->value_ci($person, 'fky_person_id');
        }

        if ($person_id === '') {
            $person_id = $this->value_ci($person, 'fky_person_id');
        }

        return $person_id;
    }

    public function person_label(array $person): string {
        $person_id  = $this->get_person_id($person);
        $first_name = $this->value_ci($person, 'str_first_name');
        $last_name  = $this->value_ci($person, 'str_last_name');
        $email      = $this->value_ci($person, 'str_email');
        $country    = $this->value_ci($person, 'str_country');

        $label_parts = array_filter(array(
            trim($last_name . ' ' . $first_name),
            $email,
            $country,
            $person_id !== '' ? 'id ' . $person_id : '',
        ));

        return implode(' | ', $label_parts);
    }

    public function event_text_by_language(array $event, string $base_field, string $lang, string $fallback = ''): string {
        $lang = strtolower(trim($lang));

        if ($lang === '') {
            $lang = 'de';
        }

        $suffixes = array_unique(array(
            strtoupper($lang),
            strtolower($lang),
            ucfirst(strtolower($lang)),
            'DE',
            'de',
        ));

        foreach ($suffixes as $suffix) {
            $value = $this->value_ci($event, $base_field . $suffix);

            if (trim($value) !== '') {
                return $value;
            }
        }

        $value = $this->value_ci($event, $base_field);

        if (trim($value) !== '') {
            return $value;
        }

        return $fallback;
    }

    public function language_texts(array $layout, string $lang): array {
        $lang = strtolower(trim($lang));

        if ($lang === '') {
            $lang = 'de';
        }

        $texts = $layout['texts'] ?? array();

        if (!empty($texts[$lang]) && is_array($texts[$lang])) {
            return $texts[$lang];
        }

        if (!empty($texts['de']) && is_array($texts['de'])) {
            return $texts['de'];
        }

        return array();
    }

    public function text_replacements(array $layout, string $lang): array {
        $texts = $this->language_texts($layout, $lang);
        $replacements = array();

        foreach ($texts as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return $replacements;
    }

    public function person_replacements(array $person, array $extra = array()): array {
        $person_id = $this->get_person_id($person);

        $replacements = array(
            '{id}'              => esc_html($person_id),
            '{str_salutation}'            => esc_html($this->value_ci($person, 'str_salutation')),
            '{str_academic_title}'         => esc_html($this->value_ci($person, 'str_academic_title')),
            '{str_first_name}'             => esc_html($this->value_ci($person, 'str_first_name')),
            '{str_last_name}'              => esc_html($this->value_ci($person, 'str_last_name')),
            '{str_email}'                 => esc_html($this->value_ci($person, 'str_email')),
            '{str_phone}'                 => esc_html($this->value_ci($person, 'str_phone')),
            '{str_job_title}'              => esc_html($this->value_ci($person, 'str_job_title')),
            '{str_address}'               => esc_html($this->value_ci($person, 'str_address')),
            '{str_zip}'                   => esc_html($this->value_ci($person, 'str_zip')),
            '{str_city}'                  => esc_html($this->value_ci($person, 'str_city')),
            '{str_country}'               => esc_html($this->value_ci($person, 'str_country')),
            '{str_institution}'           => esc_html($this->value_ci($person, 'str_institution')),
            '{str_institution_Division}'  => esc_html($this->value_ci($person, 'str_institution_Division')),
            '{str_institution_Address}'   => esc_html($this->value_ci($person, 'str_institution_Address')),
            '{str_institution_Zip}'       => esc_html($this->value_ci($person, 'str_institution_Zip')),
            '{str_institution_City}'      => esc_html($this->value_ci($person, 'str_institution_City')),
        );

        foreach ($person as $field_name => $field_value) {
            if (is_scalar($field_value)) {
                $replacements['{' . $field_name . '}'] = esc_html((string) $field_value);
            }
        }

        return array_merge($replacements, $extra);
    }

    public function file_name_from_person_field(array $person, string $file_name_field): string {
        $file_name = $this->value_ci($person, $file_name_field);
        $file_name = trim($file_name);

        if ($file_name === '') {
            return '';
        }

        $file_name = basename($file_name);

        return strtolower($file_name);
    }

    public function get_pdf_path(string $subfolder_for_pdf, string $event_uid): string {
        return dirname($this->base_dir) . DIRECTORY_SEPARATOR . 'file-storage' . DIRECTORY_SEPARATOR . sanitize_file_name($subfolder_for_pdf) . DIRECTORY_SEPARATOR . sanitize_file_name($event_uid) . DIRECTORY_SEPARATOR;
    }

    public function get_pdf_url(string $subfolder_for_pdf, string $event_uid, string $file_name = ''): string {
        $url = get_stylesheet_directory_uri() . '/db-custom/event-registration/file-storage/' .
            rawurlencode($subfolder_for_pdf) . '/' .
            rawurlencode($event_uid);

        if ($file_name !== '') {
            $url .= '/' . rawurlencode($file_name);
        }

        return $url;
    }

    public function ensure_directory(string $path): void {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Failed to create directory: ' . $path);
        }
    }

    public function get_existing_pdf_files(string $pdf_path, string $event_uid, string $subfolder_for_pdf = 'diplomas'): array {
        if (!is_dir($pdf_path)) {
            return array();
        }

        $files = glob(rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.pdf');

        if (empty($files) || !is_array($files)) {
            return array();
        }

        natcasesort($files);

        $items = array();

        foreach ($files as $file_path) {
            if (!is_file($file_path) || !is_readable($file_path)) {
                continue;
            }

            $file_name = basename($file_path);

            $items[] = array(
                'file_name' => $file_name,
                'file_path' => $file_path,
                'file_url'  => $this->get_pdf_url($subfolder_for_pdf, $event_uid, $file_name),
                'mtime'     => filemtime($file_path),
                'size'      => filesize($file_path),
            );
        }

        return $items;
    }

    public function get_persons_without_file(array $persons, string $pdf_path, string $file_name_field): array {
        $missing = array();

        foreach ($persons as $person) {
            $expected_file_name = $this->file_name_from_person_field($person, $file_name_field);

            if ($expected_file_name === '') {
                $person['expected_pdf_file'] = 'Kein Dateiname im Feld ' . $file_name_field;
                $missing[] = $person;
                continue;
            }

            $expected_path = rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $expected_file_name;

            if (!file_exists($expected_path)) {
                $person['expected_pdf_file'] = $expected_file_name;
                $missing[] = $person;
            }
        }

        return $missing;
    }

    public function get_zip_download_data(string $pdf_path, array $existing_pdf_files, string $type_of_pdf, string $event_uid, string $subfolder_for_pdf): array {
        $result = array(
            'url'   => '',
            'path'  => '',
            'name'  => '',
            'count' => count($existing_pdf_files),
            'error' => '',
        );

        if (empty($existing_pdf_files)) {
            return $result;
        }

        if (!class_exists('ZipArchive')) {
            $result['error'] = 'ZIP-Unterstützung ist auf diesem Server nicht verfügbar.';
            return $result;
        }

        $this->ensure_directory($pdf_path);

        if (!is_writable($pdf_path)) {
            $result['error'] = 'PDF-Ordner ist nicht beschreibbar.';
            return $result;
        }

        $zip_file_name = strtolower(sanitize_file_name($type_of_pdf . '-' . $event_uid . '.zip'));
        $zip_file_path = rtrim($pdf_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zip_file_name;

        $zip = new ZipArchive();

        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $result['error'] = 'ZIP-Datei konnte nicht erstellt werden.';
            return $result;
        }

        foreach ($existing_pdf_files as $file) {
            $file_path = '';

            if (is_array($file) && !empty($file['file_path'])) {
                $file_path = (string) $file['file_path'];
            } elseif (is_string($file)) {
                $file_path = $file;
            }

            if ($file_path !== '' && is_file($file_path)) {
                $zip->addFile($file_path, basename($file_path));
            }
        }

        $zip->close();

        if (!is_file($zip_file_path) || filesize($zip_file_path) <= 0) {
            $result['error'] = 'ZIP-Datei wurde nicht korrekt erstellt.';
            return $result;
        }

        $result['url']  = $this->get_pdf_url($subfolder_for_pdf, $event_uid, $zip_file_name);
        $result['path'] = $zip_file_path;
        $result['name'] = $zip_file_name;

        return $result;
    }

    public function get_download_zip_button_html(array $zip_download_data, string $type_of_pdf): string {
        if (!empty($zip_download_data['error'])) {
            return '<div class="alert alert-warning mb-3">' . esc_html($zip_download_data['error']) . '</div>';
        }

        if (empty($zip_download_data['url'])) {
            return '';
        }

        return sprintf(
            '<div class="mb-3"><a class="btn btn-outline-primary" href="%s" download="%s">%s</a></div>',
            esc_url($zip_download_data['url']),
            esc_attr($zip_download_data['name']),
            esc_html(sprintf('Alle bestehenden %s als ZIP herunterladen', $type_of_pdf))
        );
    }

    public function render_status_accordion(array $existing_pdf_files, array $missing_items, string $type_of_pdf, string $type_of_pdf_sing, array $zip_download_data = array(), callable $missing_label_callback = null, string $missing_heading_prefix = 'Teilnehmende ohne'): void {
        $accordion_id = 'pdf_status_accordion';
        $zip_button_html = $this->get_download_zip_button_html($zip_download_data, $type_of_pdf);
        ?>
        <div class="accordion mt-4 js-diploma-accordion" id="<?php echo esc_attr($accordion_id); ?>">
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading_existing_pdf">
                    <button class="accordion-button collapsed" type="button" data-bs-target="#collapse_existing_pdf" aria-expanded="false" aria-controls="collapse_existing_pdf">
                        Bestehende <?php echo esc_html($type_of_pdf); ?> (<?php echo esc_html((string) count($existing_pdf_files)); ?>)
                    </button>
                </h2>
                <div id="collapse_existing_pdf" class="accordion-collapse collapse" aria-labelledby="heading_existing_pdf">
                    <div class="accordion-body">
                        <?php echo $zip_button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                        <?php if (empty($existing_pdf_files)) : ?>
                            <p class="text-muted mb-0">Es sind noch keine <?php echo esc_html($type_of_pdf); ?> vorhanden.</p>
                        <?php else : ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($existing_pdf_files as $file) : ?>
                                    <a class="list-group-item list-group-item-action" href="<?php echo esc_url($file['file_url']); ?>" target="_blank" rel="noopener">
                                        <div class="d-flex w-100 justify-content-between gap-3">
                                            <span class="text-break"><?php echo esc_html($file['file_name']); ?></span>
                                            <?php if (isset($file['size'])) : ?>
                                                <small class="text-muted text-nowrap"><?php echo esc_html(size_format((int) $file['size'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (isset($file['mtime'])) : ?>
                                            <small class="text-muted"><?php echo esc_html(wp_date('d.m.Y H:i', (int) $file['mtime'])); ?></small>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="heading_missing_pdf">
                    <button class="accordion-button collapsed" type="button" data-bs-target="#collapse_missing_pdf" aria-expanded="false" aria-controls="collapse_missing_pdf">
                        <?php echo esc_html($missing_heading_prefix); ?> <?php echo esc_html($type_of_pdf_sing); ?> (<?php echo esc_html((string) count($missing_items)); ?>)
                    </button>
                </h2>
                <div id="collapse_missing_pdf" class="accordion-collapse collapse" aria-labelledby="heading_missing_pdf">
                    <div class="accordion-body">
                        <?php if (empty($missing_items)) : ?>
                            <p class="text-muted mb-0">Für alle gefundenen Einträge existiert bereits eine Datei.</p>
                        <?php else : ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($missing_items as $item) : ?>
                                    <div class="list-group-item">
                                        <div class="">
                                            <?php echo esc_html($missing_label_callback ? (string) $missing_label_callback($item) : $this->person_label($item)); ?>
                                        </div>
                                        <?php if (!empty($item['expected_pdf_file'])) : ?>
                                            <small class="text-muted text-break">Erwartete Datei: <?php echo esc_html($item['expected_pdf_file']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_workshop_participants_html(array $workshop, array $persons, string $presenters_text = '', callable $workshop_label_callback = null): string {
        ob_start();
        ?>
        <section class="workshop-participants">
            <h3 class="workshop-title"><?php echo esc_html($workshop_label_callback ? (string) $workshop_label_callback($workshop) : $this->value_ci($workshop, 'str_workshop_title_de')); ?></h3>
            <?php if ($presenters_text !== '') : ?>
                <h4 class="workshop-person-name"><?php echo esc_html($presenters_text); ?></h4>
            <?php endif; ?>

            <table class="participants-table">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-last">Name</th>
                        <th class="col-first">Vorname</th>
                        <th class="col-email">E-Mail</th>
                        <th class="col-job">Funktion</th>
                        <th class="col-institution">Institution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($persons)) : ?>
                        <tr><td colspan="6" class="empty-row">Keine Anmeldungen gefunden.</td></tr>
                    <?php else : ?>
                        <?php foreach ($persons as $index => $person) : ?>
                            <tr>
                                <td class="col-num"><?php echo esc_html((string) ($index + 1)); ?></td>
                                <td><?php echo esc_html($this->value_ci($person, 'str_last_name')); ?></td>
                                <td><?php echo esc_html($this->value_ci($person, 'str_first_name')); ?></td>
                                <td><?php echo esc_html($this->value_ci($person, 'str_email')); ?></td>
                                <td><?php echo esc_html($this->value_ci($person, 'str_job_title', $this->value_ci($person, 'str_function'))); ?></td>
                                <td><?php echo esc_html($this->value_ci($person, 'str_institution')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function show_page_header(string $title = 'PDF-Erstellung'): void {
        $css_file = $this->base_dir . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pdf-creation.css';
        $css_url  = get_stylesheet_directory_uri() . '/db-custom/event-registration/pages/css/pdf-creation.css';
        ?>
        <?php if (file_exists($css_file)) : ?>
            <link rel="stylesheet" href="<?php echo esc_url($css_url); ?>">
        <?php endif; ?>
        <main class="container-xl py-4 diploma-page pdf-creation-page">
            <div class="row">
                <div class="col-12">
        <?php
    }

    public function show_page_footer(): void {
        ?>
                </div>
            </div>
        </main>

        <script>
            (function () {
                function setButtonState(button, isOpen) {
                    if (!button) {
                        return;
                    }

                    button.classList.toggle('collapsed', !isOpen);
                    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }

                function initPdfAccordions() {
                    const accordions = document.querySelectorAll('.js-diploma-accordion');

                    accordions.forEach(function (accordion) {
                        const buttons = accordion.querySelectorAll('.accordion-button[data-bs-target]');

                        buttons.forEach(function (button) {
                            if (button.dataset.pdfAccordionBound === '1') {
                                return;
                            }

                            button.dataset.pdfAccordionBound = '1';

                            button.addEventListener('click', function (event) {
                                event.preventDefault();

                                const targetSelector = button.getAttribute('data-bs-target');
                                const target = targetSelector ? document.querySelector(targetSelector) : null;

                                if (!target || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
                                    return;
                                }

                                const isOpen = target.classList.contains('show');

                                accordion.querySelectorAll('.accordion-collapse.show').forEach(function (openPanel) {
                                    if (openPanel !== target) {
                                        const openInstance = bootstrap.Collapse.getOrCreateInstance(openPanel, { toggle: false });
                                        openInstance.hide();

                                        const openButton = accordion.querySelector('.accordion-button[data-bs-target="#' + openPanel.id + '"]');
                                        setButtonState(openButton, false);
                                    }
                                });

                                const instance = bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });

                                if (isOpen) {
                                    instance.hide();
                                    setButtonState(button, false);
                                } else {
                                    instance.show();
                                    setButtonState(button, true);
                                }
                            });
                        });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initPdfAccordions);
                } else {
                    initPdfAccordions();
                }
            })();
        </script>
        <?php
    }
}
