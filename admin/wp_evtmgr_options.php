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
            'labels' => [
                'title'     => 'evtmgr_options bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],
            
        ],
        'langs' => function($table_config) {
            return ["de","fr","it"];
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'evtmgr_options bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz bearbeiten',
            ],
            'fields_visual' => '
                str_option_description_{{lang}}:col-md-{{lang_col_count}}
                str_option_value:col-lg-4 col-md-4

                str_option_type:col-lg-4 col-md-4
                str_option_name:col-lg-4 col-md-4
                ysn_clone_on_copy:col-lg-4 col-md-4
                fky_event_uid:col-lg-4 col-md-4'
                
        ],
        'fields' => [
            'id' => [
                'label'    => 'ID'
            ],
           'fky_event_uid' => [
                'label' => 'fky_event_uid',
                'acf' => [
                    'type' => 'text',    
                ],
            ],
           'str_option_description_{{lang}}' => [
                'label' => 'str_option_description',
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'langs' => [
                    'de' => ['label' => 'str_option_description (deutsch)'],
                    'fr' => ['label' => 'str_option_description (französisch)'],
                    'it' => ['label' => 'str_option_description (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'textarea',
                    'message' => '{str_option_description}',
                    'readonly'=> true,
                ]
            ],
           'str_option_name' => [
                'label' => 'str_option_name',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                ]
            ],
           'str_option_value' => [
                'label' => 'str_option_value',
                'acf' => [
                    'type' => 'text',
                    'instructions'=> '{{str_info_text_de}}',
                ]
            ],
           'str_option_type' => [
                'label' => 'str_option_type',
                'acf' => [
                    'type' => 'text',
                    'readonly'=> true,
                ]
            ],
           'ysn_clone_on_copy' => [
                'label' => 'ysn_clone_on_copy',
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