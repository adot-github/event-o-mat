<?php
    wp_enqueue_style(
        'event-registration-admin-theme',
        get_stylesheet_directory_uri() . '/db-custom/event-registration/admin/css/admin-bootstrap-theme.css',
        array('bootstrap-5'),
        '1.0.0'
    );

(function(){
    if (!function_exists('Adot_DB_Editor')) {
        return;
    }
    $editor = Adot_DB_Editor();
    require_once __DIR__ . '/wp_evtmgr_events.php';
    require_once __DIR__ . '/wp_evtmgr_workshops.php';
    require_once __DIR__ . '/wp_evtmgr_persons.php';
    require_once __DIR__ . '/wp_evtmgr_presenters.php';
    require_once __DIR__ . '/wp_evtmgr_timezones.php';
    require_once __DIR__ . '/wp_evtmgr_pricing.php';
    require_once __DIR__ . '/wp_evtmgr_wordings.php';
    require_once __DIR__ . '/procedures.php';
})();