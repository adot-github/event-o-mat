<?php

require_once ADOT_SYS_COMPONENTS_PATH . '/email_sendout/classes/AdotEmailSendoutDatasource.php';
class Email_Diploma extends AdotEmailSendoutDatasource {
    public function __construct($options = []) {
        // All possible options are located in email_sendout/classes/AdotEmailSendoutDatasource.php
        parent::__construct(array_merge([
            'custom_page' => 'diploma-send-by-email',
            'title_base' => 'Teilnahmebestätigungen',
            'languages' => ["DE"],
            'language' => 'DE',
            'group' => "diplomas",
            'vars_allowed' => [
                'str_first_name' => 'Vorname',
                'str_last_name' => 'Nachname',
            ],
            'tags_allowed' => [
                'attachement_pdf'=> 'Teilnahmebestätigung als PDF mitsenden',
                'change_diploma_send' => 'Set DiplomaSend Flag'
            ],
            'tags_default' => "attachement_pdf,change_diploma_send",
            'filters' => [
                'type' => 'radio',
                //'layout' => 'horizontal',
                'choices' => [
                    '0' => "Alle Personen, welche die Teilnahmebestätigung noch nicht erhalten haben",
                    '1' => "Alle Personen, welche die Teilnahmebestätigung bereits erhalten haben",
                ]
            ],
            'hide_in_menu' => true
        ], $options));
    }

    // Override functions, to get real data:
    
    public function get_head_content(): string {
        //...
    }

    public function get_persons_query(string $ids = "", string $filters = "", string $search = "", int $limit = 0): string {
        //...
    }

    public function get_vars(array $st_person, string $str_filters_external = '', bool $is_test = false): array {
        //...
    }

    public function get_attachments(array $st_person, string $tags): array {
        //...
    }

    public function get_external_records(string $person_ids = "", string $filters = "", string $filters_persons = ""): array {
        //...
    }

    public function get_external_records_link(array $st_external, string $str_filters_external = ""): string {
        //...
    }
}