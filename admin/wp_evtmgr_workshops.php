<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
    $event_obj = new Evtmgr_Events();
    $event_languages = $event_obj->get_current_event_languages();
    $event_uid = $event_obj->get_current_event_uid();
    $additional_sql_condition = $event_obj->get_current_event_sql_condition();
    $additional_sql_condition_for_tbx = $event_obj->get_current_event_sql_condition('record');
    $event_uid = $event_obj->get_current_event_uid(true);
?>

<?php
    $lang = 'de';
    $editor->add_table_config([
        'table' => 'evtmgr_workshops',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'evtmgr_workshops',
            'menu_title' => 'evtmgr_workshops',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],
        'list'  => [
            'fields' => ["id","str_workshop_number", "str_workshop_title_{$lang}"],
            'fields_iframe' => ["id","str_workshop_number","str_workshop_title_{$lang}"],
            'orderby_default' => "str_workshop_title_{$lang}",
            'order_default' => 'asc',
            'condition' => "$additional_sql_condition",
            'labels' => [
                'title'     => 'evtmgr_workshops bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],

        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'evtmgr_workshops bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                tab:Texte
                str_workshop_number:col-lg-3 col-md-4
                -
                fky_dozierende:col-lg-6 col-md-12
                -
                str_workshop_title_{{lang}}:col-md-{{lang_col_count}}
                str_workshop_subtitle_{{lang}}:col-md-{{lang_col_count}}

                mem_workshop_description_{{lang}}:col-md-{{lang_col_count}}
                mem_workshop_description_long_{{lang}}:col-md-{{lang_col_count}}
                mem_comments:col-lg-3 col-md-4
                fky_event_uid:col-lg-3 col-md-4

                tab:Anmeldung/Kosten
                ysn_booked_out:col-lg-3 col-md-4
                ysn_print:col-lg-3 col-md-4
                ysn_online:col-lg-3 col-md-4
                ysn_auto_register:col-lg-3 col-md-4
                ysn_no_registration_possible:col-lg-3 col-md-4
                int_number_of_registrations:col-lg-3 col-md-4
                int_max_number_of_registrations:col-lg-3 col-md-4
                num_price:col-lg-3 col-md-4

                tab:Konfiguration
                fky_timezone_id:col-lg-6 col-md-8
                fky_slot_id:col-lg-6 col-md-8
                fky_room_id:col-lg-6 col-md-8
                fky_audience_id:col-lg-3 col-md-4
                -
                dtm_date_updated:col-lg-3 col-md-4
                dtm_date_created:col-lg-3 col-md-4

                tab:Obsolete
                str_workshop_type:col-lg-3 col-md-4
                int_sort_print:col-lg-3 col-md-4

                int_typo_count:col-lg-3 col-md-4
                str_typo_count:col-lg-3 col-md-4

                str_audience_print:col-lg-3 col-md-4
                str_time_print:col-lg-3 col-md-4
                str_room_print:col-lg-3 col-md-4
                str_presenters_print:col-lg-3 col-md-4'
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
                    'type' => 'text'
                ]
            ],
           'str_workshop_number' => [
                'label' => 'Workshop-Nummer',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'int_sort_print' => [
                'label' => 'Sortierung',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_workshop_title_{{lang}}' => [
                'label' => 'Titel des Workshops',
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_workshop_subtitle_{{lang}}' => [
                'label' => 'Untertitel des Workshops',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_workshop_type' => [
                'label' => 'Workshop-Typ',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'mem_workshop_description_{{lang}}' => [
                'label' => 'Beschreibung',
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'mem_workshop_description_long_{{lang}}' => [
                'label' => 'Beschreibung detailliert',
                'langs' => [
                    'de' => ['label' => 'mem_workshop_description_long (deutsch)'],
                    'fr' => ['label' => 'mem_workshop_description_long (französisch)'],
                    'it' => ['label' => 'mem_workshop_description_long (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'mem_comments' => [
                'label' => 'Interne Bemerkungen',
                'langs' => [
                    'de' => ['label' => 'mem_comments (deutsch)'],
                    'fr' => ['label' => 'mem_comments (französisch)'],
                    'it' => ['label' => 'mem_comments (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'ysn_booked_out' => [
                'label' => 'Ausgebucht',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_print' => [
                'label' => 'Erscheint in Broschüre',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_online' => [
                'label' => 'Erscheint Online',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_auto_register' => [
                'label' => 'Obligatorische&nbsp;Anmeldung',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_no_registration_possible' => [
                'label' => 'Keine Anmeldung',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'int_number_of_registrations' => [
                'label' => 'Anzahl Anmeldungen',
                'langs' => [
                    'de' => ['label' => 'int_number_of_registrations (deutsch)'],
                    'fr' => ['label' => 'int_number_of_registrations (französisch)'],
                    'it' => ['label' => 'int_number_of_registrations (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                ]
            ],
           'int_max_number_of_registrations' => [
                'label' => 'Max. Anzahl Teilnehmende',
                'langs' => [
                    'de' => ['label' => 'int_max_number_of_registrations (deutsch)'],
                    'fr' => ['label' => 'int_max_number_of_registrations (französisch)'],
                    'it' => ['label' => 'int_max_number_of_registrations (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'num_price' => [
                'label' => 'Preis',
                'langs' => [
                    'de' => ['label' => 'num_price (deutsch)'],
                    'fr' => ['label' => 'num_price (französisch)'],
                    'it' => ['label' => 'num_price (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'ysn_no_discount' => [
                'label' => 'Nicht Rabatt-berechtigt',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],

           'int_typo_count' => [
                'label' => 'int_typo_count',
                'langs' => [
                    'de' => ['label' => 'int_typo_count (deutsch)'],
                    'fr' => ['label' => 'int_typo_count (französisch)'],
                    'it' => ['label' => 'int_typo_count (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_typo_count' => [
                'label' => 'str_typo_count',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'mem_workshop_description_short_print' => [
                'label' => 'Beschreibung lang DE',
                'langs' => [
                    'de' => ['label' => 'mem_workshop_description_short_print (deutsch)'],
                    'fr' => ['label' => 'mem_workshop_description_short_print (französisch)'],
                    'it' => ['label' => 'mem_workshop_description_short_print (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'mem_workshop_description_print' => [
                'label' => 'Beschreibung lang DE',
                'langs' => [
                    'de' => ['label' => 'mem_workshop_description_print (deutsch)'],
                    'fr' => ['label' => 'mem_workshop_description_print (französisch)'],
                    'it' => ['label' => 'mem_workshop_description_print (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_audience_print' => [
                'label' => 'str_audience_print',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_time_print' => [
                'label' => 'str_time_print',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_room_print' => [
                'label' => 'str_room_print',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_presenters_print' => [
                'label' => 'str_presenters_print',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'dtm_date_updated' => [
                'label' => 'Geändert am',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true,
                ],
                'value_force' => 'now()'
            ],
           'dtm_date_created' => [
                'label' => 'Erstellt am',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true,
                ]
            ],
           'fky_event_uid' => [
                'label' => 'UID',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                    'default_value' => "$event_uid",
                ],
            ],
            'fky_dozierende' => [
                'label' => 'Dozierende',
                'acf' => [
                    'type' => 'adot_relationship'
                ],
                'dbx' => [
                    'allow_new' => true,
                    'db' => [
                        'allow_new_data'       => ["id" => 0, "str_type" => 'some type', "fky_person_id" => 'some category'],
                        'tbx_table'            => 'evtmgr_tbx_workshops_presenters', 
                        'tbx_id_main'          => 'fky_workshop_id',
                        'tbx_id_linked'        => 'fky_person_id',
                        'linked_table'         => 'evtmgr_presenters',
                        'linked_label_sql'       => "CONCAT(IFNULL(str_last_name, ''), '–' ,IFNULL(str_first_name, ''))",
                        'linked_label'         => 'str_last_name',
                        'linked_condition'     => "$additional_sql_condition_for_tbx",
                        'linked_pid'           => '0'
                    ]
                ]
            ],
            'fky_timezone_id' => [
                'label' => 'Zeitzone',
                'class' => 'col-md-6',
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_timezones',
                        'id'    => 'id',
                        'label' => "CONCAT(IFNULL(dtm_time_from, ''), '–' ,IFNULL(dtm_time_to, ''), ' | ',IFNULL(str_timezone_name_de, ''))",
                        'condition'     => "fky_event_uid='$event_uid'",
                        'order_by' => 'dtm_time_from, str_timezone_name_de'
                    ]
                ],
                'acf' => [
                    'type' => 'adot_relationship'
                ]
            ],
            'fky_slot_id' => [
                'label' => 'Slot',
                'class' => 'col-md-6',
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_slots',
                        'id'    => 'id',
                        'label' => 'str_slot_name_de',
                        'condition'     => "fky_event_uid='$event_uid'",
                        'order_by' => 'str_slot_name_de'
                    ]
                ],
                'acf' => [
                    'type' => 'adot_relationship'
                ]
            ],
            'fky_room_id' => [
                'label' => 'Raum',
                'class' => 'col-md-6',
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_rooms',
                        'id'            => 'id',
                        'label' => 'str_room_de',
                        'condition'     => "fky_event_uid='$event_uid'",
                        'order_by' => 'str_room_de'
                    ]
                ],
                'acf' => [
                    'type' => 'adot_relationship'
                ]
            ],
            'fky_audience_id' => [
            'label' => 'Zielgruppen',
            'acf' => [
                'type' => 'adot_relationship'
            ],
            'dbx' => [
                    'allow_new' => true,
                    'db' => [
                        'allow_new_data'       => ["id" => 0, "str_type" => 'some type', "fky_person_id" => 'some category'],
                        'tbx_table'            => 'evtmgr_tbx_workshops_audience', 
                        'tbx_id_main'          => 'fky_workshop_id',
                        'tbx_id_linked'        => 'fky_audience_id',
                        'linked_table'         => 'evtmgr_audience',
                        'linked_label'         => 'str_audience_de',
                        'linked_condition'     => "$additional_sql_condition_for_tbx",
                        'linked_pid'           => '0'
                    ]
                ]
            ],
        ]
    ]);

