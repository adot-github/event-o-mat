<?php
/**
 * AJAX endpoint backing the "Merkliste" like button rendered by
 * public/registration/_workshop.php. Works without login: the visitor is
 * identified by the cookie set in Evtmgr_Workshop_Likes.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/class-evtmgr-workshop-likes.php';

add_action('wp_ajax_evtmgr_toggle_like', 'evtmgr_toggle_like_ajax_handler');
add_action('wp_ajax_nopriv_evtmgr_toggle_like', 'evtmgr_toggle_like_ajax_handler');

/**
 * The "Merkliste" like button. Needs event_registration_enqueue_workshop_likes()
 * on the page (public/functions.php).
 */
function evtmgr_like_button_html($workshop_id, $event_uid, $is_liked = false) {
    $workshop_id = absint($workshop_id);

    if ($workshop_id <= 0 || (string) $event_uid === '') {
        return '';
    }

    return '<button type="button"'
        . ' class="js-workshop-like-button workshop-like-button' . ($is_liked ? ' is-liked' : '') . '"'
        . ' data-workshop-id="' . esc_attr($workshop_id) . '"'
        . ' data-event-uid="' . esc_attr($event_uid) . '"'
        . ' aria-pressed="' . ($is_liked ? 'true' : 'false') . '"'
        . ' aria-label="Auf die Merkliste setzen">'
        . '<img src="' . esc_url(get_stylesheet_directory_uri() . '/db-custom/event-registration/public/img/like.svg') . '" alt="" width="24" height="24">'
        . '</button>';
}

/**
 * IDs of the workshops the current visitor has liked for an event. Only reads
 * the visitor cookie (the AJAX handler creates it on the first like).
 *
 * @return int[]
 */
function evtmgr_liked_workshop_ids_for_visitor($event_uid) {
    $likes_obj = new Evtmgr_Workshop_Likes();
    $cookie    = $likes_obj->get_visitor_cookie();

    return $cookie !== '' ? array_map('absint', (array) $likes_obj->get_liked_workshop_ids($event_uid, $cookie)) : array();
}

function evtmgr_toggle_like_ajax_handler() {
    if (!check_ajax_referer('evtmgr_toggle_like', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ungültige Sicherheitsprüfung.'], 403);
    }

    $event_uid   = isset($_POST['event_uid']) ? sanitize_text_field(wp_unslash($_POST['event_uid'])) : '';
    $workshop_id = isset($_POST['workshop_id']) ? absint($_POST['workshop_id']) : 0;

    if ($event_uid === '' || $workshop_id <= 0) {
        wp_send_json_error(['message' => 'Ungültige Anfrage.']);
    }

    $likes_obj = new Evtmgr_Workshop_Likes();
    $cookie    = $likes_obj->get_or_create_visitor_cookie();

    $result = $likes_obj->toggle_like($event_uid, $workshop_id, $cookie);

    if (!$result['success']) {
        wp_send_json_error(['message' => 'Merkliste konnte nicht aktualisiert werden.']);
    }

    wp_send_json_success(['liked' => $result['liked']]);
}
