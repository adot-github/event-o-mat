<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
    $event_obj = new Evtmgr_Events();
    $event_languages = $event_obj->get_current_event_languages();
    $additional_sql_condition = $event_obj->get_current_event_sql_condition();
    $additional_sql_condition_for_tbx = $event_obj->get_current_event_sql_condition('record');
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
                'title'     => 'evtmgr_persons bearbeiten',
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
                int_type_of_address:col-lg-2 col-md-4
                str_salutation:col-lg-2 col-md-4
                str_academic_title:col-lg-1 col-md-2
                -
                str_first_name:col-lg-3 col-md-4
                str_last_name:col-lg-3 col-md-4
                -
                str_job_title:col-lg-3 col-md-4
                -
                str_email:col-lg-3 col-md-4
                str_phone:col-lg-3 col-md-4
                -
                str_address:col-lg-3 col-md-4
                str_zip:col-lg-1 col-md-2
                str_city:col-lg-2 col-md-3
                -
                str_country:col-lg-2 col-md-3
                str_language:col-lg-3 col-md-4
                fky_event_uid:col-lg-3 col-md-4


                tab:Organisation/Arbeitgeber
                str_institution:col-lg-3 col-md-4
                str_institution_division:col-lg-3 col-md-4
                -
                str_institution_address:col-lg-3 col-md-4
                -
                str_institution_zip:col-lg-2 col-md-3
                str_institution_city:col-lg-3 col-md-4
                -
                str_institution_name_shield_OFF:col-lg-3 col-md-4

                tab:Anmeldung
                mem_email_sent:col-12
                mem_program_data:col-12
                mem_price_data:col-12
                mem_person_data:col-12
                mem_cgi_variables:col-12
                mem_form_variables:col-12
                int_billing_status:col-lg-3 col-md-4
                int_diploma_sent:col-lg-3 col-md-4
                num_invoice_total:col-lg-3 col-md-4


                tab:Systemdaten
                str_registration_cookie:col-lg-3 col-md-4
                -
                str_diploma_pdf:col-12
                str_invoice_pdf:col-12
                str_program_pdf:col-12
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4'

        ],
        'fields' => [
'id' => [
                'label' => 'ID',
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
           'int_type_of_address' => [
                'label' => 'Art der Adresse',
                'langs' => [
                    'de' => ['label' => 'int_type_of_address (deutsch)'],
                    'fr' => ['label' => 'int_type_of_address (französisch)'],
                    'it' => ['label' => 'int_type_of_address (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => "checkbox",
                    'layout' => 'horizontal',
                    'default_value' => 'unbestimmt',
                    'choices' => [
                        '1' => 'privat',
                        '2' => 'geschäftlich',
                    ]
                ]
            ],
           'str_first_name' => [
                'label' => 'Vorname',
                'searchable' => true,
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_last_name' => [
                'label' => 'Nachname',
                'searchable' => true,
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_salutation' => [
                'label' => 'Anrede',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_academic_title' => [
                'label' => 'Akad. Titel',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_job_title' => [
                'label' => 'Funktion',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_email' => [
                'label' => 'E-Mail',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_phone' => [
                'label' => 'Telefon',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_address' => [
                'label' => 'Adresse',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_zip' => [
                'label' => 'PLZ',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_city' => [
                'label' => 'Ort',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_country' => [
                'label' => 'Land',
                'acf' => [
                    'type' => 'text',
                ]
            ],[
                'label'    => __('Anbieter', 'lernorte'),
                'acf' => [
                    'type' => 'tab',
                ],
            ],
           'str_institution' => [
                'label' => 'Institution',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_division' => [
                'label' => 'Institution Abteilung',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_name_shield' => [
                'label' => 'xxx-undefiniert',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_zip' => [
                'label' => 'Institution PLZ',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_address' => [
                'label' => 'Institution Adresse',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_institution_city' => [
                'label' => 'Institution Ort',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_language' => [
                'label' => 'Sprache',
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
                'label' => 'Beziehung',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                ],

            ],
           'dtm_date_created' => [
                'label' => 'Erstellt am',
                'acf' => [
                    'type' => 'date_picker',
                    'readonly'=> true,
                ]
            ],
           'dtm_date_updated' => [
                'label' => 'Geändert am',
                'acf' => [
                    'type' => 'date_picker',
                    'readonly'=> true,
                ]
            ],
           'str_registration_cookie' => [
                'label' => 'Cookie',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_diploma_pdf' => [
                'label' => 'TN-Bestätigung PDF',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_invoice_pdf' => [
                'label' => 'Rechnung PDF',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_program_pdf' => [
                'label' => 'Programm PDF',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
            'mem_email_sent' => [
                'label' => 'mem_email_sent',
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'mem_cgi_variables' => [
                'label' => 'mem_cgi_variables',
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'mem_form_variables' => [
                'label' => 'mem_form_variables',
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'mem_person_data' => [
                'label' => 'mem_person_data',
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'mem_program_data' => [
                'label' => 'mem_program_data',
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'mem_price_data' => [
                'label' => 'mem_price_data',
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'int_billing_status' => [
                'label' => 'int_billing_status',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'int_diploma_sent' => [
                'label' => 'int_diploma_sent',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'num_invoice_total' => [
                'label' => 'num_invoice_total',
                'acf' => [
                    'type' => 'text',
                ]
            ],
        ]
    ]);
