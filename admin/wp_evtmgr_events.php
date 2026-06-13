<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    require_once get_stylesheet_directory() . '/db-custom/event-registration/classes/class-evtmgr-events.php';
    $event_obj = new Evtmgr_Events();
    $event_languages = $event_obj->get_current_event_languages();
    $event_uid = $event_obj->get_current_event_uid();
    $record_id = isset($_GET['record'])
    ? absint(wp_unslash($_GET['record']))
    : 0;
    $qry_events_active = $event_obj->get_event_by_id($record_id, 'de');
?>


<?php
    $lang = 'de';
    $root_config_id = $editor->add_table_config([
        'table' => 'evtmgr_events',
        'skin' => 'iframe',
        'menu' => [
            'page_title' => 'Event-Manager',
            'menu_title' => 'Event-Manager',
            'icon'       => get_stylesheet_directory_uri() . '/db-custom/event-registration/admin/assets/event-icon.png',
            'position'   => 32
        ],
        'list'  => [
            'fields' => ["str_event_name_de"],
            'fields_iframe' => ["str_event_name_de","id"],
            'orderby_default' => "str_event_name_de",
            'order_default' => 'asc',
            'labels' => [
                'title'     => 'Event bearbeiten',
                'button_add'   => 'Neuen Datensatz hinzufügen',
            ],

        ],
        'langs' => function($table_config) use ($event_languages) {
            return $event_languages;
        },
        'form'  => [
            'labels' => [
                'title_add'     => 'Neuen Datensatz hinzufügen',
                'title_edit'    => 'Event bearbeiten',
                'button_add'   => 'Datensatz speichern',
                'button_edit'   => 'Datensatz speichern',
            ],
            'fields_visual' => '
                tab:Texte
                str_event_name_{{lang}}:col-md-{{lang_col_count}}
                str_event_subtitle_{{lang}}:col-md-{{lang_col_count}}
                mem_event_description_{{lang}}:col-md-{{lang_col_count}}
                str_logo_text_1:col-lg-3 col-md-4
                str_logo_text_2:col-lg-3 col-md-4
                -
                id:col-lg-1
                event_uid:col-lg-2 col-md-2
                
                tab:Datum und Anmeldung
				dtm_event_date:col-lg-2 col-md-3
                dtm_registration_opened:col-lg-2 col-md-3
                dtm_registration_closed:col-lg-2 col-md-3
                mem_text_on_closed_{{lang}}:col-md-{{lang_col_count}}

                tab:E-Mail
                str_event_email_from:col-lg-3 col-md-4
                str_event_email_bcc:col-lg-3 col-md-4
                
                tab:Varia
                str_event_languages:col-lg-3 col-md-4
                str_event_color:col-lg-3 col-md-4
                str_image:col-lg-3 col-md-4
                -
                dtm_date_created:col-lg-3 col-md-4
                dtm_date_updated:col-lg-3 col-md-4
                '
                
        ],
        'fields' => [
        'id' => [
                'label' => 'ID',
                    'formatter' => [
                        'list' => 'actions'
                    ],
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                ]
            ],

           'str_event_name_{{lang}}' => [
                'label' => 'Name des Events',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_event_subtitle_{{lang}}' => [
                'label' => 'Untertitel',
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'mem_event_description_{{lang}}' => [
                'label' => 'Beschreibung',
                'langs' => [
                    'de' => ['label' => 'Beschreibung DE'],
                    'fr' => ['label' => 'Beschreibung FR'],
                    'it' => ['label' => 'Beschreibung IT'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'adot_ckeditor',
                ],
                'ckeditor' => [
                    'mode' => 'standalone',
                ]
            ],
           'str_event_email_from' => [
                'label' => 'Absender E-Mail',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_event_email_bcc' => [
                'label' => 'E-Mail BCC',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_event_languages' => [
                'label' => 'Sprachen des Events',
                'acf' => [
                    'type' => "checkbox",
                    'layout' => 'horizontal',
                    'default_value' => 'unbestimmt',
                    'choices' => [
                        'de' => 'deutsch',
                        'fr' => 'französisch',
                        'it' => 'italienisch'
                    ]
                ]
            ],
           'str_event_color' => [
                'label' => 'Farbe der Fusszeile',
                'acf' => [
                    'type' => 'color_picker'
                ]
            ],
           'dtm_event_date' => [
                'label' => 'Tag des Events',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'dtm_registration_opened' => [
                'label' => 'Beginn der Anmeldung',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'dtm_registration_closed' => [
                'label' => 'Ende der Anmeldung',
                'acf' => [
                    'type' => 'date_picker'
                ]
            ],
           'mem_text_on_closed_{{lang}}' => [
                'label' => 'Text, wenn die Anmeldung geschlossen ist',
                'langs' => [
                    'de' => ['label' => 'mem_text_on_closed (deutsch)'],
                    'fr' => ['label' => 'mem_text_on_closed (französisch)'],
                    'it' => ['label' => 'mem_text_on_closed (italienisch)'],
                ],
                'searchable' => true,
                'acf' => [
                    'type' => 'text'
                ]
            ],
           'str_logo_text_1' => [
                'label' => 'Logo-Text 1',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_logo_text_2' => [
                'label' => 'Logo-Text 2',
                'acf' => [
                    'type' => 'text',
                ]
            ],
           'str_image' => [
                'label' => 'Bild (2100×700px)',
                'acf' => [
                    'type' => 'adot_file_selector',
                    'file_type' => 'image',
                    'subfolder' => '/',
                    'image_width' => 300,
                    'image_height' => 300,
                ],
            ],
           'event_uid' => [
                'label' => 'UID',
                'acf' => [
                    'type' => 'text',
                    'readonly' => true,
                    'default_value'=> $event_uid,
                ],
            ],
           'dtm_date_created' => [
                'label' => 'Erstellt am',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly' => true,
                ]
            ],
           'dtm_date_updated' => [
                'label' => 'Geändert am',
                'acf' => [
                    'type' => 'date_time_picker',
                    'readonly' => true,
                ],
                'value_force' => 'now()'
            ],
        ]
    ]);