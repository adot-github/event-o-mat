<?php
require_once dirname(EVENT_REGISTRATION_DIR) . '/classes/class-email-diploma.php';
$email_sendout_datasource = new Email_Diploma();    

// Include the component's admin page. All the options (like parent menu, labels, etc. Are stored in above config class)
include ADOT_SYS_COMPONENTS_PATH . '/email_sendout/admin.php';