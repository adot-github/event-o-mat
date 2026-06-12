<?php
$editor->add_page_config([
    'id' => 'dashboard',
    'menu' => [
        'menu_parent'=> $root_config_id,
        'page_title' => "Dashboard",
        'menu_title' => "DASHBOARD",
        'icon'       => get_stylesheet_directory_uri() . '/db-custom/lernorte/admin/assets/fhnw-logo-weiss.svg',
        'position'   => 0
    ],
    'page' => 'dashboard'
]);

$editor->add_page_config([
    'id' => 'dashboard-event',
    'menu' => [
        'menu_parent'=> $root_config_id,
        'page_title' => "EVENT",
        'menu_title' => "EVENT",
        'position'   => 1
    ],
    'page' => 'dashboard-event'
]);

$editor->add_page_config([
    'id' => 'dashboard-workshop',
    'menu' => [
        'menu_parent'=> $root_config_id,
        'page_title' => "WORKSHOPS",
        'menu_title' => "WORKSHOPS",
        'position'   => 2
    ],
    'page' => 'dashboard-workshop'
]);


$editor->add_page_config([
    'id' => 'dashboard-anmeldungen',
    'menu' => [
        'menu_parent'=> $root_config_id,
        'page_title' => "ANMELDUNGEN",
        'menu_title' => "ANMELDUNGEN",
        'position'   => 2
    ],
    'page' => 'dashboard-anmeldungen'
]);


/** ENO */
/**
 * Hidden admin pages that should belong to the existing parent:
 * /wp-admin/admin.php?page=adot_evtmgr_events
 *
 * They stay registered as real submenu pages, but are hidden visually.
 * When one of them is opened, another visible submenu item can be marked active.
 */
$adot_evtmgr_hidden_pages = array(
    array(
        'page_title'        => '> dashboard-event',
        'menu_title'        => '> dashboard-event',
        'capability'        => 'manage_options',
        'menu_slug'         => 'report-income',
        'file'              => '/db-custom/event-registration/pages/report-income.php',
        'active_submenu'    => 'dashboard-event',
    ),


    array(
        'page_title'        => '> report-participant-workshops',
        'menu_title'        => '> report-participant-workshops',
        'capability'        => 'manage_options',
        'menu_slug'         => 'report-participant-workshops',
        'file'              => '/db-custom/event-registration/pages/report-participant-workshops.php',
        'active_submenu'    => 'dashboard-event',
    ),
    array(
        'page_title'        => '> workshop-booking-lists-pdf-create',
        'menu_title'        => '> workshop-booking-lists-pdf-create',
        'capability'        => 'manage_options',
        'menu_slug'         => 'workshop-booking-lists-pdf-create',
        'file'              => '/db-custom/event-registration/pages/workshop-booking-lists-pdf-create.php',
        'active_submenu'    => 'dashboard-workshop',
        ),
    array(
        'page_title'        => '> preport-presenters',
        'menu_title'        => '> report-presenters',
        'capability'        => 'manage_options',
        'menu_slug'         => 'report-presenters',
        'file'              => '/db-custom/event-registration/pages/report-presenters.php',
        'active_submenu'    => 'dashboard-workshop',
        ),

    array(
        'page_title'        => '> workshop-booking-changes',
        'menu_title'        => '> workshop-booking-changes',
        'capability'        => 'manage_options',
        'menu_slug'         => 'workshop-booking-changes',
        'file'              => '/db-custom/event-registration/pages/workshop-booking-changes.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),

    array(
        'page_title'        => '> invoice-change',
        'menu_title'        => '> invoice-change',
        'capability'        => 'manage_options',
        'menu_slug'         => 'invoice-change',
        'file'              => '/db-custom/event-registration/pages/invoice-change.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),
    array(
        'page_title'        => '> invoice-pdf-create',
        'menu_title'        => '> invoice-pdf-create',
        'capability'        => 'manage_options',
        'menu_slug'         => 'invoice-pdf-create',
        'file'              => '/db-custom/event-registration/pages/invoice-pdf-create.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),    
    /*
    array(
        'page_title'        => '> invoice-send-by-email',
        'menu_title'        => '> invoice-send-by-email',
        'capability'        => 'manage_options',
        'menu_slug'         => 'invoice-send-by-email',
        'file'              => '/db-custom/event-registration/pages/invoice-send-by-email.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),
    */
    array(
        'page_title'        => '> report-persons',
        'menu_title'        => '> report-persons',
        'capability'        => 'manage_options',
        'menu_slug'         => 'report-persons',
        'file'              => '/db-custom/event-registration/pages/report-persons.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),

    array(
        'page_title'        => '> diploma-pdf-create',
        'menu_title'        => '> diploma-pdf-create',
        'capability'        => 'manage_options',
        'menu_slug'         => 'diploma-pdf-create',
        'file'              => '/db-custom/event-registration/pages/diploma-pdf-create.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),
    /*
    array(
        'page_title'        => '> diploma-send-by-email',
        'menu_title'        => '> diploma-send-by-email',
        'capability'        => 'manage_options',
        'menu_slug'         => 'diploma-send-by-email',
        'file'              => '/db-custom/event-registration/pages/diploma-send-by-email.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),
    */
    array(
        'page_title'        => '> person-program-pdf-create',
        'menu_title'        => '> person-program-pdf-create',
        'capability'        => 'manage_options',
        'menu_slug'         => 'person-program-pdf-create',
        'file'              => '/db-custom/event-registration/pages/person-program-pdf-create.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),

    array(
        'page_title'        => '> event duplicate',
        'menu_title'        => '> event duplicate',
        'capability'        => 'manage_options',
        'menu_slug'         => 'event-duplicate',
        'file'              => '/db-custom/event-registration/pages/event-duplicate.php',
        'active_submenu'    => 'dashboard-event',
    ),
    array(
        'page_title'        => '> event delete',
        'menu_title'        => '> event delete',
        'capability'        => 'manage_options',
        'menu_slug'         => 'event-delete',
        'file'              => '/db-custom/event-registration/pages/event-delete.php',
        'active_submenu'    => 'dashboard-event',
    ),
    array(
        'page_title'        => '> registration delete',
        'menu_title'        => '> registration delete',
        'capability'        => 'manage_options',
        'menu_slug'         => 'registration-delete',
        'file'              => '/db-custom/event-registration/pages/registration-delete.php',
        'active_submenu'    => 'dashboard-anmeldungen',
    ),

);

$adot_evtmgr_hidden_sys_pages = array(
    array(
        'menu_slug'         => 'adot_evtmgr_workshops',
        'active_submenu'    => 'dashboard',
    ),
    array(
        'menu_slug'         => 'adot_evtmgr_events',
        'active_submenu'    => 'dashboard',
    ),
    array(
        'menu_slug'         => 'adot_evtmgr_persons',
        'active_submenu'    => 'dashboard',
    ),
    array(
        'menu_slug'         => 'adot_evtmgr_presenters',
        'active_submenu'    => 'dashboard',
    ),
);

add_action('admin_menu', function () use ($adot_evtmgr_hidden_pages) {

    foreach ($adot_evtmgr_hidden_pages as $page) {
        add_submenu_page(
            'adot_evtmgr_events',
            $page['page_title'],
            $page['menu_title'],
            $page['capability'],
            $page['menu_slug'],
            function () use ($page) {
                require get_stylesheet_directory() . $page['file'];
            }
        );
    }

}, 99);


/**
 * Hide the registered submenu pages visually.
 * Do not use remove_submenu_page(), otherwise WordPress loses the parent relation.
 */

add_action('admin_head', function () use ($adot_evtmgr_hidden_pages,$adot_evtmgr_hidden_sys_pages) {
    echo '<!-- ENO--><style>';
    foreach ($adot_evtmgr_hidden_pages as $page) {
        echo ' #adminmenu a[href*="page=' . esc_attr($page['menu_slug']) . '"] { display: none !important; }';
    }
    foreach ($adot_evtmgr_hidden_sys_pages as $page) {
        echo ' #adminmenu a[href*="page=' . esc_attr($page['menu_slug']) . '"] { display: none !important; }';
    }
    echo '</style>';
});


/**
 * Keep parent menu open.
 */
add_filter('parent_file', function ($parent_file) use ($adot_evtmgr_hidden_pages) {
    $current_page = isset($_GET['page'])
        ? sanitize_key(wp_unslash($_GET['page']))
        : '';

    foreach ($adot_evtmgr_hidden_pages as $page) {
        if ($current_page === $page['menu_slug']) {
            return 'adot_evtmgr_events';
        }
    }

    return $parent_file;
});


/**
 * Mark configured visible submenu as active.
 */
add_filter('submenu_file', function ($submenu_file) use ($adot_evtmgr_hidden_pages) {
    $current_page = isset($_GET['page'])
        ? sanitize_key(wp_unslash($_GET['page']))
        : '';

    foreach ($adot_evtmgr_hidden_pages as $page) {
        if ($current_page === $page['menu_slug']) {
            return $page['active_submenu'];
        }
    }

    return $submenu_file;
});