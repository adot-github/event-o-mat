<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-pdf-creation.php';
require_once __DIR__ . '/class-evtmgr-pdf-content.php';
require_once __DIR__ . '/class-evtmgr-options.php';

/**
 * Evtmgr_Booklet
 *
 * Builds a multi-page event booklet PDF from a per-event manifest.
 *
 * Layout lives entirely under  uploads/<event_uid>/pdf-templates/booklet/ :
 *   - index.php   the manifest: returns the list of pages, each binding a
 *                 fragment template to token values. It is executed with an
 *                 Evtmgr_Pdf_Content instance available as $content.
 *   - styles.css  one shared stylesheet for every page.
 *   - *.php       fragment templates. Each returns an array with a
 *                 'html_template' that is a plain <div class="page">…</div>
 *                 fragment (no <!doctype>, <head> or <style>).
 *
 * The generator wraps all rendered fragments in a single HTML document with
 * one <head> + the shared stylesheet and sends that to DocRaptor.
 */
class Evtmgr_Booklet {

    protected string $event_uid;
    protected string $lang;
    protected string $booklet_dir;
    protected string $assets_dir;

    protected Event_Registration_Pdf_Creation $pdf;

    /** token => asset filename, accumulated from every rendered page template. */
    protected array $seen_images = array();

    public function __construct(string $event_uid, string $lang = 'de') {
        $this->event_uid = sanitize_file_name($event_uid);
        $this->lang      = strtolower(trim($lang)) !== '' ? strtolower(trim($lang)) : 'de';

        $upload_dir  = wp_upload_dir();
        $event_base  = rtrim((string) ($upload_dir['basedir'] ?? ''), '/\\')
            . DIRECTORY_SEPARATOR . $this->event_uid;
        $templates   = $event_base . DIRECTORY_SEPARATOR . 'pdf-templates';

        $this->booklet_dir = $templates . DIRECTORY_SEPARATOR . 'booklet';
        // Template images live under <event>/assets/pdf-images/ (not pdf-templates/assets/).
        $this->assets_dir  = $event_base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'pdf-images';

        // admin/pages holds vendor/ (DocRaptor + php-qrcode); the helper's
        // autoload + DocRaptor client resolution keys off this base dir.
        $this->pdf = new Event_Registration_Pdf_Creation(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'pages'
        );
    }

    /**
     * Generates the booklet.
     *
     * @param bool $html_only  when true, returns the assembled HTML instead of
     *                         calling DocRaptor (useful for debugging the layout).
     *
     * @return array{ok:bool,error:string,html:string,path:string,url:string,filename:string,page_count:int,test_mode:bool}
     */
    public function generate(bool $html_only = false): array {
        $result = array(
            'ok'         => false,
            'error'      => '',
            'html'       => '',
            'path'       => '',
            'url'        => '',
            'filename'   => '',
            'page_count' => 0,
            'test_mode'  => true,
        );

        try {
            // Loads admin/pages/vendor/autoload.php – DocRaptor *and* php-qrcode.
            $this->pdf->load_docraptor_autoload();

            $content = new Evtmgr_Pdf_Content($this->event_uid, $this->lang);

            if (!$content->has_event()) {
                throw new RuntimeException('Kein Event gefunden für UID: ' . $this->event_uid);
            }

            $index_file = $this->booklet_dir . DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($index_file)) {
                throw new RuntimeException('Booklet-Manifest nicht gefunden: ' . $index_file);
            }

            $manifest = $this->load_manifest($index_file, $content);

            if (isset($manifest['pages']) && is_array($manifest['pages'])) {
                $css_assets = (array) ($manifest['css_assets'] ?? array());
                $pages      = $manifest['pages'];
            } else {
                $css_assets = array();
                $pages      = $manifest;
            }

            $pages = array_values(array_filter((array) $pages, 'is_array'));

            if (empty($pages)) {
                throw new RuntimeException('Booklet-Manifest enthält keine Seiten.');
            }

            $page_count        = count($pages);
            $body              = '';
            $this->seen_images = array();

            foreach ($pages as $i => $page) {
                $body .= $this->render_page($page, $i + 1, $page_count);
            }

            $css = $this->build_css(array_merge($css_assets, $this->seen_images));

            $html = $this->assemble_document($content->get_event_name_plain(), $css, $body);

            $result['html']       = $html;
            $result['page_count'] = $page_count;
            $result['test_mode']  = Evtmgr_Options::is_pdf_test_mode($this->event_uid);

            $out_dir = $this->output_dir();
            $this->pdf->ensure_directory($out_dir);

            // File name comes from wp_evtmgr_events.str_event_pdf_<lang>
            // (filled by Evtmgr_Events::event_update_booklet_pdf_filenames());
            // classic "booklet-<uid>.pdf" as fallback.
            $base_name = (string) preg_replace('/\.pdf$/i', '', basename($content->get_event_pdf_filename()));
            if ($base_name === '') {
                $base_name = 'booklet-' . $this->event_uid;
            }

            if ($html_only) {
                $filename = $base_name . '.debug.html';
                $out_path = $out_dir . DIRECTORY_SEPARATOR . $filename;

                if (file_put_contents($out_path, $html) === false) {
                    throw new RuntimeException('HTML-Vorschau konnte nicht gespeichert werden: ' . $out_path);
                }

                $result['ok']       = true;
                $result['path']     = $out_path;
                $result['filename'] = $filename;
                $result['url']      = $this->output_url($filename);
                return $result;
            }

            $filename  = $base_name . '.pdf';
            $pdf_bytes = $this->render_pdf($html, $filename, $result['test_mode']);

            $out_path = $out_dir . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($out_path, $pdf_bytes) === false) {
                throw new RuntimeException('PDF konnte nicht gespeichert werden: ' . $out_path);
            }

            $result['ok']       = true;
            $result['path']     = $out_path;
            $result['filename'] = $filename;
            $result['url']      = $this->output_url($filename);
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    // ── Manifest ────────────────────────────────────────────────────────────

    /**
     * Includes the manifest file with a clean scope. Only $content is exposed.
     */
    protected function load_manifest(string $index_file, Evtmgr_Pdf_Content $content): array {
        $load = static function (string $__manifest_file, Evtmgr_Pdf_Content $content): array {
            $data = require $__manifest_file;
            return is_array($data) ? $data : array();
        };

        return $load($index_file, $content);
    }

    // ── Per-page rendering ──────────────────────────────────────────────────

    protected function render_page(array $page, int $page_no, int $page_count): string {
        $template_name = basename((string) ($page['template'] ?? ''));

        if ($template_name === '') {
            throw new RuntimeException('Seite ' . $page_no . ': kein template angegeben.');
        }

        $template_file = $this->booklet_dir . DIRECTORY_SEPARATOR . $template_name;
        if (!file_exists($template_file)) {
            throw new RuntimeException('Template nicht gefunden: ' . $template_file);
        }

        $layout = require $template_file;
        if (!is_array($layout) || empty($layout['html_template'])) {
            throw new RuntimeException('Template ohne html_template: ' . $template_name);
        }

        // Ignore any asset_dir the template sets – images always come from
        // <event>/assets/pdf-images/.
        $asset_dir = $this->assets_dir;

        $images = array_merge(
            (array) ($layout['images'] ?? array()),
            (array) ($page['assets'] ?? array())
        );

        // Remember template-level assets so build_css() can resolve font tokens.
        $this->seen_images = array_merge($this->seen_images, (array) ($layout['images'] ?? array()));

        $replacements = array_merge(
            $this->text_replacements($layout),
            $this->resolve_assets($images, $asset_dir),
            $this->normalize_tokens((array) ($page['tokens'] ?? array())),
            array(
                '{page_no}'     => (string) $page_no,
                '{page_count}'  => (string) $page_count,
            )
        );

        $rendered = $this->pdf->render_html((string) $layout['html_template'], $replacements);

        return $this->strip_unused_tokens($rendered) . "\n";
    }

    protected function text_replacements(array $layout): array {
        $texts = array();
        $bag   = $layout['texts'][$this->lang] ?? ($layout['texts']['de'] ?? array());

        foreach ((array) $bag as $key => $value) {
            $texts['{' . $key . '}'] = (string) $value;
        }

        return $texts;
    }

    /**
     * Manifest token keys may be given with or without the surrounding braces.
     */
    protected function normalize_tokens(array $tokens): array {
        $out = array();

        foreach ($tokens as $key => $value) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            if ($key[0] !== '{') {
                $key = '{' . $key . '}';
            }
            $out[$key] = (string) $value;
        }

        return $out;
    }

    /**
     * @param array<string,string> $map  token => asset filename
     * @return array<string,string>       token => data URI ('' when missing)
     */
    protected function resolve_assets(array $map, string $asset_dir): array {
        $out = array();

        foreach ($map as $token => $filename) {
            $filename = (string) $filename;
            if ($filename === '') {
                $out[(string) $token] = '';
                continue;
            }

            try {
                $out[(string) $token] = $this->pdf->image_to_data_uri($filename, $asset_dir);
            } catch (\Throwable $e) {
                $out[(string) $token] = '';
            }
        }

        return $out;
    }

    protected function strip_unused_tokens(string $html): string {
        // Leftover placeholders like {token} or {$token}; never matches a CSS
        // rule body ("{ color:red }") because those contain whitespace.
        return (string) preg_replace('/\{\$?[A-Za-z0-9_.\-]+\}/', '', $html);
    }

    // ── Stylesheet ─────────────────────────────────────────────────────────

    /**
     * Loads booklet/styles.css and resolves any asset tokens it contains
     * (typically @font-face url('{inter_*_data_uri}')).
     *
     * @param array<string,string> $asset_map  token => asset filename
     */
    protected function build_css(array $asset_map): string {
        $css_file = $this->booklet_dir . DIRECTORY_SEPARATOR . 'styles.css';
        $css      = is_readable($css_file) ? (string) file_get_contents($css_file) : '';

        if ($css === '') {
            return '';
        }

        $css = $this->pdf->render_html($css, $this->resolve_assets($asset_map, $this->assets_dir));

        return $this->strip_unused_tokens($css);
    }

    // ── Document assembly ──────────────────────────────────────────────────

    protected function assemble_document(string $title, string $css, string $body): string {
        $title = trim(wp_strip_all_tags($title));
        if ($title === '') {
            $title = 'Booklet';
        }
        $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<!doctype html>\n"
            . "<html lang=\"" . htmlspecialchars($this->lang, ENT_QUOTES, 'UTF-8') . "\">\n"
            . "<head>\n"
            . "<meta charset=\"utf-8\">\n"
            . "<title>{$title}</title>\n"
            . "<style>\n{$css}\n</style>\n"
            . "</head>\n"
            . "<body>\n{$body}\n</body>\n"
            . "</html>\n";
    }

    // ── DocRaptor ──────────────────────────────────────────────────────────

    protected function render_pdf(string $html, string $filename, bool $test_mode): string {
        $client = $this->pdf->create_docraptor_client();

        $doc = new \DocRaptor\Doc();
        $doc->setName($filename);
        $doc->setDocumentType('pdf');
        $doc->setTest($test_mode);
        $doc->setDocumentContent($html);

        return (string) $client->createDoc($doc);
    }

    // ── Output location ────────────────────────────────────────────────────

    protected function output_dir(): string {
        $upload_dir = wp_upload_dir();

        return rtrim((string) ($upload_dir['basedir'] ?? ''), '/\\')
            . DIRECTORY_SEPARATOR . $this->event_uid
            . DIRECTORY_SEPARATOR . 'pdf'
            . DIRECTORY_SEPARATOR . 'booklet';
    }

    protected function output_url(string $filename): string {
        $upload_dir = wp_upload_dir();

        return rtrim((string) ($upload_dir['baseurl'] ?? ''), '/')
            . '/' . rawurlencode($this->event_uid)
            . '/pdf/booklet/' . rawurlencode($filename);
    }

    public function existing_pdfs(): array {
        $dir = $this->output_dir();
        if (!is_dir($dir)) {
            return array();
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: array();
        natcasesort($files);

        $items = array();
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $items[] = array(
                'file_name' => basename($file),
                'file_url'  => $this->output_url(basename($file)),
                'mtime'     => filemtime($file),
                'size'      => filesize($file),
            );
        }

        return $items;
    }
}
