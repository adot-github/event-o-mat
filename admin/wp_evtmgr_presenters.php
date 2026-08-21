<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class_database_fields.php';
    $event_obj = new Evtmgr_Events();
    $event_languages = $event_obj->get_current_event_languages();
    $event_uid = $event_obj->get_current_event_uid();
    $additional_sql_condition = $event_obj->get_current_event_sql_condition();
    $additional_sql_condition_for_tbx = $event_obj->get_current_event_sql_condition('record');
    $labels = (new Evtmgr_Database_Fields())->get_labels('wp_evtmgr_presenters');
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
                'label' => $labels['id'] ?? 'ID',
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
                'label' => $labels['str_employer'] ?? 'xxx-undefiniert',
                'acf' => [
                    'type' => 'text',
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
           'str_job_title_{{lang}}' => [
                'label' => $labels['str_job_title_de'] ?? 'Funktion',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
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
           'str_institution_{{lang}}' => [
                'label' => $labels['str_institution_de'] ?? 'Institution',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_person_image' => [
                'label' => $labels['str_person_image'] ?? 'Bild der Person',
                'acf' => [
                    'type' => 'acdb_file_selector',
                    'file_type' => 'image',
                    'subfolder' => '/',
                    'image_width' => 300,
                    'image_height' => 300,
                ],
            ],
           'str_language' => [
                'label' => $labels['str_language'] ?? 'Sprache',
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
           'mem_presenter_text_{{lang}}' => [
                'label' => $labels['mem_presenter_text_de'] ?? 'Text zur Person',
                'langs' => [
                    'de' => ['label' => 'Text zur Person (deutsch)'],
                    'fr' => ['label' => 'Text zur Person (französisch)'],
                    'it' => ['label' => 'Text zur Person (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'acdb_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'ysn_print' => [
                'label' => $labels['ysn_print'] ?? 'Erscheint in Broschüre',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_website' => [
                'label' => $labels['ysn_website'] ?? 'Erscheint in Website',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'fky_event_uid' => [
                'label' => $labels['fky_event_uid'] ?? 'Beziehung',
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
        ]
    ]);
