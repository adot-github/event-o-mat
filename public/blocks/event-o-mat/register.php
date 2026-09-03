<?php
/**
 * Editor-Blöcke "Event-o-mat".
 *
 * Registriert pro öffentlichem Shortcode (event_registration, events_with_filters,
 * price_map, …) einen dynamischen Block in einer gemeinsamen Block-Kategorie
 * "Event-o-mat". Redakteur:innen wählen den Inhalt also im "+"-Menü aus und
 * füllen die Parameter (Kongress, Sprache, Workshop-Typen …) über Dropdowns in
 * der Block-Seitenleiste – statt Shortcode-Syntax zu tippen.
 *
 * Frontend-Ausgabe = der passende Shortcode via do_shortcode(). Im Editor zeigt
 * ein ServerSideRender eine Live-Vorschau.
 *
 * Einzige Wahrheitsquelle für die Block-Liste (Labels, Icons, erlaubte
 * Parameter) ist event_o_mat_block_map(); dieselben Daten gehen via
 * wp_localize_script() an editor.js.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** event-registration-Wurzel (…/db-custom/event-registration). */
if (!defined('EVENT_O_MAT_BLOCK_ER_ROOT')) {
    define('EVENT_O_MAT_BLOCK_ER_ROOT', dirname(__DIR__, 3));
}

/**
 * Die zu registrierenden Blöcke. Schlüssel = Shortcode-Tag;
 * Blockname = "event-o-mat/<Tag mit Bindestrichen>".
 *
 * @return array<string, array{label:string, description:string, icon:string, params:string[]}>
 */
function event_o_mat_block_map() {
    return array(
        'event_registration' => array(
            'label'       => 'Anmeldung/Registrierung',
            'description' => 'Das mehrstufige Anmeldeformular für einen Kongress.',
            'icon'        => 'forms',
            'params'      => array('event_uid', 'lang'),
        ),
        'events_with_filters' => array(
            'label'       => 'Workshops mit Filter',
            'description' => 'Workshop-Liste mit Filter- und Merkfunktion.',
            'icon'        => 'filter',
            'params'      => array('event_uid', 'lang', 'type'),
        ),
        'events_by_workshop_type' => array(
            'label'       => 'Workshops nach Typ (Akkordeon)',
            'description' => 'Workshops gruppiert nach Workshop-Typ, je Angebot ein Akkordeon.',
            'icon'        => 'list-view',
            'params'      => array('event_uid', 'lang', 'type'),
        ),
        'presenters_by_slot' => array(
            'label'       => 'Referent:innen nach Slot',
            'description' => 'Referent:innen gruppiert nach Zeit-Slot, mit Foto und Bio.',
            'icon'        => 'groups',
            'params'      => array('event_uid', 'lang'),
        ),
        'presenters_by_workshop_type' => array(
            'label'       => 'Referent:innen nach Workshop-Typ',
            'description' => 'Referent:innen gruppiert nach Workshop-Typ, mit Foto und Bio.',
            'icon'        => 'groups',
            'params'      => array('event_uid', 'lang', 'type'),
        ),
        'sponsor_wall' => array(
            'label'       => 'Partner / Sponsoren – Grid',
            'description' => 'Partner-Logos als responsives Raster, nach Gruppe sortiert.',
            'icon'        => 'grid-view',
            'params'      => array('event_uid', 'lang'),
        ),
        'sponsor_ticker' => array(
            'label'       => 'Partner / Sponsoren – Laufband',
            'description' => 'Partner-Logos als endlos laufendes Band.',
            'icon'        => 'controls-repeat',
            'params'      => array('event_uid', 'lang', 'speed'),
        ),
        'price_map' => array(
            'label'       => 'Preise/Anmeldungstabelle',
            'description' => 'Die Preistabelle des Kongresses (identisch zum Booklet).',
            'icon'        => 'money-alt',
            'params'      => array('event_uid', 'lang'),
        ),
        'event_keyfigures' => array(
            'label'       => 'Event-Eckdaten',
            'description' => 'Titel, Untertitel, Beschreibung, Datum und Anmeldefristen des Kongresses.',
            'icon'        => 'info-outline',
            'params'      => array('event_uid', 'lang'),
        ),
        'evtmgr_checkin_app' => array(
            'label'       => 'Check-in-App',
            'description' => 'Die QR-Code-Check-in-App für den Event-Empfang.',
            'icon'        => 'yes-alt',
            'params'      => array('event_uid', 'lang'),
        ),
    );
}

/** Blockname (namespace/name) für ein Shortcode-Tag. */
function event_o_mat_block_name($tag) {
    return 'event-o-mat/' . str_replace('_', '-', (string) $tag);
}

/** Shortcode-Tag für einen Blocknamen, oder '' wenn es keiner unserer Blöcke ist. */
function event_o_mat_block_tag_from_name($block_name) {
    $block_name = (string) $block_name;

    if (strpos($block_name, 'event-o-mat/') !== 0) {
        return '';
    }

    $slug = substr($block_name, strlen('event-o-mat/'));

    foreach (array_keys(event_o_mat_block_map()) as $tag) {
        if (str_replace('_', '-', $tag) === $slug) {
            return $tag;
        }
    }

    return '';
}

// ── Block-Kategorie im "+"-Menü ──────────────────────────────────────────────

add_filter('block_categories_all', function ($categories) {
    foreach ((array) $categories as $category) {
        if (isset($category['slug']) && $category['slug'] === 'event-o-mat') {
            return $categories;
        }
    }

    array_unshift($categories, array(
        'slug'  => 'event-o-mat',
        'title' => 'Event-o-mat',
        'icon'  => null,
    ));

    return $categories;
}, 10, 1);

// ── Editor-Assets registrieren ──────────────────────────────────────────────

add_action('init', function () {
    $rel = '/db-custom/event-registration/public/blocks/event-o-mat';
    $dir = get_stylesheet_directory() . $rel;
    $uri = get_stylesheet_directory_uri() . $rel;

    wp_register_script(
        'event-o-mat-blocks',
        $uri . '/editor.js',
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-api-fetch'),
        file_exists($dir . '/editor.js') ? filemtime($dir . '/editor.js') : '1',
        true
    );

    wp_register_style(
        'event-o-mat-blocks',
        $uri . '/editor.css',
        array(),
        file_exists($dir . '/editor.css') ? filemtime($dir . '/editor.css') : '1'
    );
}, 5);

// ── Blöcke registrieren (einer pro Shortcode) ───────────────────────────────

add_action('init', function () {
    $common_attributes = array(
        'eventUid' => array('type' => 'string', 'default' => ''),
        'lang'     => array('type' => 'string', 'default' => 'de'),
        'type'     => array('type' => 'string', 'default' => ''),
        'speed'    => array('type' => 'number', 'default' => 60),
    );

    foreach (event_o_mat_block_map() as $tag => $def) {
        register_block_type(event_o_mat_block_name($tag), array(
            'api_version'     => 2,
            'title'           => $def['label'],
            'description'     => $def['description'],
            'category'        => 'event-o-mat',
            'icon'            => $def['icon'],
            'editor_script'   => 'event-o-mat-blocks',
            'editor_style'    => 'event-o-mat-blocks',
            'attributes'      => $common_attributes,
            'supports'        => array(
                'html'     => false,
                'reusable' => true,
                'multiple' => true,
            ),
            'render_callback' => function ($attributes) use ($tag) {
                return event_o_mat_block_render($tag, $attributes);
            },
        ));
    }
});

/**
 * Frontend-/Vorschau-Ausgabe eines Blocks: baut den Shortcode-String und gibt
 * do_shortcode() zurück. Im Editor (REST) statt leerem String ein Hinweis.
 *
 * @param string              $tag
 * @param array<string, mixed> $attributes
 */
function event_o_mat_block_render($tag, $attributes) {
    $map = event_o_mat_block_map();
    $tag = sanitize_key((string) $tag);

    if (!isset($map[$tag])) {
        return '';
    }

    $params     = $map[$tag]['params'];
    $attributes = is_array($attributes) ? $attributes : array();
    $in_editor  = defined('REST_REQUEST') && REST_REQUEST;

    $event_uid = isset($attributes['eventUid']) ? sanitize_text_field((string) $attributes['eventUid']) : '';
    $lang      = isset($attributes['lang']) ? sanitize_key((string) $attributes['lang']) : 'de';
    if ($lang === '') {
        $lang = 'de';
    }

    if ($event_uid === '') {
        return $in_editor
            ? '<div class="event-o-mat-block__notice">' . esc_html__('Bitte in der Seitenleiste einen Kongress auswählen.', 'event-registration') . '</div>'
            : '';
    }

    $atts  = ' event_uid="' . esc_attr($event_uid) . '"';
    $atts .= ' lang="' . esc_attr($lang) . '"';

    if (in_array('type', $params, true)) {
        $type = isset($attributes['type']) ? preg_replace('/[^0-9,]/', '', (string) $attributes['type']) : '';
        $type = trim((string) $type, ',');
        if ($type !== '') {
            $atts .= ' type="' . esc_attr($type) . '"';
        }
    }

    if (in_array('speed', $params, true)) {
        $speed = isset($attributes['speed']) ? absint($attributes['speed']) : 0;
        if ($speed > 0) {
            $atts .= ' speed="' . esc_attr($speed) . '"';
        }
    }

    $output = do_shortcode('[' . $tag . $atts . ']');

    if (trim((string) $output) === '' && $in_editor) {
        return '<div class="event-o-mat-block__notice">'
            . esc_html__('Für diesen Kongress / diese Auswahl gibt es momentan nichts anzuzeigen.', 'event-registration')
            . '</div>';
    }

    return $output;
}

// ── Editor-Daten (Kongress-Liste + Sprachen) an editor.js ───────────────────

add_action('enqueue_block_editor_assets', function () {
    if (!wp_script_is('event-o-mat-blocks', 'registered')) {
        return;
    }

    // Make sure our script/style ship on every block-editor screen (post editor,
    // site editor, widgets) so all nine blocks appear in the inserter.
    wp_enqueue_script('event-o-mat-blocks');
    wp_enqueue_style('event-o-mat-blocks');

    $events = array();

    require_once EVENT_O_MAT_BLOCK_ER_ROOT . '/classes/class-evtmgr-events.php';

    if (class_exists('Evtmgr_Events')) {
        $rows = (new Evtmgr_Events())->get_events_all('de');

        foreach ((array) $rows as $row) {
            $uid = trim((string) ($row['event_uid'] ?? ''));
            if ($uid === '') {
                continue;
            }
            $name     = trim((string) ($row['str_event_name'] ?? ''));
            $events[] = array(
                'uid'   => $uid,
                'label' => $name !== '' ? $name . ' — ' . $uid : $uid,
            );
        }
    }

    wp_localize_script('event-o-mat-blocks', 'eventOMatBlocks', array(
        'blocks'    => event_o_mat_block_map(),
        'events'    => $events,
        'languages' => array(
            array('value' => 'de', 'label' => 'Deutsch'),
            array('value' => 'fr', 'label' => 'Français'),
            array('value' => 'it', 'label' => 'Italiano'),
            array('value' => 'en', 'label' => 'English'),
        ),
    ));
});

// ── REST: Workshop-Typen eines Kongresses (für das Typ-Dropdown) ────────────

add_action('rest_api_init', function () {
    register_rest_route('event-o-mat/v1', '/workshop-types', array(
        'methods'             => 'GET',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args'                => array(
            'event_uid' => array('required' => true),
            'lang'      => array('required' => false, 'default' => 'de'),
        ),
        'callback'            => function (WP_REST_Request $request) {
            $event_uid = sanitize_text_field((string) $request->get_param('event_uid'));
            $lang      = sanitize_key((string) $request->get_param('lang'));
            if ($lang === '') {
                $lang = 'de';
            }

            if ($event_uid === '') {
                return rest_ensure_response(array());
            }

            require_once EVENT_O_MAT_BLOCK_ER_ROOT . '/classes/class-evtmgr-workshops.php';

            $out = array();

            if (class_exists('Evtmgr_Workshops')) {
                $types = (new Evtmgr_Workshops())->get_workshop_types_for_output($event_uid, $lang);

                foreach ((array) $types as $type) {
                    $id   = absint($type['id'] ?? 0);
                    $name = trim((string) ($type['str_type_name'] ?? ''));
                    if ($id > 0 && $name !== '') {
                        $out[] = array('id' => $id, 'name' => $name);
                    }
                }
            }

            return rest_ensure_response($out);
        },
    ));
});

// ── Helfer: Blöcke in einem Beitrag erkennen (für die Asset-Ladelogik) ──────

/**
 * Alle Event-o-mat-Shortcode-Tags, die als Block im Beitrag vorkommen.
 *
 * @return string[]
 */
function event_o_mat_page_block_tags($post = null) {
    static $cache = array();

    $post_obj = get_post($post);
    $key      = $post_obj ? (int) $post_obj->ID : 0;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $tags = array();

    if ($post_obj && !empty($post_obj->post_content) && strpos($post_obj->post_content, '<!-- wp:event-o-mat/') !== false) {
        event_o_mat_block_collect_tags(parse_blocks($post_obj->post_content), $tags);
    }

    $cache[$key] = array_values(array_unique($tags));

    return $cache[$key];
}

/** @param array<int,array<string,mixed>> $blocks */
function event_o_mat_block_collect_tags($blocks, array &$tags) {
    foreach ((array) $blocks as $block) {
        $tag = event_o_mat_block_tag_from_name($block['blockName'] ?? '');
        if ($tag !== '') {
            $tags[] = $tag;
        }
        if (!empty($block['innerBlocks'])) {
            event_o_mat_block_collect_tags($block['innerBlocks'], $tags);
        }
    }
}

/** Ob der Beitrag (irgend)einen Event-o-mat-Block – oder den für $tag – enthält. */
function event_o_mat_page_has_block($tag = null, $post = null) {
    $tags = event_o_mat_page_block_tags($post);

    if ($tag === null) {
        return !empty($tags);
    }

    return in_array($tag, $tags, true);
}

/** event_uid des ersten Event-o-mat-Blocks im Beitrag (für die Bootstrap-Ladelogik). */
function event_o_mat_page_event_uid($post = null) {
    $post_obj = get_post($post);

    if (!$post_obj || empty($post_obj->post_content) || strpos($post_obj->post_content, '<!-- wp:event-o-mat/') === false) {
        return '';
    }

    return event_o_mat_block_scan_uid(parse_blocks($post_obj->post_content));
}

/** @param array<int,array<string,mixed>> $blocks */
function event_o_mat_block_scan_uid($blocks) {
    foreach ((array) $blocks as $block) {
        if (event_o_mat_block_tag_from_name($block['blockName'] ?? '') !== '' && !empty($block['attrs']['eventUid'])) {
            return sanitize_text_field((string) $block['attrs']['eventUid']);
        }
        if (!empty($block['innerBlocks'])) {
            $uid = event_o_mat_block_scan_uid($block['innerBlocks']);
            if ($uid !== '') {
                return $uid;
            }
        }
    }

    return '';
}
