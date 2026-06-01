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
            'fields' => ["text_{$lang}"],
            'fields_iframe' => ["text_{$lang}"],
            'orderby_default' => "text_{$lang}",
            'condition' => "$additional_sql_condition",
            'order_default' => 'asc',
            'labels' => [
                'title'     => 'evtmgr_wordings bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],
            
        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'evtmgr_wordings bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz bearbeiten',
            ],
            'fields_visual' => '
                str_template:col-lg-3 col-md-4
                str_var_string:col-lg-3 col-md-4
                str_var_string_short:col-lg-3 col-md-4
                str_var_name:col-lg-3 col-md-4
                s_var_name_cf:col-lg-3 col-md-4
                text_de_for_tree:col-lg-3 col-md-4
                text_{{lang}}:col-md-{{lang_col_count}}
                text_be:col-lg-3 col-md-4
                str_group:col-lg-3 col-md-4
                int_num_of_occurences:col-lg-3 col-md-4
                int_len_of_german:col-lg-3 col-md-4
                translate:col-lg-3 col-md-4
                fky_event_uid:col-lg-3 col-md-4
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4'
        ],
        'fields' => [
            'id' => [
                'label'    => 'ID'
            ],
           'str_template' => [
                'label' => 'str_template',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_var_string' => [
                'label' => 'str_var_string',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_var_string_short' => [
                'label' => 'str_var_string_short',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_var_name' => [
                'label' => 'str_var_name',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           's_var_name_cf' => [
                'label' => 's_var_name_cf',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'text_de_for_tree' => [
                'label' => 'text_de_for_tree',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'text_{{lang}}' => [
                'label' => 'text',
                'langs' => [
                    'de' => ['label' => 'text (deutsch)'],
                    'fr' => ['label' => 'text (französisch)'],
                    'it' => ['label' => 'text (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'str_group' => [
                'label' => 'str_group',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'int_num_of_occurences' => [
                'label' => 'int_num_of_occurences',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'int_len_of_german' => [
                'label' => 'int_len_of_german',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'translate' => [
                'label' => 'translate',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'fky_event_uid' => [
                'label' => 'fky_event_uid',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ],
            ],
           'dtm_date_created' => [
                'label' => 'dtm_date_created',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'dtm_date_updated' => [
                'label' => 'dtm_date_updated',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
        ]
    ]);
