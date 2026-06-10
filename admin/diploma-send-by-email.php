<?php
// Add filter, which will return Instance of the Class above with the config for the component
add_filter('adot_email_sendout_datasource', function() {
    require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-email-diploma.php';
    $email_sendout = new Email_Diploma();    
    return $email_sendout;
});

// Include the component's admin page. All the options (like parent menu, labels, etc. Are stored in above config class)
include ADOT_SYS_COMPONENTS_PATH . '/email_sendout/admin.php';