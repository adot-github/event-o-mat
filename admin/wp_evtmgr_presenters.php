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
        'table' => 'evtmgr_presenters',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'evtmgr_presenters',
            'menu_title' => 'evtmgr_presenters',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],
        'sql' => [
            'select_fields' => [
                "CONCAT(str_last_name, ' ', str_first_name) as fullname"
            ]
        ],
        'list'  => [
            'fields' => ["str_last_name"],
            'fields_iframe' => ["fullname"],
            'orderby_default' => "fullname",
            'order_default' => 'asc',
            'condition' => "$additional_sql_condition",
            'labels' => [
                'title'     => 'Datensatz bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],
            
        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'Datensatz bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                tab:Privat
                id:col-lg-1 col-md-1
                -
                str_salutation:col-lg-2 col-md-4
                str_academic_title:col-lg-2 col-md-4
                -
                str_first_name:col-lg-3 col-md-4
                str_last_name:col-lg-3 col-md-4
                -
                str_job_title_{{lang}}:col-md-{{lang_col_count}}
                str_institution_{{lang}}:col-md-{{lang_col_count}}
                str_institution_division_{{lang}}:col-md-{{lang_col_count}}

                -
                str_email:col-lg-3 col-md-4
                str_phone:col-lg-3 col-md-4
                -
                str_address:col-lg-3 col-md-4
                str_zip:col-lg-1 col-md-2
                str_city:col-lg-2 col-md-3
                str_country:col-lg-2 col-md-3
                str_language:col-lg-2 col-md-3
                
                tab:Texte/Bild
                mem_presenter_text_de{{lang}}:col-md-{{lang_col_count}}
                str_person_image:col-lg-6 col-md-6
                
                tab:Varia
                ysn_print:col-lg-3 col-md-4
                ysn_website:col-lg-3 col-md-4
                fky_event_uid:col-lg-3 col-md-4
                -
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4
                str_registration_cookie:col-lg-3 col-md-4
                str_diploma_pdf:col-lg-3 col-md-4
                str_invoice_pdf:col-lg-3 col-md-4
                str_program_pdf:col-lg-3 col-md-4'
        ],
        'fields' => [
'id' => [
                'label' => 'ID',
                'searchable' => true,
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                ]
            ],
           'int_type_of_address' => [
                'label' => 'Art der Adresse',
                'langs' => [
                    'de' => ['label' => 'Art der Adresse (deutsch)'],
                    'fr' => ['label' => 'Art der Adresse (französisch)'],
                    'it' => ['label' => 'Art der Adresse (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_employer' => [
                'label' => 'xxx-undefiniert',
                'acf' => [
                    'type' => 'text',
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
           'str_job_title_{{lang}}' => [
                'label' => 'Funktion',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
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
            ],
           'str_institution_{{lang}}' => [
                'label' => 'Institution',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_person_image' => [
                'label' => 'Bild der Person',
                'acf' => [
                    'type' => 'adot_file_selector',    
                    'file_type' => 'image',
                    'subfolder' => '/',
                    'image_width' => 300,
                    'image_height' => 300,
                ],
            ],
           'str_language' => [
                'label' => 'Sprache',
                'acf' => [
                    'type' => "checkbox",
                    'layout' => 'horizontal',
                    'default_value' => 'unbestimmt',
                    'choices' => [
                        'de' => 'deutsch',
                        'fr' => 'französisch',
                        'it' => 'itaienisch',
                    ]
                ]
            ],
           'mem_presenter_text_de{{lang}}' => [
                'label' => 'Text zur Person',
                'langs' => [
                    'de' => ['label' => 'Text zur Person (deutsch)'],
                    'fr' => ['label' => 'Text zur Person (französisch)'],
                    'it' => ['label' => 'Text zur Person (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'ysn_print' => [
                'label' => 'Erscheint in Broschüre',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_website' => [
                'label' => 'Erscheint in Website',
                'acf' => [
                    'type' => 'true_false',
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
        ]
    ]);
