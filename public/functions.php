<?php
require_once get_stylesheet_directory() . '/db-custom/event-registration/public/event-registration.php';

add_action('wp_enqueue_scripts', function () {
    $base = get_stylesheet_directory_uri() . '/db-custom/event-registration/public/css/';
    $dir  = get_stylesheet_directory()     . '/db-custom/event-registration/public/css/';

    $sheets = [
        'event-registration-timetable'        => 'time-table-original.css',
        'event-registration-timetable-custom' => 'time-table-custom-1.css',
        'event-registration'          => 'event-registration.css',
        'event-registration-workshops'        => 'workshops.css',
    ];

    foreach ($sheets as $handle => $file) {
        wp_enqueue_style(
            $handle,
            $base . $file,
            [],
            file_exists($dir . $file) ? filemtime($dir . $file) : null
        );
    }
});
