<?php
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class_database_fields.php';
    $event_obj = new Evtmgr_Events();
    $event_languages = $event_obj->get_current_event_languages();
    $event_uid = $event_obj->get_current_event_uid();
    $additional_sql_condition = $event_obj->get_current_event_sql_condition();
    $additional_sql_condition_for_tbx = $event_obj->get_current_event_sql_condition('record');
    $labels = (new Evtmgr_Database_Fields())->get_labels('wp_evtmgr_wordings');
?>

<?php
    $lang = 'de';
    $editor->add_table_config([
        'table' => 'evtmgr_wordings',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'evtmgr_wordings',
            'menu_title' => 'evtmgr_wordings',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],
        'list'  => [
            'fields' => ["str_text_for_tree"],
            'fields_iframe' => ["str_text_for_tree"],
            'orderby_default' => "str_text_for_tree",
            'order_default' => 'asc',
            'condition' => "$additional_sql_condition",
            'labels' => [
                'title'     => 'Wordings bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],

            'screen_options' => [
                 'custom_filter_1' => [
                    'label' => __("Filter", "domain"),
                    'default' => '',
                    'type' => 'select',
                    'callback' => function($value){
                    $sql = '';
                    if ($value){
                        $value = esc_sql($value);
                        $sql = "FIND_IN_SET('{$value}', str_group)";
                    }
                    return $sql;
                        },
                    'db' => [
                        'table' => 'evtmgr_wordings',
                        'field_single' => 'str_group',
                    ]
                ]
            ]

        ],
        'langs' => function($table_config) {
            return ["de","fr","it"];
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'evtmgr_wordings bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                str_var_name:col-lg-3 col-md-4
                str_text_{{lang}}:col-md-{{lang_col_count}}
                str_group:col-lg-3 col-md-4
                int_num_of_occurences:col-lg-3 col-md-4
                int_len_of_german:col-lg-3 col-md-4
                translate:col-lg-3 col-md-4
                fky_event_uid:col-lg-3 col-md-4
                str_type_of_edit:col-lg-3 col-md-4
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4'

        ],
        'fields' => [
            'id' => [
                'label' => $labels['id'] ?? 'ID'
            ],
           'str_template' => [
                'label' => $labels['str_template'] ?? 'str_template',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_backup' => [
                'label' => $labels['str_backup'] ?? 'str_backup',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_var_string' => [
                'label' => $labels['str_var_string'] ?? 'str_var_string',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_var_string_short' => [
                'label' => $labels['str_var_string_short'] ?? 'str_var_string_short',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_var_name' => [
                'label' => $labels['str_var_name'] ?? 'str_var_name',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_text_for_tree' => [
                'label' => $labels['str_text_for_tree'] ?? 'str_text_for_tree',
                'formatter' => [
                    'list' => 'actions'
                ],
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_text_{{lang}}' => [
                'label' => $labels['str_text_de'] ?? 'str_text',

                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ],
                'before_render' => function($field_config, $record) {
                    if (($record['str_type_of_edit'] ?? '') !== 'text') {
                        $field_config['acf']['type'] = 'adot_ckeditor';
                    }
                    return $field_config;
                },
            ],
           'str_type_of_edit' => [
                'label' => $labels['str_type_of_edit'] ?? 'str_type_of_edit',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'str_group' => [
                'label' => $labels['str_group'] ?? 'str_group',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'int_num_of_occurences' => [
                'label' => $labels['int_num_of_occurences'] ?? 'int_num_of_occurences',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'int_len_of_german' => [
                'label' => $labels['int_len_of_german'] ?? 'int_len_of_german',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'translate' => [
                'label' => $labels['translate'] ?? 'translate',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],
           'fky_event_uid' => [
                'label' => $labels['fky_event_uid'] ?? 'fky_event_uid',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                    'default_value'=> $event_uid,
                ],
            ],
           'dtm_date_created' => [
                'label' => $labels['dtm_date_created'] ?? 'dtm_date_created',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly' => true,
                ]
            ],
           'dtm_date_updated' => [
                'label' => $labels['dtm_date_updated'] ?? 'dtm_date_updated',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly' => true,
                ],
                'value_force' => 'now()'
            ],
        ]
    ]);
