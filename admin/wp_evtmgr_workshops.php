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
    $event_uid = $event_obj->get_current_event_uid(true);
    $labels = (new Evtmgr_Database_Fields())->get_labels('wp_evtmgr_workshops');
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
                'title'     => 'Workshops bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],

            'screen_options' => [
                'filter_by_workshop_type' => [
                    'label' => __("Workshop-Typ", "domain"),
                    'default' => '',
                    'type' => 'select',
                    'callback' => function($value){
                        $sql = '';
                        if ($value){
                            $value = absint($value);
                            $sql = "fky_workshop_type = {$value}";
                        }
                        return $sql;
                    },
                    'db' => [
                        'table' => 'evtmgr_workshop_types',
                        'label' => 'str_event_typename_de',
                        'condition' => "fky_event_uid='$event_uid'"
                    ]
                ]
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
                fky_workshop_type:col-lg-3 col-md-4
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

                ysn_print:col-lg-3 col-md-4
                ysn_online:col-lg-3 col-md-4
                ysn_auto_register:col-lg-3 col-md-4
                ysn_no_registration_possible:col-lg-3 col-md-4
                ysn_booked_out:col-lg-3 col-md-4
                int_number_of_registrations:col-lg-3 col-md-4
                int_max_number_of_registrations:col-lg-3 col-md-4
                num_price:col-lg-3 col-md-4

                tab:Konfiguration
                fky_slot_id:col-lg-6 col-md-8
                fky_timezone_id:col-lg-6 col-md-8
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
                'label' => $labels['id'] ?? 'ID',
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
                'label' => $labels['str_workshop_number'] ?? 'Workshop-Nummer',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'int_sort_print' => [
                'label' => $labels['int_sort_print'] ?? 'Sortierung',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_workshop_title_{{lang}}' => [
                'label' => $labels['str_workshop_title_de'] ?? 'Titel des Workshops',
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_workshop_subtitle_{{lang}}' => [
                'label' => $labels['str_workshop_subtitle_de'] ?? 'Untertitel des Workshops',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_workshop_type' => [
                'label' => $labels['str_workshop_type'] ?? 'Workshop-Typ',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'mem_workshop_description_{{lang}}' => [
                'label' => $labels['mem_workshop_description_de'] ?? 'Beschreibung',
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'mem_workshop_description_long_{{lang}}' => [
                'label' => $labels['mem_workshop_description_long_de'] ?? 'Beschreibung detailliert',
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
                'label' => $labels['mem_comments'] ?? 'Interne Bemerkungen',
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
                'label' => $labels['ysn_booked_out'] ?? 'Ausgebucht',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_print' => [
                'label' => $labels['ysn_print'] ?? 'Erscheint in Broschüre',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_online' => [
                'label' => $labels['ysn_online'] ?? 'Erscheint Online',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_auto_register' => [
                'label' => $labels['ysn_auto_register'] ?? 'Obligatorische&nbsp;Anmeldung',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_no_registration_possible' => [
                'label' => $labels['ysn_no_registration_possible'] ?? 'Keine Anmeldung',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'int_number_of_registrations' => [
                'label' => $labels['int_number_of_registrations'] ?? 'Anzahl Anmeldungen',
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
                'label' => $labels['int_max_number_of_registrations'] ?? 'Max. Anzahl Teilnehmende',
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
                'label' => $labels['num_price'] ?? 'Preis',
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
                'label' => $labels['ysn_no_discount'] ?? 'Nicht Rabatt-berechtigt',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],

           'int_typo_count' => [
                'label' => $labels['int_typo_count'] ?? 'int_typo_count',
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
                'label' => $labels['str_typo_count'] ?? 'str_typo_count',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'mem_workshop_description_short_print' => [
                'label' => $labels['mem_workshop_description_short_print'] ?? 'Beschreibung lang DE',
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
                'label' => $labels['mem_workshop_description_print'] ?? 'Beschreibung lang DE',
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
                'label' => $labels['str_audience_print'] ?? 'str_audience_print',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_time_print' => [
                'label' => $labels['str_time_print'] ?? 'str_time_print',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_room_print' => [
                'label' => $labels['str_room_print'] ?? 'str_room_print',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_presenters_print' => [
                'label' => $labels['str_presenters_print'] ?? 'str_presenters_print',
                'acf' => [
                    'type' => 'text',
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
           'dtm_date_created' => [
                'label' => $labels['dtm_date_created'] ?? 'Erstellt am',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true,
                ]
            ],
           'fky_event_uid' => [
                'label' => $labels['fky_event_uid'] ?? 'UID',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                    'default_value' => "$event_uid",
                ],
            ],
            'fky_dozierende' => [
                'label' => $labels['fky_dozierende'] ?? 'Dozierende',
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
                        'linked_label_sql'       => "CONCAT(IFNULL(str_last_name, ''), ' ' ,IFNULL(str_first_name, ''))",
                        'linked_label'         => 'str_last_name',
                        'linked_condition'     => "$additional_sql_condition_for_tbx",
                        'linked_pid'           => '0'
                    ]
                ]
            ],
            'fky_timezone_id' => [
                'label' => $labels['fky_timezone_id'] ?? 'Zeitzone',
                'class' => 'col-md-6',
                'fky' => [
                    'db' => [
                        'table'     => "evtmgr_timezones tz LEFT JOIN wp_evtmgr_timezones parent_tz ON parent_tz.id = tz.fky_parent_timezone_id",
                        'id'        => 'tz.id',
                        'label'     => "CONCAT(IFNULL(CONCAT(parent_tz.str_timezone_name_de, '➜ '), ''), IFNULL(tz.str_timezone_name_de, ''))",
                        'condition' => "tz.fky_event_uid='$event_uid'",
                        'order_by'  => 'tz.fky_parent_timezone_id, tz.dtm_time_from',
                    ]
                ],
                'acf' => [
                    'type' => 'adot_relationship'
                ]
            ],
            'fky_slot_id' => [
                'label' => $labels['fky_slot_id'] ?? 'Slot',
                'class' => 'col-md-6',
                'fky' => [
                    'db' => [
                        'table'     => "evtmgr_slots s LEFT JOIN wp_evtmgr_timezones tz ON tz.id = s.fky_timezone_id",
                        'id'        => 's.id',
                        'label'     => "CONCAT(IFNULL(s.str_slot_name_de, ''), IFNULL(CONCAT(' (', tz.str_timezone_name_de, ')'), ''))",
                        'condition' => "s.fky_event_uid='$event_uid'",
                        'order_by'  => 's.str_slot_name_de',
                    ]
                ],
                'acf' => [
                    'type' => 'adot_relationship'
                ]
            ],
            'fky_room_id' => [
                'label' => $labels['fky_room_id'] ?? 'Raum',
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
            'fky_workshop_type' => [
                'label' => $labels['fky_workshop_type'] ?? 'Workshop-Typ',
                'class' => 'col-md-6',
                'fky' => [
                    'db' => [
                        'table'     => 'evtmgr_workshop_types',
                        'id'        => 'id',
                        'label'     => 'str_event_typename_de',
                        'condition' => "fky_event_uid='$event_uid'",
                        'order_by'  => 'str_event_typename_de',
                    ]
                ],
                'acf' => [
                    'type' => 'adot_relationship'
                ]
            ],
            'fky_audience_id' => [
            'label' => $labels['fky_audience_id'] ?? 'Zielgruppen',
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

