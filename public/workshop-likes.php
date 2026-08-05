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
