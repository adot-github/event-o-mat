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
    function events_with_filters_render_workshop_html($workshop_id, $lang, array $wordings = array(), $is_liked = false) {
        $id                = absint($workshop_id);
        $str_slot_color    = 'eeeeee';
        $show_like_button  = true;

        if ($id <= 0) {
            return '';
        }

        ob_start();
        include __DIR__ . '/registration/_workshop.php';
        return trim((string) ob_get_clean());
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
            'type'      => '',
        ),
        $atts,
        'events_with_filters'
    );

    $event_uid     = sanitize_text_field((string) $atts['event_uid']);
    $lang          = sanitize_key((string) $atts['lang']);
    $default_types = array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $atts['type'])))));

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
    $search = isset($_GET['ewf_search'])
        ? sanitize_text_field(wp_unslash($_GET['ewf_search']))
        : '';

    $ewf_filters = isset($_GET['ewf_filters']) && is_array($_GET['ewf_filters'])
        ? wp_unslash($_GET['ewf_filters'])
        : array();

    $selected_types = isset($ewf_filters['types'])
        ? array_values(array_filter(array_map('absint', (array) $ewf_filters['types'])))
        : $default_types;

    $selected_categories = isset($ewf_filters['categories'])
        ? array_values(array_filter(array_map('absint', (array) $ewf_filters['categories'])))
        : array();

    $selected_presenters = isset($ewf_filters['presenters'])
        ? array_values(array_filter(array_map('absint', (array) $ewf_filters['presenters'])))
        : array();

    $only_liked = isset($_GET['ewf_liked']) && wp_unslash($_GET['ewf_liked']) === '1';

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

    // ── Karten rendern ────────────────────────────────────────────────────────
    $cards_html = '';

    foreach ($workshops as $workshop) {
        $workshop_id = absint($workshop['id'] ?? 0);

        if ($workshop_id <= 0) {
            continue;
        }

        $is_liked      = in_array($workshop_id, $liked_workshop_ids, true);
        $workshop_html = events_with_filters_render_workshop_html($workshop_id, $lang, $wordings, $is_liked);

        if ($workshop_html === '') {
            continue;
        }

        $cards_html .= <<<HTML

        <div class="col">
            <div class="card h-100 events-with-filters-card">
                <div class="card-body">
                    {$workshop_html}
                </div>
            </div>
        </div>
HTML;
    }

    $result_count  = count($workshops);
    $search_attr   = esc_attr($search);
    $liked_checked = $only_liked ? ' checked="checked"' : '';
    $categories_ui = events_with_filters_options_html($category_options, $selected_categories);
    $types_ui      = events_with_filters_options_html($type_options, $selected_types);
    $presenters_ui = events_with_filters_options_html($presenter_options, $selected_presenters);

    $filters_html = <<<HTML
    <div class="events-with-filters-form-wrapper mb-4">
        <form name="events_with_filters_form" method="get" class="events-with-filters-form">
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
                            Nur gemerkte Angebote
                        </label>
                    </div>
                </div>
                <div class="col-md-3 pt-2">
                    <div class="input-group justify-content-end h-100">
                        <button type="submit" class="btn btn-primary text-nowrap">Suche starten</button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="form-group floating-label select-container">
                        <select name="ewf_filters[categories][]" id="ewf_filter_categories" multiple="multiple" class="form-control dirty" placeholder="Alle">
                            {$categories_ui}
                        </select>
                        <label for="ewf_filter_categories">Kategorie</label>
                        <div class="clean-filter"></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-group floating-label select-container">
                        <select name="ewf_filters[types][]" id="ewf_filter_types" multiple="multiple" class="form-control dirty" placeholder="Alle">
                            {$types_ui}
                        </select>
                        <label for="ewf_filter_types">Workshop-Typ</label>
                        <div class="clean-filter"></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-group floating-label select-container">
                        <select name="ewf_filters[presenters][]" id="ewf_filter_presenters" multiple="multiple" class="form-control dirty" placeholder="Alle">
                            {$presenters_ui}
                        </select>
                        <label for="ewf_filter_presenters">Referent*in</label>
                        <div class="clean-filter"></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
HTML;

    $cards_section = $cards_html !== ''
        ? <<<HTML
        <div class="events-with-filters-wrapper mx-n2">
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mx-0">{$cards_html}
            </div>
        </div>
HTML
        : '';

    return <<<HTML
    {$filters_html}
    <div class="events-with-filters-result-count">{$result_count} Angebote gefunden</div>
    {$cards_section}
HTML;
}
