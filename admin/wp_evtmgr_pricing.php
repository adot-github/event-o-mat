<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
    $event_obj = new Evtmgr_Events();
    $event_languages = $event_obj->get_current_event_languages();
    $event_uid = $event_obj->get_current_event_uid();
    $additional_sql_condition = $event_obj->get_current_event_sql_condition();
    $additional_sql_condition_for_tbx_pricing = $event_obj->get_current_event_sql_condition('wp_evtmgr_pricing');
    $additional_sql_condition_for_tbx_pricing = $additional_sql_condition_for_tbx_pricing.' AND fky_pricing_parent_id = 0';
?>

<?php
    $lang = 'de';
    add_action('db_editor/after_save', function (string $table_name, int $record_id, array $data, array $table_config) {
        global $wpdb;

        $pricing_table = $wpdb->prefix . 'evtmgr_pricing';

        if ($table_name !== $pricing_table) {
            return;
        }

        $event_uid = $data['fky_event_uid'] ?? $wpdb->get_var(
            $wpdb->prepare("SELECT fky_event_uid FROM {$pricing_table} WHERE id = %d", $record_id)
        );

        if (!$event_uid) {
            return;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, fky_pricing_parent_id FROM {$pricing_table}
                 WHERE fky_event_uid = %s
                 ORDER BY int_sort_order, id",
                $event_uid
            ),
            ARRAY_A
        );

        // Group children by parent id
        $parents  = [];
        $children = [];
        foreach ($rows as $row) {
            $pid = (int) $row['fky_pricing_parent_id'];
            if ($pid === 0) {
                $parents[] = (int) $row['id'];
            } else {
                $children[$pid][] = (int) $row['id'];
            }
        }

        // Flatten: parent, then its children, then next parent, ...
        $ordered = [];
        foreach ($parents as $parent_id) {
            $ordered[] = $parent_id;
            foreach ($children[$parent_id] ?? [] as $child_id) {
                $ordered[] = $child_id;
            }
        }

        // Orphaned children (parent not in this event) appended at the end
        foreach ($children as $parent_id => $child_ids) {
            if (!in_array($parent_id, $parents, true)) {
                foreach ($child_ids as $child_id) {
                    $ordered[] = $child_id;
                }
            }
        }

        foreach ($ordered as $i => $id) {
            $wpdb->update(
                $pricing_table,
                ['int_sort_order' => ($i + 1) * 10],
                ['id' => $id],
                ['%d'],
                ['%d']
            );
        }
    }, 10, 4);

    $editor->add_table_config([
        'table' => 'evtmgr_pricing',
        'skin' => 'iframe',
        'menu' => [
            'menu_parent' => $root_config_id,
            'page_title' => 'evtmgr_pricing',
            'menu_title' => 'evtmgr_pricing',
            'icon'       => 'dashicons-text-page',
            'position'   => 40
        ],
        'list'  => [
            'drag_sort' => 'int_sort_order',
            'fields' => ["str_pricing_name_{$lang}"],
            'fields_iframe' => ["str_pricing_name_{$lang}", "num_price", "int_sort_order"],
            'orderby_default' => "int_sort_order,str_pricing_name_{$lang}",
            'condition' => "$additional_sql_condition",
            'order_default' => 'asc',
            'labels' => [
                'title'     => 'Preise bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],
            'tree' => [
                'parent_field' => 'fky_pricing_parent_id',
                'title_field'  => "str_pricing_name_{$lang}",
                'filter_label' => 'Tree structure'
            ],
        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'evtmgr_pricing bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                fky_pricing_parent_id:col-lg-3 col-md-4
                fky_workshop_id:col-lg-3 col-md-4
                str_pricing_name_{{lang}}:col-md-{{lang_col_count}}
                mem_pricing_description_{{lang}}:col-md-{{lang_col_count}}
                num_price:col-lg-3 col-md-4
                dtm_date_valid_from:col-lg-3 col-md-4
                dtm_date_valid_to:col-lg-3 col-md-4
                int_sort_order:col-lg-3 col-md-4
                ysn_proof_required:col-lg-3 col-md-4
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4
                fky_event_uid:col-lg-3 col-md-4'
                
        ],
        'fields' => [
            'id' => [
                'label'    => 'ID'
            ],
           'fky_pricing_parent_id' => [
                'label' => 'Übergeordnete Gruppe',
                'acf' => [
                    'type' => 'adot_relationship',    
                ],
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_pricing',
                        'label' => 'str_pricing_name_de',
                        'condition' => "$additional_sql_condition_for_tbx_pricing",
                        'order_by' => 'str_pricing_name_de',
                    ]
                ],
            ],

           'fky_event_uid' => [
                'label' => 'fky_event_uid',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                    'default_value'=> $event_uid,
                ]
            ],

           'fky_workshop_id' => [
                'label' => 'Workshops',
                'acf' => [
                    'type' => 'adot_relationship',    
                ],
                'fky' => [
                    'db' => [
                        'table' => 'evtmgr_workshops',
                        'label' => 'str_workshop_title_de',
                        'condition' => "$additional_sql_condition",
                        'order_by' => 'str_workshop_title_de',
                    ]
                ],
            ],
           'str_pricing_name_{{lang}}' => [
                'label' => 'str_pricing_name',
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'langs' => [
                    'de' => ['label' => 'str_pricing_name (deutsch)'],
                    'fr' => ['label' => 'str_pricing_name (französisch)'],
                    'it' => ['label' => 'str_pricing_name (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'mem_pricing_description_{{lang}}' => [
                'label' => 'mem_pricing_description',
                'langs' => [
                    'de' => ['label' => 'mem_pricing_description (deutsch)'],
                    'fr' => ['label' => 'mem_pricing_description (französisch)'],
                    'it' => ['label' => 'mem_pricing_description (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',    
                ]
            ],
           'num_price' => [
                'label' => 'num_price',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'dtm_date_valid_from' => [
                'label' => 'dtm_date_valid_from',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'dtm_date_valid_to' => [
                'label' => 'dtm_date_valid_to',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'int_sort_order' => [
                'label' => 'int_sort_order',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'ysn_proof_required' => [
                'label' => 'ysn_proof_required',
                'acf' => [
                    'type' => 'true_false',
                ]
            ],
           'dtm_date_created' => [
                'label' => 'dtm_date_created',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true,
                ]
            ],
           'dtm_date_updated' => [
                'label' => 'dtm_date_updated',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly'=> true,
                ],
                'value_force' => 'now()'
            ],
        ]
    ]);
