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
    $additional_sql_condition_for_tbx_timezone = $event_obj->get_current_event_sql_condition('wp_evtmgr_timezones');
    $additional_sql_condition_for_tbx_timezone = $additional_sql_condition_for_tbx_timezone.' AND fky_parent_timezone_id = 0';
?>

<?php
    $lang = 'de';
    $editor->add_table_config([
        'table' => 'evtmgr_timezones',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'evtmgr_timezones',
            'menu_title' => 'evtmgr_timezones',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],

        'list'  => [
            'fields' => ["str_timezone_name_{$lang}"],
            'fields_iframe' => ["id", "int_sort_order","str_timezone_name_{$lang}", "dtm_time_from" , "dtm_time_to"],
            'orderby_default' => "int_sort_order, str_timezone_name_{$lang}",
            'order_default' => 'asc',
            'condition' => "$additional_sql_condition",
            'labels' => [
                'title'     => 'Zeitplan bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],
            'tree' => [
                'parent_field' => 'fky_parent_timezone_id',
                'title_field'  => "str_timezone_name_{$lang}",
                'filter_label' => 'Filter für Kategorien'
            ],
        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'Zeitplan bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                tab:Texte
                id:col-lg-3 col-md-4
                str_timezone_code:col-lg-3 col-md-4
                fky_parent_timezone_id:col-lg-3 col-md-4
                int_sort_order:col-lg-3 col-md-4
                fky_presenters:col-lg-8 col-md-12
                -
                str_timezone_name_{{lang}}:col-md-{{lang_col_count}}
                mem_timezone_text_{{lang}}:col-md-{{lang_col_count}}
                mem_remark_on_no_registration_{{lang}}:col-md-{{lang_col_count}}
                -
                fky_event_uid:col-lg-3 col-md-4
                
                tab:Konfiguration
                
                str_dec_class:col-lg-3 col-md-4
                int_level:col-lg-3 col-md-4
                str_slots:col-lg-3 col-md-4
                dtm_day:col-lg-3 col-md-4
                dtm_time_from:col-lg-3 col-md-4
                dtm_time_to:col-lg-3 col-md-4
                
                str_color:col-lg-3 col-md-4
                str_css_class:col-lg-3 col-md-4
                int_price:col-lg-3 col-md-4
                -
                
                ysn_show_in_output:col-lg-3 col-md-4
                ysn_show_time_in_output:col-lg-3 col-md-4
                ysn_print_on_label:col-lg-3 col-md-4
                ysn_group_select_children:col-lg-3 col-md-4
                ysn_group_select_block_only:col-lg-3 col-md-4
                ysn_charge_always:col-lg-3 col-md-4
                -
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4'

        ],
        'fields' => [
        'id' => [
                'label' => 'id',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true
                ]
            ],
           'fky_parent_timezone_id' => [
                'label' => 'Übergeordnete Zeitzone',
                'acf' => [
                    'type' => 'adot_relationship',    
                ],
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_timezones',
                        'label' => 'str_timezone_name_de',
                        'condition' => "$additional_sql_condition_for_tbx_timezone",
                        'order_by' => 'field_label',
                    ]
                ],
            ],
           'int_sort_order' => [
                'label' => 'int_sort_order',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_dec_class' => [
                'label' => 'str_dec_class',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'int_level' => [
                'label' => 'int_level',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_slots' => [
                'label' => 'str_slots',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'dtm_day' => [
                'label' => 'dtm_day',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'dtm_time_from' => [
                'label' => 'dtm_time_from',
                'acf' => [
                    'type' => 'time_picker'
                ]
            ],
           'dtm_time_to' => [
                'label' => 'dtm_time_to',
                'acf' => [
                    'type' => 'time_picker'
                ]
            ],
           'str_timezone_code' => [
                'label' => 'str_timezone_code',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_timezone_name_{{lang}}' => [
                'label' => 'str_timezone_name',
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'langs' => [
                    'de' => ['label' => 'str_timezone_name (deutsch)'],
                    'fr' => ['label' => 'str_timezone_name (französisch)'],
                    'it' => ['label' => 'str_timezone_name (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'mem_timezone_text_{{lang}}' => [
                'label' => 'mem_timezone_text',
                'langs' => [
                    'de' => ['label' => 'mem_timezone_text (deutsch)'],
                    'fr' => ['label' => 'mem_timezone_text (französisch)'],
                    'it' => ['label' => 'mem_timezone_text (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'str_color' => [
                'label' => 'str_color',
                'acf' => [
                    'type' => 'color_picker'
                ]
            ],
            'str_css_class' => [
                'label'  => 'Anzeige',
                'acf' => [
                    'type' => 'select',
                    'choices' => [
                        'session' => 'Session',
                        'pitch' => 'Pitch',
                        'info' => 'Info',
                        'alert' => 'Warnung'
                    ]
                ]
            ],
           'int_price' => [
                'label' => 'int_price',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'ysn_show_time_in_output' => [
                'label' => 'ysn_show_time_in_output',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_show_in_output' => [
                'label' => 'ysn_show_in_output',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_print_on_label' => [
                'label' => 'ysn_print_on_label',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_group_select_children' => [
                'label' => 'ysn_group_select_children',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_group_select_block_only' => [
                'label' => 'ysn_group_select_block_only',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'ysn_charge_always' => [
                'label' => 'ysn_charge_always',
                'acf' => [
                    'type' => 'true_false',
                    'ui'            => 1,
                ]
            ],
           'mem_remark_on_no_registration_{{lang}}' => [
                'label' => 'mem_remark_on_no_registration',
                'langs' => [
                    'de' => ['label' => 'mem_remark_on_no_registration (deutsch)'],
                    'fr' => ['label' => 'mem_remark_on_no_registration (französisch)'],
                    'it' => ['label' => 'mem_remark_on_no_registration (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'fky_event_uid' => [
                'label' => 'fky_event_uid',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                    'default_value'=> $event_uid,
                ],
            ],
           'dtm_date_created' => [
                'label' => 'dtm_date_created',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true
                ]
            ],
           'dtm_date_updated' => [
                'label' => 'dtm_date_updated',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true
                ],
                'value_force' => 'now()'
            ],
            'fky_presenters' => [
            'label' => 'Dozierende',
            'acf' => [
                'type' => 'adot_relationship'
            ],
            'dbx' => [
                    'allow_new' => true,
                    'db' => [
                        'allow_new_data'       => ["id" => 0, "str_type" => 'some type', "fky_person_id" => 'some category'],
                        'tbx_table'            => 'evtmgr_tbx_timezones_presenters', 
                        'tbx_id_main'          => 'fky_timezone_id',
                        'tbx_id_linked'        => 'fky_person_id',
                        'linked_table'         => 'evtmgr_presenters',
                        'linked_label'         => "CONCAT(IFNULL(str_last_name, ''), '–' ,IFNULL(str_first_name, ''))",
                        'linked_label'         => 'str_last_name',
                        'linked_condition'     => "$additional_sql_condition_for_tbx",
                        'linked_pid'           => '0'
                    ]
                ]
            ]
        ]
    ]);
