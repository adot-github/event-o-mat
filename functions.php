<?php
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style(
        'bootstrap-5',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
        array(),
        '5.3.8'
    );

    wp_enqueue_style(
        'event-registration-admin-theme',
        get_stylesheet_directory_uri() . '/db-custom/event-registration/admin/css/admin-bootstrap-theme.css',
        array('bootstrap-5'),
        '1.0.0'
    );

    wp_enqueue_script(
        'bootstrap-5-bundle',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.3.8',
        true
    );
});

require_once get_stylesheet_directory() . '/db-custom/event-registration/public/functions.php';