<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';
require_once __DIR__ . '/class-evtmgr-events.php';
require_once __DIR__ . '/class-evtmgr-slots.php';
require_once __DIR__ . '/class-evtmgr-workshops.php';
require_once __DIR__ . '/class-evtmgr-presenters.php';
require_once __DIR__ . '/class-evtmgr-time-zones.php';
require_once __DIR__ . '/class-evtmgr-pricing.php';
require_once __DIR__ . '/class-evtmgr-sponsors.php';

/**
 * Evtmgr_Pdf_Content
 *
 * Central content provider for generated event documents (booklet, flyers …).
 * Every method returns a ready-to-embed string – plain text or an HTML block –
 * for one event in one language. No layout logic lives here, no template knows
 * about the database: templates only receive the strings these methods return.
 *
 * The HTML blocks use the CSS class names defined in the booklet stylesheet
 * (pr-*, tt-*, ks-*, sp-*). They were lifted from the former admin/pages/event-pdf.php.
 */
class Evtmgr_Pdf_Content {

    protected string $event_uid;
    protected string $lang;

    /** @var array<string,mixed>|null */
    protected ?array $event = null;

    protected const GERMAN_DAYS = array(
        'Sunday'    => 'Sonntag',
        'Monday'    => 'Montag',
        'Tuesday'   => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday'  => 'Donnerstag',
        'Friday'    => 'Freitag',
        'Saturday'  => 'Samstag',
    );

    public function __construct(string $event_uid, string $lang = 'de') {
        $this->event_uid = sanitize_text_field($event_uid);
        $this->lang      = strtolower(trim($lang)) !== '' ? strtolower(trim($lang)) : 'de';

        $events_obj  = new Evtmgr_Events();
        $this->event = $events_obj->get_event_by_event_uid($this->event_uid, $this->lang) ?: null;
    }

    public function has_event(): bool {
        return $this->event !== null;
    }

    public function get_event_uid(): string {
        return $this->event_uid;
    }

    public function get_lang(): string {
        return $this->lang;
    }

    /** Raw event row (language columns already aliased by Evtmgr_Events). */
    public function get_event_row(): array {
        return $this->event ?? array();
    }

    // ── Scalar fields ───────────────────────────────────────────────────────

    public function get_event_title(): string {
        return $this->e($this->field('str_event_name'));
    }

    /** Unescaped event name – for <title>, filenames, logging. */
    public function get_event_name_plain(): string {
        return trim((string) $this->field('str_event_name'));
    }

    public function get_event_subtitle(): string {
        return $this->e($this->field('str_event_subtitle'));
    }

    /** Formatted event day, e.g. "Donnerstag, 5.3.2026". */
    public function get_event_date(): string {
        return $this->e($this->format_german_date($this->field('dtm_event_date')));
    }

    /** Formatted "registration opens" day. */
    public function get_event_registration_opened(): string {
        return $this->e($this->format_german_date($this->field('dtm_registration_opened')));
    }

    /** Formatted "registration closes" day. */
    public function get_event_registration_closed(): string {
        return $this->e($this->format_german_date($this->field('dtm_registration_closed')));
    }

    /**
     * Stored booklet PDF file name (str_event_pdf_<lang>, falling back to the
     * German one). Unescaped – for file paths / DocRaptor. '' when unset.
     */
    public function get_event_pdf_filename(): string {
        $name = trim((string) $this->field('str_event_pdf_' . $this->lang));
        if ($name === '') {
            $name = trim((string) $this->field('str_event_pdf_de'));
        }
        return $name;
    }

    /** Rich-text event description (already HTML, not escaped). */
    public function get_event_description(): string {
        return (string) $this->field('mem_event_description');
    }

    /** Public event / registration URL (wp_evtmgr_events.str_event_url). */
    public function get_event_url(): string {
        return trim((string) $this->field('str_event_url'));
    }

    // ── HTML blocks ─────────────────────────────────────────────────────────

    /**
     * Pricing / registration table.
     * Ported from event-pdf.php (Evtmgr_Pricing::get_pricing_top / get_pricing_by_parent).
     */
    public function get_event_price_map(): string {
        $pricing_obj = new Evtmgr_Pricing();
        $pr_parents  = $pricing_obj->get_pricing_top($this->event_uid, $this->lang);

        $rows = '';

        foreach ((array) $pr_parents as $pr_parent) {
            $pr_pid   = absint($pr_parent['id'] ?? 0);
            $pr_pname = $this->e($pr_parent['str_pricing_name'] ?? '');

            $pr_children = $pricing_obj->get_pricing_by_parent($pr_pid, $this->event_uid, $this->lang);

            $pr_pamount = $pricing_obj->normalize_price($pr_parent['num_price'] ?? 0);

            if ($pr_pname !== '') {
                $rows .= '<tr class="pr-section"><td>' . $pr_pname . '</td>'
                    . '<td class="pr-price">' . $this->e($this->format_price($pr_pamount)) . '</td></tr>';
            }

            foreach ((array) $pr_children as $pr_child) {
                $pr_cname  = $this->e($pr_child['str_pricing_name'] ?? '');
                $pr_cdesc  = trim((string) ($pr_child['mem_pricing_description'] ?? ''));
                $pr_amount = $pricing_obj->normalize_price($pr_child['num_price'] ?? 0);
                $pr_valid  = trim((string) ($pr_child['dtm_date_valid_to'] ?? ''));

                $pr_name_cell = $pr_cname;
                if ($pr_cdesc !== '') {
                    $pr_name_cell .= '<div class="pr-desc">' . $this->e($pr_cdesc) . '</div>';
                }
                if ($pr_valid !== '' && strlen($pr_valid) >= 10) {
                    $pr_name_cell .= '<div class="pr-valid">gültig bis '
                        . $this->e(date('j.n.Y', (int) strtotime($pr_valid))) . '</div>';
                }

                $rows .= '<tr class="pr-row">'
                    . '<td class="pr-name">' . $pr_name_cell . '</td>'
                    . '<td class="pr-price">' . $this->e($this->format_price($pr_amount)) . '</td>'
                    . '</tr>';
            }
        }

        if ($rows === '') {
            return '<p>Keine Preise vorhanden.</p>';
        }

        return '<table class="pr-table"><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * Programme timetable (slots × time zones).
     * Ported from event-pdf.php.
     */
    public function get_event_timetable(): string {
        $slots_obj     = new Evtmgr_Slots();
        $workshops_obj = new Evtmgr_Workshops();
        $tz_obj        = new Evtmgr_Time_Zones();

        $tt_slots   = $slots_obj->get_slots_with_timezone($this->event_uid, $this->lang);
        $tt_parents = $tz_obj->get_time_zones_top($this->event_uid, $this->lang);
        $tt_col_cnt = count((array) $tt_slots);

        if ($tt_col_cnt === 0 || empty($tt_parents)) {
            return '<p>Kein Timetable vorhanden.</p>';
        }

        $active_parent_ids = array_values(array_unique(array_filter(
            array_map('absint', array_column((array) $tt_slots, 'fky_timezone_id'))
        )));

        $tt_header = '<tr><th class="tt-th-time">Zeit</th>';
        foreach ($tt_slots as $tsl) {
            $tsl_color_raw = trim((string) ($tsl['str_color'] ?? ''));
            $tsl_color     = $tsl_color_raw !== '' ? '#' . ltrim($tsl_color_raw, '#') : '';
            $tsl_style     = $tsl_color !== '' ? ' style="background-color:' . $this->e($tsl_color) . ';"' : '';
            $tt_header .= '<th class="tt-th-slot"' . $tsl_style . '>' . $this->e($tsl['str_slot_name'] ?? '') . '</th>';
        }
        $tt_header .= '</tr>';

        $tt_rows  = '';
        $tt_total = $tt_col_cnt + 1;

        foreach ($tt_parents as $tt_parent) {
            $tt_pid = absint($tt_parent['id'] ?? 0);
            if (!in_array($tt_pid, $active_parent_ids, true)) {
                continue;
            }

            $tt_pname    = $this->e($tt_parent['str_timezone_name'] ?? '');
            $tt_children = $tz_obj->get_time_zones_by_parent($tt_pid, $this->event_uid, $this->lang);

            $tt_section_rows   = '';
            $tt_section_has_ws = false;

            foreach ((array) $tt_children as $tt_child) {
                if (empty($tt_child['ysn_show_timezone_in_output'])) {
                    continue;
                }

                $tt_cid   = absint($tt_child['id'] ?? 0);
                $tt_tfrom = trim((string) ($tt_child['dtm_time_from'] ?? ''));
                $tt_tto   = trim((string) ($tt_child['dtm_time_to'] ?? ''));
                $tt_tfrom = strlen($tt_tfrom) >= 5 ? substr($tt_tfrom, 0, 5) : '';
                $tt_tto   = strlen($tt_tto) >= 5 ? substr($tt_tto, 0, 5) : '';
                $tt_time  = $tt_tfrom !== '' ? ($tt_tto !== '' ? $tt_tfrom . '–' . $tt_tto : $tt_tfrom) : '';
                $tt_cname = $this->e($tt_child['str_timezone_name'] ?? '');
                $is_fw    = !empty($tt_child['ysn_show_fullwidth']);

                $tt_cells      = array();
                $tt_has_any_ws = false;

                foreach ($tt_slots as $tsl) {
                    $tsl_id  = absint($tsl['id'] ?? 0);
                    $tt_ws   = $workshops_obj->get_workshops_by_slot($tsl_id, $tt_cid, $this->event_uid, $this->lang);
                    $tt_cell = '';

                    foreach ((array) $tt_ws as $ttw) {
                        $ttw_num   = trim((string) ($ttw['str_workshop_number'] ?? ''));
                        $ttw_title = $this->e(trim((string) ($ttw['str_workshop_title'] ?? '')));
                        if ($ttw_title === '') {
                            continue;
                        }
                        $tt_has_any_ws     = true;
                        $tt_section_has_ws = true;
                        $ttw_pres = $this->workshop_presenters(absint($ttw['id'] ?? 0));
                        $tt_cell .= '<div class="tt-ws">'
                            . ($ttw_num !== '' ? '<span class="tt-wnum">' . $this->e($ttw_num) . '</span> ' : '')
                            . $ttw_title
                            . ($ttw_pres !== '' ? '<div class="tt-ws-pres">' . $ttw_pres . '</div>' : '')
                            . '</div>';
                    }

                    $tt_cells[] = $tt_cell;
                }

                $tt_tcell = '<td class="tt-time">' . $this->e($tt_time) . '</td>';

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

            if (!$tt_section_has_ws) {
                continue;
            }

            if ($tt_pname !== '') {
                $tt_rows .= '<tr><td colspan="' . $tt_total . '" class="tt-section">' . $tt_pname . '</td></tr>';
            }
            $tt_rows .= $tt_section_rows;
        }

        return '<table class="tt-table"><thead>' . $tt_header . '</thead><tbody>' . $tt_rows . '</tbody></table>';
    }

    /**
     * Keynote-speaker list as the two-column table, grouped by slot. Column 2 is
     * "akad. Titel Vorname Nachname, Funktion, Firma" per presenter (no empty
     * commas). Falls back to the workshop title when no presenter is set.
     */
    public function get_event_keynote_speakers(): string {
        $html = $this->render_keynote_table(function (array $workshop): string {
            $speaker = implode('<br>', $this->presenter_labels_full(absint($workshop['id'] ?? 0)));

            if ($speaker === '') {
                $speaker = $this->e(trim((string) ($workshop['str_workshop_title'] ?? '')));
            }

            return $speaker;
        });

        return $html !== '' ? $html : '<p>Keine Keynote-Speaker vorhanden.</p>';
    }

    /**
     * Keynote list in the same two-column table. Column 2 is the workshop title,
     * then each presenter on its own line as
     * "akad. Titel Vorname Nachname, Funktion, Firma".
     */
    public function get_event_keynotes(): string {
        $html = $this->render_keynote_table(function (array $workshop): string {
            $title = trim((string) ($workshop['str_workshop_title'] ?? ''));
            if ($title === '') {
                return '';
            }

            $labels = $this->presenter_labels_full(absint($workshop['id'] ?? 0));
            if (empty($labels)) {
                return $this->e($title);
            }

            // Placeholder rows where the workshop title *is* the speaker/company:
            // drop the presenter line when title and presenter say the same thing.
            $norm  = static fn (string $s): string => trim(mb_strtolower(
                (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s)
            ));
            $t = $norm($title);
            $p = $norm(html_entity_decode(implode(' / ', $labels), ENT_QUOTES, 'UTF-8'));

            if (mb_strlen($t) >= 5 && ($p === $t || mb_strpos($p, $t) !== false || mb_strpos($t, $p) !== false)) {
                return $this->e($title);
            }

            return $this->e($title) . '<br>' . implode('<br>', $labels);
        });

        return $html !== '' ? $html : '<p>Keine Keynotes vorhanden.</p>';
    }

    /**
     * Shared layout for both keynote lists: workshops grouped by slot in a
     * two-column table (time | text), section rows tinted with the slot colour.
     *
     * @param callable $cell2  fn(array $workshop): string  – escaped HTML for
     *                         column 2, or '' to drop the row.
     */
    protected function render_keynote_table(callable $cell2): string {
        $slots_obj     = new Evtmgr_Slots();
        $workshops_obj = new Evtmgr_Workshops();

        $slots = $slots_obj->get_slots_for_output($this->event_uid, $this->lang);
        $rows  = '';

        foreach ((array) $slots as $slot) {
            $slot_id   = absint($slot['id'] ?? 0);
            $slot_name = trim((string) ($slot['str_slot_name'] ?? ''));

            if ($slot_id <= 0 || $slot_name === '') {
                continue;
            }

            $workshops = $workshops_obj->get_workshops_all_by_slot($slot_id, $this->event_uid, $this->lang);
            if (empty($workshops)) {
                continue;
            }

            $slot_rows = '';

            foreach ($workshops as $workshop) {
                $text = (string) $cell2($workshop);
                if ($text === '') {
                    continue;
                }

                $slot_rows .= '<tr class="kn-row">'
                    . '<td class="kn-time">' . $this->e($this->workshop_time_label($workshop)) . '</td>'
                    . '<td class="kn-text">' . $text . '</td>'
                    . '</tr>';
            }

            if ($slot_rows === '') {
                continue;
            }

            // Section header uses the slot's own colour (same source as the timetable).
            $slot_color = trim((string) ($slot['str_color'] ?? ''));
            $slot_style = '';
            if ($slot_color !== '') {
                $bg         = '#' . ltrim($slot_color, '#');
                $slot_style = ' style="background-color:' . $this->e($bg)
                    . ';color:' . $this->readable_text_color($bg) . ';"';
            }

            $rows .= '<tr class="kn-section"><td colspan="2"' . $slot_style . '>'
                . $this->e($slot_name) . '</td></tr>' . $slot_rows;
        }

        return $rows !== ''
            ? '<table class="kn-table"><tbody>' . $rows . '</tbody></table>'
            : '';
    }

    /** "HH:MM–HH:MM Uhr" (or "HH:MM Uhr", or '') for a workshop row. */
    protected function workshop_time_label(array $workshop): string {
        $from = trim((string) ($workshop['dtm_time_from'] ?? ''));
        $to   = trim((string) ($workshop['dtm_time_to'] ?? ''));
        $from = strlen($from) >= 5 ? substr($from, 0, 5) : '';
        $to   = strlen($to) >= 5 ? substr($to, 0, 5) : '';

        if ($from !== '' && $to !== '') {
            return $from . '–' . $to . ' Uhr';
        }
        return $from !== '' ? $from . ' Uhr' : '';
    }

    /**
     * Partner / sponsor logo grid, grouped by sponsor group.
     * Ported from event-pdf.php.
     */
    public function get_event_partners(): string {
        $sponsors_obj = new Evtmgr_Sponsors();
        $sponsors     = $sponsors_obj->get_sponsors_by_event_uid($this->event_uid, $this->lang);

        if (empty($sponsors)) {
            return '<p>Keine Partner vorhanden.</p>';
        }

        $upload_dir     = wp_upload_dir();
        $upload_baseurl = rtrim((string) ($upload_dir['baseurl'] ?? ''), '/');
        $upload_basedir = rtrim((string) ($upload_dir['basedir'] ?? ''), '/');

        $groups = array();
        foreach ($sponsors as $s) {
            $group            = trim((string) ($s['str_sponsor_group'] ?? ''));
            $groups[$group][] = $s;
        }

        $html = '';

        foreach ($groups as $group_label => $items) {
            $logo_items = '';

            foreach ($items as $s) {
                $logo_raw = trim((string) ($s['str_sponsor_logo'] ?? ''));
                $name_raw = trim((string) ($s['str_sponsor_name'] ?? ''));

                if ($logo_raw === '') {
                    continue;
                }

                if (ctype_digit($logo_raw)) {
                    $local_path = (string) get_attached_file((int) $logo_raw);
                } elseif (preg_match('#^https?://#i', $logo_raw) && $upload_baseurl !== '' && strpos($logo_raw, $upload_baseurl) === 0) {
                    $local_path = $upload_basedir . substr($logo_raw, strlen($upload_baseurl));
                } elseif (strpos($logo_raw, '/') === 0) {
                    $local_path = ABSPATH . ltrim($logo_raw, '/');
                } else {
                    $local_path = '';
                }

                if ($local_path === '' || !file_exists($local_path)) {
                    continue;
                }

                $src_attr = $this->file_to_data_uri($local_path);
                if ($src_attr === '') {
                    continue;
                }

                $alt_attr = $this->e($name_raw !== '' ? $name_raw : 'Logo');
                $logo_items .= '<div class="sp-item"><img src="' . $src_attr . '" alt="' . $alt_attr . '"></div>';
            }

            if ($logo_items === '') {
                continue;
            }

            if ($group_label !== '') {
                $html .= '<h3 class="sp-group-title">' . $this->e((string) $group_label) . '</h3>';
            }
            $html .= '<div class="sp-grid">' . $logo_items . '</div>';
        }

        return $html !== '' ? $html : '<p>Keine Partner vorhanden.</p>';
    }

    /**
     * Demo / layout-sample content: the raw HTML in
     * uploads/<event_uid>/pdf-templates/booklet/html-sample.htm, repeated
     * $repeat times (default 3, to show the page overflow).
     */
    public function get_demo_html(int $repeat = 3): string {
        $upload = wp_upload_dir();
        $file   = rtrim((string) ($upload['basedir'] ?? ''), '/\\')
            . DIRECTORY_SEPARATOR . $this->event_uid
            . DIRECTORY_SEPARATOR . 'pdf-templates'
            . DIRECTORY_SEPARATOR . 'booklet'
            . DIRECTORY_SEPARATOR . 'html-sample.htm';

        if (!is_readable($file)) {
            return '';
        }

        $html = (string) file_get_contents($file);

        return str_repeat($html, max(1, $repeat));
    }

    /**
     * All online workshops of one type, grouped by track (slot), as flowing
     * markup for the two-column content page. Per workshop: title, subtitle,
     * "Referent (Institution) · Zeit", and the description
     * (mem_workshop_description_<lang>).
     *
     * @param int|string $type  workshop type id, or its name (str_event_typename_<lang>)
     */
    public function get_workshops_by_type($type): string {
        global $wpdb;

        $lang = in_array($this->lang, array('de', 'fr', 'it'), true) ? $this->lang : 'de';

        if (is_numeric($type)) {
            $type_id = (int) $type;
        } else {
            $type_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}evtmgr_workshop_types
                  WHERE fky_event_uid = %s AND str_event_typename_{$lang} = %s
                  LIMIT 1",
                $this->event_uid,
                (string) $type
            ));
        }

        if ($type_id <= 0) {
            return '<p>Kein Workshop-Typ gefunden.</p>';
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT w.id,
                    w.fky_slot_id                      AS slot_id,
                    s.str_slot_name_{$lang}            AS slot_name,
                    s.str_color                        AS slot_color,
                    w.str_workshop_title_{$lang}       AS title,
                    w.str_workshop_subtitle_{$lang}    AS subtitle,
                    w.mem_workshop_description_{$lang} AS description,
                    tz.dtm_time_from,
                    tz.dtm_time_to
               FROM {$wpdb->prefix}evtmgr_workshops w
          LEFT JOIN {$wpdb->prefix}evtmgr_slots s      ON s.id = w.fky_slot_id
          LEFT JOIN {$wpdb->prefix}evtmgr_timezones tz ON tz.id = w.fky_timezone_id
              WHERE w.fky_event_uid = %s
                AND w.fky_workshop_type = %d
                AND w.ysn_online = 1
           ORDER BY s.int_sort, tz.dtm_time_from, w.str_workshop_number",
            $this->event_uid,
            $type_id
        ), ARRAY_A);

        if (empty($rows)) {
            return '<p>Keine Workshops vorhanden.</p>';
        }

        $groups = array();
        foreach ($rows as $r) {
            $groups[(int) ($r['slot_id'] ?? 0)][] = $r;
        }

        $html = '';

        foreach ($groups as $items) {
            $slot_name  = trim((string) ($items[0]['slot_name'] ?? ''));
            $slot_color = trim((string) ($items[0]['slot_color'] ?? ''));

            if ($slot_name !== '') {
                $style = '';
                if ($slot_color !== '') {
                    $bg    = '#' . ltrim($slot_color, '#');
                    $style = ' style="background-color:' . $this->e($bg)
                        . ';color:' . $this->readable_text_color($bg) . ';"';
                }
                $html .= '<h2 class="wt-track"' . $style . '>' . $this->e($slot_name) . '</h2>';
            }

            foreach ($items as $w) {
                $html .= $this->render_workshop_item($w);
            }
        }

        return $html !== '' ? $html : '<p>Keine Workshops vorhanden.</p>';
    }

    /**
     * The workshops one person has booked, rendered in the same `wt-*` markup as
     * get_workshops_by_type() (title, subtitle, description, presenter photo +
     * name + funktion/firma, time). Ordered chronologically.
     */
    public function get_person_booked_workshops(int $person_id): string {
        global $wpdb;

        if ($person_id <= 0) {
            return '<p>Keine gebuchten Angebote.</p>';
        }

        $lang = in_array($this->lang, array('de', 'fr', 'it'), true) ? $this->lang : 'de';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT w.id,
                    w.str_workshop_title_{$lang}       AS title,
                    w.str_workshop_subtitle_{$lang}    AS subtitle,
                    w.mem_workshop_description_{$lang} AS description,
                    tz.dtm_time_from,
                    tz.dtm_time_to
               FROM {$wpdb->prefix}evtmgr_registrations_workshops rw
         INNER JOIN {$wpdb->prefix}evtmgr_workshops w      ON w.id = rw.fky_workshop_id
          LEFT JOIN {$wpdb->prefix}evtmgr_timezones tz     ON tz.id = w.fky_timezone_id
              WHERE rw.fky_person_id = %d
                AND w.fky_event_uid  = %s
                AND w.ysn_no_registration_possible = 0
           ORDER BY tz.int_sort_order, tz.dtm_time_from, w.str_workshop_number",
            $person_id,
            $this->event_uid
        ), ARRAY_A);

        if (empty($rows)) {
            return '<p>Keine gebuchten Angebote.</p>';
        }

        $html = '';
        foreach ($rows as $w) {
            $html .= $this->render_workshop_item($w);
        }

        return $html;
    }

    /**
     * One workshop as a `.wt-item` block: title, subtitle, description, then each
     * presenter (round b&w photo left, name + funktion/firma right), then the time.
     * $w keys: id, title, subtitle, description, dtm_time_from, dtm_time_to.
     */
    protected function render_workshop_item(array $w): string {
        $title    = trim((string) ($w['title'] ?? ''));
        $subtitle = trim((string) ($w['subtitle'] ?? ''));
        $desc     = trim((string) ($w['description'] ?? ''));

        if ($title === '' && $desc === '') {
            return '';
        }

        $presenter_rows = $this->presenter_rows((int) ($w['id'] ?? 0));
        $time           = $this->e($this->workshop_time_label($w));

        $html = '<div class="wt-item">';
        if ($title !== '') {
            $html .= '<h3 class="wt-title">' . $this->e($title) . '</h3>';
        }
        if ($subtitle !== '') {
            $html .= '<div class="wt-subtitle">' . $this->e($subtitle) . '</div>';
        }
        if ($desc !== '') {
            $html .= '<div class="wt-desc">' . $desc . '</div>';
        }
        if (!empty($presenter_rows)) {
            $html .= '<div class="wt-meta">';
            foreach ($presenter_rows as $pr) {
                $text = '<span class="wt-presenter-name">' . $pr['name'] . '</span>';
                if ($pr['meta'] !== '') {
                    $text .= '<br>' . $pr['meta'];
                }
                $html .= '<div class="wt-presenter">'
                    . $pr['photo']
                    . '<span class="wt-presenter-text">' . $text . '</span>'
                    . '</div>';
            }
            $html .= '</div>';
        }
        if ($time !== '') {
            $html .= '<div class="wt-time">' . $time . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * QR code as a self-contained <img> (base64 data URI).
     * Defaults to the event URL when no target is passed.
     * Requires the chillerlan/php-qrcode autoloader to be loaded by the caller.
     */
    public function get_qr_code(string $target = ''): string {
        $target = trim($target) !== '' ? trim($target) : $this->get_event_url();

        if ($target === '' || !class_exists('\chillerlan\QRCode\QRCode')) {
            return '';
        }

        try {
            $qr_options               = new \chillerlan\QRCode\QROptions;
            $qr_options->eccLevel     = \chillerlan\QRCode\Common\EccLevel::L;
            $qr_options->addQuietzone = true;
            $qr_options->outputBase64 = true;

            $qr_uri = (new \chillerlan\QRCode\QRCode($qr_options))->render($target);

            return '<img src="' . $qr_uri . '" alt="QR Code">';
        } catch (\Throwable $e) {
            return '';
        }
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    /**
     * One escaped "Vorname Nachname (Institution)" label per presenter assigned
     * to a workshop. Institution is str_institution_<lang>, falling back to
     * str_employer when that is empty (the two usually hold the same value).
     *
     * @return string[]
     */
    protected function presenter_labels(int $workshop_id): array {
        if ($workshop_id <= 0) {
            return array();
        }

        $presenters = (new Evtmgr_Presenters())->get_presenters_by_workshop_id($workshop_id, $this->lang);
        $labels     = array();

        foreach ((array) $presenters as $pres) {
            $name        = trim((string) ($pres['str_first_name'] ?? '') . ' ' . (string) ($pres['str_last_name'] ?? ''));
            $institution = trim((string) ($pres['str_institution'] ?? ''));
            if ($institution === '') {
                $institution = trim((string) ($pres['str_employer'] ?? ''));
            }

            if ($name !== '' && $institution !== '' && strcasecmp($name, $institution) !== 0) {
                $labels[] = $this->e($name) . ' (' . $this->e($institution) . ')';
            } elseif ($name !== '') {
                $labels[] = $this->e($name);
            } elseif ($institution !== '') {
                $labels[] = $this->e($institution);
            }
        }

        return $labels;
    }

    /** Workshop presenters as one " · "-joined string (timetable cells). */
    protected function workshop_presenters(int $workshop_id): string {
        return implode(' · ', $this->presenter_labels($workshop_id));
    }

    /**
     * One escaped label per presenter, formatted as
     *   "akad. Titel Vorname Nachname, Funktion, Firma"
     * Funktion (str_job_title_<lang>) and Firma (str_institution_<lang> ?: str_employer)
     * are dropped together with their comma when empty.
     *
     * @return string[]
     */
    protected function presenter_labels_full(int $workshop_id): array {
        $labels = array();
        foreach ($this->presenter_rows($workshop_id) as $row) {
            $labels[] = $row['label'];
        }
        return $labels;
    }

    /**
     * Presenter rows for the workshop-list layout: each
     *   ['name'  => escaped "akad. Titel Vorname Nachname",
     *    'meta'  => escaped "Funktion, Firma" (or ''),
     *    'label' => escaped "akad. Titel Name, Funktion, Firma" (single line),
     *    'photo' => '<img class="wt-presenter-photo" …>' or '']
     *
     * @return array<int, array{name:string,meta:string,label:string,photo:string}>
     */
    protected function presenter_rows(int $workshop_id): array {
        if ($workshop_id <= 0) {
            return array();
        }

        $presenters = (new Evtmgr_Presenters())->get_presenters_by_workshop_id($workshop_id, $this->lang);
        $rows       = array();

        foreach ((array) $presenters as $pres) {
            $name = trim(implode(' ', array_filter(array(
                rtrim(trim((string) ($pres['str_academic_title'] ?? '')), " ,;"),
                trim((string) ($pres['str_first_name'] ?? '')),
                trim((string) ($pres['str_last_name'] ?? '')),
            ), 'strlen')));

            if ($name === '') {
                continue;
            }

            $company = trim((string) ($pres['str_institution'] ?? ''));
            if ($company === '') {
                $company = trim((string) ($pres['str_employer'] ?? ''));
            }
            $company = rtrim(trim($company), " ,;");

            $meta_parts = array();

            $job = rtrim(trim((string) ($pres['str_job_title'] ?? '')), " ,;");
            if ($job !== '') {
                $meta_parts[] = $job;
            }
            if ($company !== '' && strcasecmp($company, $name) !== 0) {
                $meta_parts[] = $company;
            }

            $meta  = implode(', ', $meta_parts);
            $label = $meta !== '' ? $name . ', ' . $meta : $name;

            $rows[] = array(
                'name'  => $this->e($name),
                'meta'  => $this->e($meta),
                'label' => $this->e($label),
                'photo' => $this->presenter_photo_img((string) ($pres['str_person_image'] ?? '')),
            );
        }

        return $rows;
    }

    /**
     * <img class="wt-presenter-photo"> (base64 data URI) for a presenter photo,
     * or '' when the file is missing. str_person_image may be a bare file name
     * (uploads root) or a path relative to the uploads dir
     * ("<event>/assets/presenter-images/foo.jpg"). Round / b&w is CSS.
     */
    protected function presenter_photo_img(string $file_name): string {
        $file_name = trim($file_name);
        if ($file_name === '') {
            return '';
        }

        $basedir = rtrim((string) (wp_upload_dir()['basedir'] ?? ''), '/\\');

        $candidates = array(
            $file_name,                                                              // stored path as-is
            $this->event_uid . '/assets/presenter-images/' . basename($file_name),   // per-event folder
            $this->event_uid . '/presenters/' . basename($file_name),                // legacy per-event folder
            basename($file_name),                                                    // uploads root
        );

        foreach ($candidates as $candidate) {
            $path = $basedir . DIRECTORY_SEPARATOR
                . ltrim(str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR);
            $uri  = $this->file_to_data_uri($path);
            if ($uri !== '') {
                return '<img class="wt-presenter-photo" src="' . $uri . '" alt="">';
            }
        }

        return '';
    }

    /** Black or white, whichever is readable on the given #RRGGBB background. */
    protected function readable_text_color(string $hex): string {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#000000';
        }
        $r   = hexdec(substr($hex, 0, 2));
        $g   = hexdec(substr($hex, 2, 2));
        $b   = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $lum > 0.6 ? '#000000' : '#ffffff';
    }

    /** @return mixed */
    protected function field(string $key, $default = '') {
        return Event_Registration_Helpers::value_ci($this->event ?? array(), $key, $default);
    }

    protected function e(?string $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function format_german_date(string $date_value): string {
        $date_value = trim($date_value);
        if ($date_value === '') {
            return '';
        }

        $ts = strtotime($date_value);
        if (!$ts) {
            return $date_value;
        }

        $day_name = self::GERMAN_DAYS[date('l', $ts)] ?? date('l', $ts);

        return $day_name . ', ' . date('j.n.Y', $ts);
    }

    protected function format_price(float $amount): string {
        if ($amount == 0) {
            return 'kostenlos';
        }
        if ($amount == floor($amount)) {
            return 'CHF ' . number_format($amount, 0, '.', "'") . '.–';
        }
        return 'CHF ' . number_format($amount, 2, '.', "'");
    }

    protected function file_to_data_uri(string $path): string {
        if (!file_exists($path)) {
            return '';
        }

        $data = file_get_contents($path);
        if ($data === false) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
