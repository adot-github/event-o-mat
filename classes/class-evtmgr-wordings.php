<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Wordings {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_wordings';
    }

    /**
     * Get all wording texts for one owner/event and language.
     *
     * Usage:
     * $wordings = $wordings_obj->get_wordings($lang, $event_uid);
     * echo $wordings['notwendige_angaben_zur_anmeldung_sind_nicht_verfuegbar_eventuell'] ?? '';
     *
     * @param string $lang      Language suffix, for example de, en, fr, it.
     * @param string $event_uid Event / owner Uid, for example lll-2020-clone.
     * @return array Associative array: str_var_name => translated text.
     */
    public function get_wordings($lang = 'de', $event_uid = '') {
        $lang      = $this->sanitize_language($lang);
        $event_uid = sanitize_text_field((string) $event_uid);

        if ($event_uid === '') {
            return array();
        }

        $sql = "
            SELECT
                str_var_name,
                str_text_{$lang} AS text
            FROM {$this->table_name}
            WHERE fky_event_uid = %s OR fky_event_uid IS NULL
            ORDER BY (fky_event_uid IS NULL) DESC, str_var_name
        ";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );

        if (empty($rows)) {
            return array();
        }

        $wordings = array();

        foreach ($rows as $row) {
            if (empty($row['str_var_name'])) {
                continue;
            }

            $wordings[$row['str_var_name']] = isset($row['text']) ? $row['text'] : '';
        }

        return $wordings;
    }

    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}
