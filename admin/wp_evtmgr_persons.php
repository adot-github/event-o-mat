<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class_database_fields.php';
    $event_obj = new Evtmgr_Events();
    $event_uid = $event_obj->get_current_event_uid();
    $event_languages = $event_obj->get_current_event_languages();
    $additional_sql_condition = $event_obj->get_current_event_sql_condition();
    $additional_sql_condition_for_tbx = $event_obj->get_current_event_sql_condition('record');
    $labels = (new Evtmgr_Database_Fields())->get_labels('wp_evtmgr_persons');

    // Refresh num_invoice_total for this one person whenever their edit form is opened.
    $evtmgr_persons_edit_record_id = absint($_GET['record'] ?? 0);
    if ($evtmgr_persons_edit_record_id > 0) {
        require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-persons.php';
        (new class_evtmgr_persons())->person_update_invoice_total($evtmgr_persons_edit_record_id);
    }
?>

<?php
    $lang = 'de';
    $editor->add_table_config([
        'table' => 'evtmgr_persons',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'evtmgr_persons',
            'menu_title' => 'evtmgr_persons',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],
        'sql' => [
            'select_fields' => [
                "CONCAT(str_last_name, ' ', str_first_name) as fullname"
            ]
        ],
        'list'  => [
            'fields' => ["fullname"],
            'fields_iframe' => ["id","fullname"],
            'orderby_default' => "str_last_name",
            'order_default' => 'asc',
            'condition' => "$additional_sql_condition",
            'labels' => [
                'title'     => 'Personen bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],

        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'Personen bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                tab:Privat
                id:col-lg-1 col-md-1
                int_type_of_address:col-lg-4 col-md-6
                str_language:col-lg-4 col-md-6
                -
                str_salutation:col-lg-3 col-md-4
                str_academic_title:col-lg-1 col-md-2
                -
                str_first_name:col-lg-4 col-md-6
                str_last_name:col-lg-4 col-md-6
                -
                str_job_title:col-lg-4 col-md-6
                -
                str_email:col-lg-4 col-md-6
                str_phone:col-lg-4 col-md-6
                -
                str_address:col-lg-4 col-md-6
                str_zip:col-lg-1 col-md-2
                str_city:col-lg-2 col-md-3
                -
                str_country:col-lg-2 col-md-3
                fky_event_uid:col-lg-2 col-md-3


                tab:Organisation/Arbeitgeber
                str_institution:col-lg-4 col-md-6
                str_institution_division:col-lg-4 col-md-6
                -
                str_institution_address:col-lg-4 col-md-6
                -
                str_institution_zip:col-lg-2 col-md-3
                str_institution_city:col-lg-4 col-md-6
                -
                str_institution_name_shield_OFF:col-lg-4 col-md-6

                tab:Anmeldung
                num_invoice_total:col-lg-4 col-md-6
                int_billing_status:col-lg-4 col-md-6
                -
                int_diploma_sent:col-lg-4 col-md-6
                mem_email_sent:col-12
                mem_program_data:col-12
                mem_price_data:col-12
                mem_person_data:col-12
                mem_cgi_variables:col-12
                mem_form_variables:col-12

                tab:Systemdaten
                str_registration_cookie:col-lg-4 col-md-6
                -
                str_diploma_pdf:col-12
                str_invoice_pdf:col-12
                str_program_pdf:col-12
                dtm_date_created:col-lg-4 col-md-6
                dtm_date_updated:col-lg-4 col-md-6'

        ],
        'fields' => [
            'id' => [
                'label' => $labels['id'] ?? 'ID',
                'langs' => [
                    'de' => ['label' => 'id (deutsch)'],
                    'fr' => ['label' => 'id (französisch)'],
                    'it' => ['label' => 'id (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                ]
            ],
            'fullname' => [
                'label' => 'Name',
                'formatter' => [
                    'list' => 'actions'
                ],
                'is_form_hidden' => true
            ],
            'int_type_of_address' => [
                'label' => $labels['int_type_of_address'] ?? 'Art der Adresse',
                'langs' => [
                    'de' => ['label' => 'int_type_of_address (deutsch)'],
                    'fr' => ['label' => 'int_type_of_address (französisch)'],
                    'it' => ['label' => 'int_type_of_address (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => "radio",
                    'layout' => 'horizontal',
                    'default_value' => 'unbestimmt',
                    'choices' => [
                        '1' => 'privat',
                        '2' => 'geschäftlich',
                    ]
                ]
            ],
           'str_first_name' => [
                'label' => $labels['str_first_name'] ?? 'Vorname',
                'searchable' => true,
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_last_name' => [
                'label' => $labels['str_last_name'] ?? 'Nachname',
                'searchable' => true,
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_salutation' => [
                'label' => $labels['str_salutation'] ?? 'Anrede',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_academic_title' => [
                'label' => $labels['str_academic_title'] ?? 'Akad. Titel',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_job_title' => [
                'label' => $labels['str_job_title'] ?? 'Funktion',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_email' => [
                'label' => $labels['str_email'] ?? 'E-Mail',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_phone' => [
                'label' => $labels['str_phone'] ?? 'Telefon',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_address' => [
                'label' => $labels['str_address'] ?? 'Adresse',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_zip' => [
                'label' => $labels['str_zip'] ?? 'PLZ',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_city' => [
                'label' => $labels['str_city'] ?? 'Ort',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_country' => [
                'label' => $labels['str_country'] ?? 'Land',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution' => [
                'label' => $labels['str_institution'] ?? 'Institution',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_division' => [
                'label' => $labels['str_institution_division'] ?? 'Institution Abteilung',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_name_shield' => [
                'label' => $labels['str_institution_name_shield'] ?? 'xxx-undefiniert',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_zip' => [
                'label' => $labels['str_institution_zip'] ?? 'Institution PLZ',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_address' => [
                'label' => $labels['str_institution_address'] ?? 'Institution Adresse',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_city' => [
                'label' => $labels['str_institution_city'] ?? 'Institution Ort',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_language' => [
                'label' => $labels['str_language'] ?? 'Sprache',
                'acf' => [
                    'type' => "radio",
                    'layout' => 'horizontal',
                    'default_value' => 'unbestimmt',
                    'choices' => [
                        'de' => 'deutsch',
                        'fr' => 'französisch',
                        'it' => 'itaienisch',
                    ]
                ]
            ],

           'fky_event_uid' => [
                'label' => $labels['fky_event_uid'] ?? 'UID',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                    'default_value'=> $event_uid,
                ],

            ],
           'dtm_date_created' => [
                'label' => $labels['dtm_date_created'] ?? 'Erstellt am',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true,
                ]
            ],
           'dtm_date_updated' => [
                'label' => $labels['dtm_date_updated'] ?? 'Geändert am',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true,
                ],
                'value_force' => 'now()'
            ],
           'str_registration_cookie' => [
                'label' => $labels['str_registration_cookie'] ?? 'Cookie',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_diploma_pdf' => [
                'label' => $labels['str_diploma_pdf'] ?? 'TN-Bestätigung PDF',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_invoice_pdf' => [
                'label' => $labels['str_invoice_pdf'] ?? 'Rechnung PDF',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_program_pdf' => [
                'label' => $labels['str_program_pdf'] ?? 'Programm PDF',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
            'mem_email_sent' => [
                'label' => $labels['mem_email_sent'] ?? 'mem_email_sent',
                'searchable' => true,
                'acf' => [
                    'type' => 'acdb_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'mem_cgi_variables' => [
                'label' => $labels['mem_cgi_variables'] ?? 'mem_cgi_variables',
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                ],
            ],
           'mem_form_variables' => [
                'label' => $labels['mem_form_variables'] ?? 'mem_form_variables',
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                ],
            ],
           'mem_person_data' => [
                'label' => $labels['mem_person_data'] ?? 'mem_person_data',
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                ],
            ],
           'mem_program_data' => [
                'label' => $labels['mem_program_data'] ?? 'mem_program_data',
                'searchable' => true,
                'acf' => [
                    'type' => 'acdb_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'mem_price_data' => [
                'label' => $labels['mem_price_data'] ?? 'mem_price_data',
                'searchable' => true,
                'acf' => [
                    'type' => 'acdb_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'int_billing_status' => [
                'label' => $labels['int_billing_status'] ?? 'Rechnungsstatus',
                'acf' => [
                    'type'    => 'radio',
                    'choices' => [
                        '0'   => 'Rechnung noch nicht erhalten',
                        '1'   => 'Rechnung erhalten, aber noch nicht bezahlt',
                        '11'  => 'Erste Mahnung erhalten, aber noch nicht bezahlt',
                        '12'  => 'Zweite Mahnung erhalten, aber noch nicht bezahlt',
                        '13'  => 'Dritte Mahnung erhalten, aber noch nicht bezahlt',
                        '100' => 'Rechnung bezahlt',
                    ],
                ]
            ],
           'int_diploma_sent' => [
                'label' => $labels['int_diploma_sent'] ?? 'int_diploma_sent',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'num_invoice_total' => [
                'label' => $labels['num_invoice_total'] ?? 'num_invoice_total',
                'acf' => [
                    'type' => 'text',
                ]
            ],
        ]
    ]);
