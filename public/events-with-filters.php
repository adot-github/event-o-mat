<?php
/**
 * All workshops of an event as Bootstrap cards, with a filter bar
 * (free-text search, category, workshop type, presenter) above them.
 *
 * Usage as shortcode:
 * [events_with_filters event_uid='fhnw-bgf-2026' type='']
 *
 * The "type" attribute is optional and takes one or more workshop type
 * IDs (comma separated), pre-selecting the type filter. It only applies
 * as long as the visitor hasn't picked their own type filter yet.
 *
 * Each card reuses public/registration/_workshop.php for its content,
 * so the markup stays identical to the workshop selection step.
 *
 * Filter bar visually/functionally adapted from
 * db-custom/mks/public/unterrichtsideen-cards__filters.php
 * (musikinderschule.dev/unterrichtsideen/): a GET-submitted form with
 * Select2 v3.5.2 multi-selects and a "clean filter" clear button.
 */

if (!defined('ABSPATH')) {
    exit;
}

$_ewf_classes_dir = dirname(__DIR__) . '/classes/';
require_once $_ewf_classes_dir . 'class-helpers.php';
require_once $_ewf_classes_dir . 'class-evtmgr-workshops.php';
require_once $_ewf_classes_dir . 'class-evtmgr-presenters.php';
require_once $_ewf_classes_dir . 'class-evtmgr-time-zones.php';
require_once $_ewf_classes_dir . 'class-evtmgr-rooms.php';
require_once $_ewf_classes_dir . 'class-evtmgr-audience.php';
require_once $_ewf_classes_dir . 'class-evtmgr-wordings.php';
require_once $_ewf_classes_dir . 'class-evtmgr-workshop-likes.php';

add_action('init', function () {
    add_shortcode('events_with_filters', 'events_with_filters_shortcode');
});

if (!function_exists('events_with_filters_render_workshop_html')) {
    function events_with_filters_render_workshop_html($workshop_id, $lang, array $wordings = array(), $is_liked = false, $workshop_layout = 'card') {
        $id                = absint($workshop_id);
        $str_slot_color    = 'eeeeee';
        $show_like_button  = true;
        $pdf_link_option   = 'link_pdf_in_filter_page';

        if ($id <= 0) {
            return '';
        }

        ob_start();
        include __DIR__ . '/registration/_workshop.php';
        return trim((string) ob_get_clean());
    }
}

if (!function_exists('events_with_filters_card_item_html')) {
    /** One offer as card (grid column). Shared with [events_by_slot]. */
    function events_with_filters_card_item_html($workshop_html) {
        return <<<HTML

        <div class="col">
            <div class="card h-100 events-with-filters-card">
                <div class="card-body">
                    {$workshop_html}
                </div>
            </div>
        </div>
HTML;
    }
}

if (!function_exists('events_with_filters_accordion_item_html')) {
    /**
     * One offer as accordion item: "number | title" in the header, the
     * workshop partial (layout 'accordion') in the body. Shared with
     * [events_by_slot]. $workshop needs str_workshop_title / str_workshop_number.
     */
    function events_with_filters_accordion_item_html($item_id, array $workshop, $workshop_html) {
        $item_title = trim((string) ($workshop['str_workshop_title'] ?? ''));
        $item_no    = trim((string) ($workshop['str_workshop_number'] ?? ''));
        $item_title = esc_html($item_no !== '' ? $item_no . ' | ' . $item_title : $item_title);
        $item_id    = esc_attr($item_id);

        return <<<HTML

        <div class="accordion-item events-with-filters-accordion-item">
            <h2 class="accordion-header" id="{$item_id}-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{$item_id}" aria-expanded="false" aria-controls="{$item_id}">
                    {$item_title}
                </button>
            </h2>
            <div id="{$item_id}" class="accordion-collapse collapse" aria-labelledby="{$item_id}-heading">
                <div class="accordion-body">
                    {$workshop_html}
                </div>
            </div>
        </div>
HTML;
    }
}

if (!function_exists('events_with_filters_options_html')) {
    function events_with_filters_options_html(array $options, $selected_ids) {
        $html = '<option value="" disabled>Alle</option>';

        foreach ($options as $option) {
            $is_selected = in_array((int) $option['id'], $selected_ids, true);

            $html .= '<option value="' . esc_attr($option['id']) . '"' . ($is_selected ? ' selected="selected"' : '') . '>'
                . esc_html($option['label'])
                . '</option>';
        }

        return $html;
    }
}

function events_with_filters_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'event_uid' => '',
            'lang'      => 'de',
            'type'            => '',
            'show_filters'    => '1',
            'type_of_display' => 'cards',
        ),
        $atts,
        'events_with_filters'
    );

    $event_uid     = sanitize_text_field((string) $atts['event_uid']);
    $lang          = sanitize_key((string) $atts['lang']);
    $default_types = array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $atts['type'])))));

    // show_filters=0 → no filter form and no result count; the GET filter
    // parameters are ignored too, so only the block's type selection applies.
    $show_filters    = !in_array(strtolower(trim((string) $atts['show_filters'])), array('0', 'false', 'no', 'nein'), true);
    $type_of_display = sanitize_key((string) $atts['type_of_display']) === 'accordion' ? 'accordion' : 'cards';

    Event_Registration_Helpers::enqueue_bootstrap($event_uid, true);

    if ($event_uid === '') {
        return '';
    }

    $workshops_obj  = new Evtmgr_Workshops();
    $presenters_obj = new Evtmgr_Presenters();
    $wordings_obj   = new Evtmgr_Wordings();
    $likes_obj      = new Evtmgr_Workshop_Likes();

    $wordings       = $wordings_obj->get_wordings($lang, $event_uid);
    $visitor_cookie = $likes_obj->get_or_create_visitor_cookie();

    // ── GET-Parameter des Filterformulars ────────────────────────────────────
    $search = $show_filters && isset($_GET['ewf_search'])
        ? sanitize_text_field(wp_unslash($_GET['ewf_search']))
        : '';

    $ewf_filters = $show_filters && isset($_GET['ewf_filters']) && is_array($_GET['ewf_filters'])
        ? wp_unslash($_GET['ewf_filters'])
        : array();

    // The block's types are only a preselection for the first visit: once the
    // form was submitted (ewf_submitted), an empty selection means "all" —
    // otherwise a deselected type would come back on every submit, since a
    // multi-select with nothing chosen sends no parameter at all.
    $form_submitted = $show_filters && isset($_GET['ewf_submitted']);

    $selected_types = isset($ewf_filters['types'])
        ? array_values(array_filter(array_map('absint', (array) $ewf_filters['types'])))
        : ($form_submitted ? array() : $default_types);

    $selected_categories = isset($ewf_filters['categories'])
        ? array_values(array_filter(array_map('absint', (array) $ewf_filters['categories'])))
        : array();

    $selected_presenters = isset($ewf_filters['presenters'])
        ? array_values(array_filter(array_map('absint', (array) $ewf_filters['presenters'])))
        : array();

    $only_liked = $show_filters && isset($_GET['ewf_liked']) && wp_unslash($_GET['ewf_liked']) === '1';

    // ── Filteroptionen laden ──────────────────────────────────────────────────
    $category_options = array_map(
        static fn($row) => array('id' => $row['id'], 'label' => $row['str_category_name']),
        $workshops_obj->get_all_categories_for_event($event_uid, $lang)
    );

    $type_options = array_map(
        static fn($row) => array('id' => $row['id'], 'label' => $row['str_type_name']),
        $workshops_obj->get_workshop_types_for_output($event_uid, $lang)
    );

    $presenter_options = array_map(
        static fn($row) => array(
            'id'    => $row['id'],
            'label' => trim(($row['str_first_name'] ?? '') . ' ' . ($row['str_last_name'] ?? '')),
        ),
        $presenters_obj->get_presenters_for_event($event_uid)
    );

    // ── Workshops laden ──────────────────────────────────────────────────────
    $liked_workshop_ids = $likes_obj->get_liked_workshop_ids($event_uid, $visitor_cookie);

    $workshops = $workshops_obj->get_filtered_workshops(
        $event_uid,
        $lang,
        $search,
        $selected_types,
        $selected_categories,
        $selected_presenters
    );

    if ($only_liked) {
        $workshops = array_values(array_filter(
            $workshops,
            static fn($workshop) => in_array((int) ($workshop['id'] ?? 0), $liked_workshop_ids, true)
        ));
    }

    // ── Karten / Akkordeon rendern ────────────────────────────────────────────
    // Unique ids per shortcode instance (several blocks on one page).
    static $ewf_instance = 0;
    $ewf_instance++;
    $accordion_id = 'ewf-accordion-' . $ewf_instance;

    $cards_html = '';

    foreach ($workshops as $workshop) {
        $workshop_id = absint($workshop['id'] ?? 0);

        if ($workshop_id <= 0) {
            continue;
        }

        $is_liked      = in_array($workshop_id, $liked_workshop_ids, true);
        $workshop_html = events_with_filters_render_workshop_html(
            $workshop_id,
            $lang,
            $wordings,
            $is_liked,
            $type_of_display === 'accordion' ? 'accordion' : 'card'
        );

        if ($workshop_html === '') {
            continue;
        }

        if ($type_of_display === 'accordion') {
            $cards_html .= events_with_filters_accordion_item_html($accordion_id . '-' . $workshop_id, $workshop, $workshop_html);
            continue;
        }

        $cards_html .= events_with_filters_card_item_html($workshop_html);
    }

    // The filter form submits with method="get". A GET submit discards the
    // action URL's query string, so on a site with plain permalinks the
    // page identifier (?page_id=10) would be lost and the shortcode page
    // never reached. Point the form at the page permalink and re-add any
    // query args it carries (page_id / p / …) as hidden fields.
    $form_action      = esc_url(get_permalink(get_queried_object_id()));
    $preserved_fields = '';
    $permalink_query  = wp_parse_url((string) get_permalink(get_queried_object_id()), PHP_URL_QUERY);

    if (is_string($permalink_query) && $permalink_query !== '') {
        $permalink_args = array();
        parse_str($permalink_query, $permalink_args);

        foreach ($permalink_args as $arg_key => $arg_value) {
            if (is_scalar($arg_value)) {
                $preserved_fields .= '<input type="hidden" name="' . esc_attr((string) $arg_key)
                    . '" value="' . esc_attr((string) $arg_value) . '">';
            }
        }
    }

    $result_count  = count($workshops);
    $search_attr   = esc_attr($search);
    $liked_checked = $only_liked ? ' checked="checked"' : '';

    // Each multi-select filter is only rendered when it offers a real choice,
    // i.e. more than one option. A filter with 0 or 1 options can never narrow
    // the result, so it stays hidden.
    $filter_defs = array(
        array(
            'options'  => $category_options,
            'selected' => $selected_categories,
            'name'     => 'ewf_filters[categories][]',
            'id'       => 'ewf_filter_categories',
            'label'    => 'Kategorie',
        ),
        array(
            'options'  => $type_options,
            'selected' => $selected_types,
            'name'     => 'ewf_filters[types][]',
            'id'       => 'ewf_filter_types',
            'label'    => 'Event-Typ',
        ),
        array(
            'options'  => $presenter_options,
            'selected' => $selected_presenters,
            'name'     => 'ewf_filters[presenters][]',
            'id'       => 'ewf_filter_presenters',
            'label'    => 'Referent*in',
        ),
    );

    $filter_selects_html = '';

    foreach ($filter_defs as $filter_def) {
        if (count($filter_def['options']) <= 1) {
            continue;
        }

        $options_ui = events_with_filters_options_html($filter_def['options'], $filter_def['selected']);
        $field_name = esc_attr($filter_def['name']);
        $field_id   = esc_attr($filter_def['id']);
        $field_lbl  = esc_html($filter_def['label']);

        $filter_selects_html .= <<<HTML
                <div class="col-md-4 mb-3">
                    <div class="form-group floating-label select-container">
                        <select name="{$field_name}" id="{$field_id}" multiple="multiple" class="form-control dirty" placeholder="Alle" data-placeholder="Alle">
                            {$options_ui}
                        </select>
                        <label for="{$field_id}">{$field_lbl}</label>
                        <div class="clean-filter"></div>
                    </div>
                </div>
HTML;
    }

    $filter_selects_row = $filter_selects_html !== ''
        ? "<div class=\"row\">{$filter_selects_html}\n            </div>"
        : '';

    $filters_html = <<<HTML
    <div class="events-with-filters-form-wrapper mb-4">
        <form name="events_with_filters_form" method="get" action="{$form_action}" class="events-with-filters-form">
            {$preserved_fields}
            <input type="hidden" name="ewf_submitted" value="1">
            <div class="row mb-3">
                <div class="col-md-4 pt-2">
                    <div class="input-group input-container">
                        <input type="text" class="form-control" name="ewf_search" value="{$search_attr}" placeholder="Suche" style="max-width: 400px;">
                        <div class="clean-filter"></div>
                    </div>
                </div>
                <div class="col-md-3 pt-2 d-flex align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ewf_liked" value="1" id="ewf_liked_checkbox"{$liked_checked}>
                        <label class="form-check-label" for="ewf_liked_checkbox">
                            Nur Angebote in der Merkliste
                        </label>
                    </div>
                </div>
                <div class="col-md-3 pt-2">
                    <div class="input-group justify-content-end h-100">
                        <button type="submit" class="btn btn-primary text-nowrap">Suche starten</button>
                    </div>
                </div>
            </div>
            {$filter_selects_row}
        </form>
    </div>
HTML;

    if ($cards_html === '') {
        $cards_section = '';
    } elseif ($type_of_display === 'accordion') {
        $cards_section = <<<HTML
        <div class="events-with-filters-wrapper events-with-filters-accordion-wrapper">
            <div class="accordion events-with-filters-accordion" id="{$accordion_id}">{$cards_html}
            </div>
        </div>
HTML;
    } else {
        $cards_section = <<<HTML
        <div class="events-with-filters-wrapper mx-n2">
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mx-0">{$cards_html}
            </div>
        </div>
HTML;
    }

    if (!$show_filters) {
        return $cards_section;
    }

    return <<<HTML
    {$filters_html}
    <div class="events-with-filters-result-count">{$result_count} Angebote gefunden</div>
    {$cards_section}
HTML;
}
