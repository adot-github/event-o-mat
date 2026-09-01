<?php
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/event-registration.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/presenters-by-slot.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/presenters-by-workshop-type.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/events-by-workshop-type.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/events-with-filters.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/workshop-likes.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/partner-logos_view-1.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/partner-logos_view-2.php';
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/checkin-app/class-checkin-app.php';

if (!function_exists('event_registration_content_has_shortcode')) {
    /**
     * Whether the current singular post/page content contains the given
     * shortcode tag. Used to only enqueue each stylesheet/script on pages
     * that actually render the matching shortcode, instead of sitewide.
     *
     * Note: this only sees shortcodes placed in the post content itself
     * (block/classic editor). A shortcode rendered via do_shortcode() from
     * a template or widget won't be detected here.
     */
    function event_registration_content_has_shortcode($tag) {
        $post = get_post();

        if (!$post || empty($post->post_content)) {
            return false;
        }

        return has_shortcode($post->post_content, $tag);
    }
}

add_action('wp_enqueue_scripts', function () {
    $base = get_stylesheet_directory_uri() . '/db-custom/event-registration/public/css/';
    $dir  = get_stylesheet_directory()     . '/db-custom/event-registration/public/css/';

    // Broad rule: if ANY event-registration shortcode is on this page, load
    // every stylesheet in public/css/ together, whichever shortcode/page it
    // is — instead of mapping each file to one specific shortcode.
    $is_event_registration_page = event_registration_content_has_shortcode('event_registration')
        || event_registration_content_has_shortcode('events_with_filters')
        || event_registration_content_has_shortcode('events_by_workshop_type')
        || event_registration_content_has_shortcode('presenters_by_slot')
        || event_registration_content_has_shortcode('presenters_by_workshop_type')
        || event_registration_content_has_shortcode('sponsor_wall')
        || event_registration_content_has_shortcode('sponsor_ticker');

    $needs_workshops = event_registration_content_has_shortcode('events_with_filters');

    if ($is_event_registration_page) {
        // Load Bootstrap early (in <head>) when the theme does not provide it
        // for this event. The event_uid is read from the first
        // event-registration shortcode in the post content; the shortcode
        // callbacks call this again as a fallback for shortcodes rendered from
        // templates via do_shortcode(). The JS bundle is needed by every view
        // with interactive Bootstrap components (modal, accordion/collapse).
        $evt_uid = '';
        $evt_post = get_post();
        if ($evt_post && !empty($evt_post->post_content) && preg_match(
            '/\[(?:event_registration|events_with_filters|events_by_workshop_type|presenters_by_slot|presenters_by_workshop_type|sponsor_wall|sponsor_ticker)\b[^\]]*\bevent_uid=(["\']?)([^"\'\]\s]+)\1/',
            $evt_post->post_content,
            $evt_match
        )) {
            $evt_uid = sanitize_text_field($evt_match[2]);
        }
        $needs_bootstrap_js = event_registration_content_has_shortcode('event_registration')
            || event_registration_content_has_shortcode('events_with_filters')
            || event_registration_content_has_shortcode('events_by_workshop_type')
            || event_registration_content_has_shortcode('presenters_by_slot')
            || event_registration_content_has_shortcode('presenters_by_workshop_type');
        Event_Registration_Helpers::enqueue_bootstrap($evt_uid, $needs_bootstrap_js);

        foreach (glob($dir . '*.css') as $file_path) {
            $file   = basename($file_path);
            $handle = 'event-registration-' . sanitize_title(preg_replace('/\.css$/', '', $file));

            wp_enqueue_style($handle, $base . $file, [], filemtime($file_path));
        }
    }

    if (!$needs_workshops) {
        return;
    }

    $assets_base = get_stylesheet_directory_uri() . '/db-custom/event-registration/public/assets/';

    wp_enqueue_style(
        'event-registration-select2',
        $assets_base . 'vendor/select2/select2.css',
        [],
        '3.5.2'
    );

    wp_enqueue_script(
        'event-registration-select2',
        $assets_base . 'vendor/select2/select2.min.js',
        ['jquery'],
        '3.5.2',
        true
    );

    $ewf_js_dir  = get_stylesheet_directory()     . '/db-custom/event-registration/public/js/';
    $ewf_js_file = 'events-with-filters.js';
    wp_enqueue_script(
        'event-registration-events-with-filters',
        get_stylesheet_directory_uri() . '/db-custom/event-registration/public/js/' . $ewf_js_file,
        ['jquery', 'event-registration-select2'],
        file_exists($ewf_js_dir . $ewf_js_file) ? filemtime($ewf_js_dir . $ewf_js_file) : null,
        true
    );

    // "Merkliste" like button — the button itself is only rendered by
    // events_with_filters_render_workshop_html(), so its JS only needs to
    // load alongside that shortcode too.
    $likes_js_dir  = get_stylesheet_directory()     . '/db-custom/event-registration/public/js/';
    $likes_js_file = 'workshop-likes.js';
    wp_enqueue_script(
        'event-registration-workshop-likes',
        get_stylesheet_directory_uri() . '/db-custom/event-registration/public/js/' . $likes_js_file,
        ['jquery'],
        file_exists($likes_js_dir . $likes_js_file) ? filemtime($likes_js_dir . $likes_js_file) : null,
        true
    );

    wp_localize_script('event-registration-workshop-likes', 'evtmgrLikes', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('evtmgr_toggle_like'),
    ]);
});
