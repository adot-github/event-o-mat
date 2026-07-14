<?php
(function(){
    if (!function_exists('Adot_DB_Editor')) {
        return;
    }

    // Register all event-registration page slugs BEFORE any get_current_event_uid() call.
    // This must happen here because init_folder() runs during plugins_loaded (before admin_menu),
    // so $GLOBALS['submenu'] is not yet available when get_cookie_event_uid() is first called.
    $event_reg_class = get_stylesheet_directory() . '/db-custom/event-registration/classes/class-event-registration.php';
    if (!class_exists('Event_Registration_Context') && file_exists($event_reg_class)) {
        require_once $event_reg_class;
    }
    if (class_exists('Event_Registration_Context')) {
        Event_Registration_Context::register_pages([
            // add_page_config pages (id field)
            'dashboard-event',
            'dashboard-workshop',
            'dashboard-anmeldungen',
            'dashboard-tools',
            // TBX add_table_config pages (slug = 'adot_' + table name)
            'adot_evtmgr_events',
            'adot_evtmgr_workshops',
            'adot_evtmgr_persons',
            'adot_evtmgr_presenters',
            'adot_evtmgr_timezones',
            'adot_evtmgr_pricing',
            'adot_evtmgr_wordings',
            'adot_evtmgr_slots',
            'adot_evtmgr_options',
            'adot_evtmgr_rooms',
            // Hidden submenu pages (procedures.php $adot_evtmgr_hidden_pages)
            'report-income',
            'report-participant-workshops',
            'workshop-booking-lists-pdf-create',
            'report-presenters',
            'workshop-booking-changes',
            'invoice-change',
            'invoice-pdf-create',
            'ticket-pdf-create',
            'etiketten-pdf-create',
            'report-persons',
            'diploma-pdf-create',
            'person-program-pdf-create',
            'event-pdf',
            'event-duplicate',
            'event-delete',
            'registration-delete',
            'wordings-scan',
            'wordings-extract',
            'database-fields-extract',
            'filestorage-clean',
        ]);
    }

    add_action('admin_head', function () {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === '' || strpos($page, 'evtmgr') === false) {
            return;
        }
        echo '<style>html,body{background:#0c0c0c !important}</style>' . "\n";
    }, 1);

    $editor = Adot_DB_Editor();
    require_once __DIR__ . '/wp_evtmgr_events.php';
    require_once __DIR__ . '/wp_evtmgr_workshops.php';
    require_once __DIR__ . '/wp_evtmgr_persons.php';
    require_once __DIR__ . '/wp_evtmgr_presenters.php';
    require_once __DIR__ . '/wp_evtmgr_timezones.php';
    require_once __DIR__ . '/wp_evtmgr_pricing.php';
    require_once __DIR__ . '/wp_evtmgr_wordings.php';
    require_once __DIR__ . '/wp_evtmgr_slots.php';
    require_once __DIR__ . '/wp_evtmgr_options.php';
    require_once __DIR__ . '/diploma-send-by-email.php';
    require_once __DIR__ . '/invoice-send-by-email.php';
    require_once __DIR__ . '/procedures.php';
})();
