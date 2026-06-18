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
    $labels = (new Evtmgr_Database_Fields())->get_labels('wp_evtmgr_options');
?>

<?php
    $lang = 'de';
    $editor->add_table_config([
        'table' => 'evtmgr_options',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'evtmgr_options',
            'menu_title' => 'evtmgr_options',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],
        'list'  => [
            'fields' => ["str_option_description_{$lang}"],
            'fields_iframe' => ["str_option_description_{$lang}"],
            'orderby_default' => "str_option_description_{$lang}",
            'order_default' => 'asc',
            'condition' => "$additional_sql_condition",
            'labels' => [
                'title'     => 'evtmgr_options bearbeiten',
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
                'button_edit'   => 'Datensatz bearbeiten',
            ],
            'fields_visual' => '
                str_option_description_{{lang}}:col-md-{{lang_col_count}}
                str_option_name:col-lg-4 col-md-4
                str_option_value:col-lg-4 col-md-4'
                
        ],
        'fields' => [
            'id' => [
                'label'    => 'ID'
            ],
           'fky_event_uid' => [
                'label' => $labels['fky_event_uid'] ?? 'fky_event_uid',
                'acf' => [
                    'type' => 'text',
                    'readonly' => 'true',
                ],
            ],
           'str_option_description_{{lang}}' => [
                'label' => $labels['str_option_description_de'],
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                    'message' => '{{str_option_description}}',
                    'readonly'=> true,
                ]
            ],
           'str_option_name' => [
                'label' => $labels['str_option_name'] ?? 'str_option_name',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                    'instructions' => 'Variable im Code',
                ]
            ],
           'str_option_value' => [
                'label' => $labels['str_option_value'] ?? 'str_option_value',
                'acf' => [
                    'type' => 'text',
                    'instructions'=> '{{str_info_text_de}}',
                ]
            ],
           'str_option_type' => [
                'label' => $labels['str_option_type'] ?? 'str_option_type',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                ]
            ],
           'ysn_clone_value_on_copy' => [
                'label' => $labels['ysn_clone_value_on_copy'] ?? 'ysn_clone_value_on_copy',
                'acf' => [
                    'type' => 'true_false',
                    'readonly'=> true,
                ]
            ],
        ]
    ]);

/*
                    'instructions_placement' => ,

*/