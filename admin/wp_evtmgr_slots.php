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
    $additional_sql_condition_for_tbx_timezone = $event_obj->get_current_event_sql_condition('wp_evtmgr_slots');
    $additional_sql_condition_for_tbx_timezone = $additional_sql_condition_for_tbx_timezone.' AND fky_slot_parent_id = 0';
    $labels = (new Evtmgr_Database_Fields())->get_labels('wp_evtmgr_slots');
?>

<?php
    $lang = 'de';
    $editor->add_table_config([
        'table' => 'evtmgr_slots',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'Slots',
            'menu_title' => 'Slots',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],
        'list'  => [
            'fields' => ["str_slot_name_{$lang}"],
            'fields_iframe' => ["str_slot_name_{$lang}"],
            'condition' => "$additional_sql_condition",
            'orderby_default' => "str_slot_name_{$lang}",
            'order_default' => 'asc',
            'labels' => [
                'title'     => 'Slots bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],
            'tree' => [
                'parent_field' => 'fky_slot_parent_id',
                'title_field'  => "str_slot_name_{$lang}",
                'filter_label' => 'Tree structure'
            ],
            
        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'Slots bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                fky_slot_parent_id:col-lg-3 col-md-4
                fky_timezone_id:col-lg-3 col-md-4
                str_slot_name_{{lang}}:col-md-{{lang_col_count}}
                mem_slot_description_{{lang}}:col-md-{{lang_col_count}}
                str_color:col-lg-3 col-md-4
                int_number_of_columns:col-lg-3 col-md-4
                ysn_show_title_row:col-lg-3 col-md-4
                ysn_print:col-lg-3 col-md-4
                int_sort:col-lg-3 col-md-4
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4
                fky_event_uid:col-lg-3 col-md-4'
                
        ],
        'fields' => [
            'id' => [
                'label' => $labels['id'] ?? 'ID'
            ],
            'fky_slot_parent_id' => [
                'label' => $labels['fky_slot_parent_id'] ?? 'Übergeordneter Slot',
                'acf' => [
                    'type' => 'acdb_relationship',    
                ],
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_slots',
                        'label' => 'str_slot_name_de',
                        'condition' => "$additional_sql_condition_for_tbx_timezone",
                        'order_by' => 'field_label',
                    ]
                ],
            ],
           'fky_event_uid' => [
                'label' => $labels['fky_event_uid'] ?? 'fky_event_uid',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                    'value' => $event_uid,
                ],

            ],
            'fky_timezone_id' => [
                'label' => $labels['fky_timezone_id'] ?? 'Zeitraster',
                'acf' => [
                    'type' => 'acdb_relationship'
                ],
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_timezones',
                        'id'    => 'id',
                        'label' => "CONCAT(IFNULL(dtm_time_from, ''), '–' ,IFNULL(dtm_time_to, ''), ' | ',IFNULL(str_timezone_name_de, ''))",
                        'condition'     => "fky_event_uid='$event_uid' AND fky_parent_timezone_id = 0",
                        'order_by' => 'dtm_time_from, str_timezone_name_de'
                    ]
                ]
            ],
           'str_slot_name_{{lang}}' => [
                'label' => $labels['str_slot_name_de'] ?? 'str_slot_name',
                    'formatter' => [
                        'list' => 'actions'
                    ],

                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'mem_slot_description_{{lang}}' => [
                'label' => $labels['mem_slot_description_de'] ?? 'mem_slot_description',
                'searchable' => true,
                'acf' => [
                    'type' => 'acdb_ckeditor',
                ],

                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'str_color' => [
                'label' => $labels['str_color'] ?? 'str_color',
                'acf' => [
                    'type' => 'color_picker'
                ]
            ],
           'int_number_of_columns' => [
                'label' => $labels['int_number_of_columns'] ?? 'int_number_of_columns',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'ysn_show_title_row' => [
                'label' => $labels['ysn_show_title_row'] ?? 'ysn_show_title_row',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'ysn_print' => [
                'label' => $labels['ysn_print'] ?? 'ysn_print',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'int_sort' => [
                'label' => $labels['int_sort'] ?? 'int_sort',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'dtm_date_created' => [
                'label' => $labels['dtm_date_created'] ?? 'dtm_date_created',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'dtm_date_updated' => [
                'label' => $labels['dtm_date_updated'] ?? 'dtm_date_updated',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
        ]
    ]);
