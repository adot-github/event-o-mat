<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-helpers.php';

class Evtmgr_Pricing {

    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = 'wp_evtmgr_pricing';
    }

    public function get_pricing_top($event_uid, $lang = 'de') {
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_pricing_name_{$lang} AS str_pricing_name,
                mem_pricing_description_{$lang} AS mem_pricing_description
            FROM {$this->table_name}
            WHERE fky_pricing_parent_id = 0
              AND fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $event_uid),
            ARRAY_A
        );
    }

    public function get_pricing_by_id($pricing_id, $event_uid, $lang = 'de') {
        $pricing_id = absint($pricing_id);
        $event_uid   = sanitize_text_field($event_uid);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_pricing_name_{$lang} AS str_pricing_name,
                mem_pricing_description_{$lang} AS mem_pricing_description
            FROM {$this->table_name}
            WHERE id = %d
              AND fky_event_uid = %s
            LIMIT 1
        ";

        return $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $pricing_id, $event_uid),
            ARRAY_A
        );
    }

    public function get_pricing_by_parent($pricing_id, $event_uid, $lang = 'de') {
        $pricing_id = absint($pricing_id);
        $event_uid   = sanitize_text_field($event_uid);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_pricing_name_{$lang} AS str_pricing_name,
                mem_pricing_description_{$lang} AS mem_pricing_description
            FROM {$this->table_name}
            WHERE fky_pricing_parent_id = %d
              AND (dtm_date_valid_to >= CURDATE() OR dtm_date_valid_to IS NULL)
              AND fky_event_uid = %s
            ORDER BY int_sort_order
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $pricing_id, $event_uid),
            ARRAY_A
        );
    }

    public function get_pricing_by_parent_min_amount($pricing_id, $event_uid, $lang = 'de') {
        $pricing_id = absint($pricing_id);
        $event_uid   = sanitize_text_field($event_uid);
        $lang        = $this->sanitize_language($lang);

        $sql = "
            SELECT *,
                str_pricing_name_{$lang} AS str_pricing_name,
                mem_pricing_description_{$lang} AS mem_pricing_description
            FROM {$this->table_name}
            WHERE fky_pricing_parent_id = %d
              AND (dtm_date_valid_to >= CURDATE() OR dtm_date_valid_to IS NULL)
              AND fky_event_uid = %s
        ";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $pricing_id, $event_uid),
            ARRAY_A
        );
    }

    public function get_workshops_by_ids($workshop_ids, $event_uid, $lang = 'de') {
        $ids       = Event_Registration_Helpers::sanitize_ids($workshop_ids);
        $event_uid = sanitize_text_field($event_uid);
        $lang      = $this->sanitize_language($lang);

        if (empty($ids) || $event_uid === '') {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT *,
                str_workshop_title_{$lang} AS str_workshop_title,
                mem_workshop_description_{$lang} AS mem_workshop_description
            FROM wp_evtmgr_workshops
            WHERE id IN ($placeholders)
              AND fky_event_uid = %s
            ORDER BY str_workshop_number, str_workshop_title
        ";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_merge($ids, array($event_uid))),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    public function get_pricing_children_for_workshops($parent_pricing_id, $workshop_ids, $event_uid, $lang = 'de') {
        $parent_pricing_id = absint($parent_pricing_id);
        $ids               = Event_Registration_Helpers::sanitize_ids($workshop_ids);
        $event_uid         = sanitize_text_field($event_uid);
        $lang              = $this->sanitize_language($lang);

        if ($parent_pricing_id <= 0 || empty($ids) || $event_uid === '') {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT *,
                str_pricing_name_{$lang} AS str_pricing_name,
                mem_pricing_description_{$lang} AS mem_pricing_description
            FROM {$this->table_name}
            WHERE fky_pricing_parent_id = %d
              AND fky_workshop_id IN ($placeholders)
              AND (dtm_date_valid_to >= CURDATE() OR dtm_date_valid_to IS NULL)
              AND fky_event_uid = %s
            ORDER BY int_sort_order, str_pricing_name
        ";

        $params = array_merge(array($parent_pricing_id), $ids, array($event_uid));
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function build_registration_pricing_options($event_uid, $lang, $selected_workshop_ids) {
        $event_uid    = sanitize_text_field($event_uid);
        $lang         = $this->sanitize_language($lang);
        $workshop_ids = Event_Registration_Helpers::sanitize_ids($selected_workshop_ids);

        $parents   = $this->get_pricing_top($event_uid, $lang);
        $workshops = $this->get_workshops_by_ids($workshop_ids, $event_uid, $lang);

        $options = array();

        if (empty($parents)) {
            return $options;
        }

        foreach ($parents as $parent) {
            $parent_id = !empty($parent['id']) ? absint($parent['id']) : 0;

            if ($parent_id <= 0) {
                continue;
            }

            $parent_price = $this->normalize_price($parent['num_price'] ?? 0);
            $children     = $this->get_pricing_children_for_workshops($parent_id, $workshop_ids, $event_uid, $lang);
            $children_by_workshop = $this->group_pricing_children_by_workshop($children);

            $lines       = array();
            $pricing_ids = array($parent_id);
            $total       = $parent_price;

            $lines[] = array(
                'type'        => 'parent',
                'id'          => $parent_id,
                'billing_id'  => $parent_id,
                'label'       => (string) ($parent['str_pricing_name'] ?? ''),
                'description' => (string) ($parent['mem_pricing_description'] ?? ''),
                'amount'      => $parent_price,
                'is_total'    => false,
            );

            /*
             * Important:
             * Pricing children are workshop-specific. The default workshop price from
             * wp_evtmgr_workshops.num_price must be used for every selected workshop
             * that has no matching child pricing record for the current parent option.
             *
             * Old logic only used default workshop prices when there were no child
             * prices at all. That made the default price disappear in later groups as
             * soon as at least one child price existed for that parent.
             */
            foreach ($workshops as $workshop) {
                $workshop_id = !empty($workshop['id']) ? absint($workshop['id']) : 0;

                if ($workshop_id <= 0) {
                    continue;
                }

                if (!empty($children_by_workshop[$workshop_id])) {
                    foreach ($children_by_workshop[$workshop_id] as $child) {
                        $child_id    = !empty($child['id']) ? absint($child['id']) : 0;
                        $child_price = $this->normalize_price($child['num_price'] ?? 0);

                        if ($child_id > 0) {
                            $pricing_ids[] = $child_id;
                        }

                        $total += $child_price;

                        $lines[] = array(
                            'type'        => 'pricing_child',
                            'id'          => $child_id,
                            'billing_id'  => $child_id > 0 ? $child_id : $parent_id,
                            'workshop_id' => $workshop_id,
                            'label'       => (string) ($child['str_pricing_name'] ?? ''),
                            'description' => (string) ($child['mem_pricing_description'] ?? ''),
                            'amount'      => $child_price,
                            'is_total'    => false,
                        );
                    }

                    continue;
                }

                $workshop_price = $this->normalize_price($workshop['num_price'] ?? 0);

                if (abs($workshop_price) < 0.000001) {
                    continue;
                }

                $total += $workshop_price;

                $lines[] = array(
                    'type'        => 'workshop_price',
                    'id'          => $workshop_id,
                    'billing_id'  => $parent_id,
                    'workshop_id' => $workshop_id,
                    'label'       => $this->build_workshop_price_label($workshop),
                    'description' => '',
                    'amount'      => $workshop_price,
                    'is_total'    => false,
                );
            }

            $has_additional_lines = count($lines) > 1;

            if ($has_additional_lines) {
                $lines[] = array(
                    'type'        => 'total',
                    'id'          => 0,
                    'billing_id'  => 0,
                    'label'       => '$Kosten total£',
                    'description' => '',
                    'amount'      => $total,
                    'is_total'    => true,
                );
            }

            $pricing_ids = array_values(array_unique(array_filter(array_map('absint', $pricing_ids))));

            $options[] = array(
                'parent_id'            => $parent_id,
                'pricing_group'        => implode(',', $pricing_ids),
                'total_cost'           => $total,
                'parent'               => $parent,
                'lines'                => $lines,
                'has_additional_lines' => $has_additional_lines,
            );
        }

        return $options;
    }

    protected function group_pricing_children_by_workshop($children) {
        $grouped = array();

        if (empty($children) || !is_array($children)) {
            return $grouped;
        }

        foreach ($children as $child) {
            $workshop_id = !empty($child['fky_workshop_id']) ? absint($child['fky_workshop_id']) : 0;

            if ($workshop_id <= 0) {
                continue;
            }

            if (empty($grouped[$workshop_id])) {
                $grouped[$workshop_id] = array();
            }

            $grouped[$workshop_id][] = $child;
        }

        return $grouped;
    }

    protected function build_workshop_price_label($workshop) {
        $label_parts = array();

        if (!empty($workshop['str_workshop_number'])) {
            $label_parts[] = (string) $workshop['str_workshop_number'];
        }

        if (!empty($workshop['str_workshop_title'])) {
            $label_parts[] = (string) $workshop['str_workshop_title'];
        }

        return trim(implode(' ', $label_parts));
    }

    public function find_registration_pricing_option($event_uid, $lang, $selected_workshop_ids, $pricing_group) {
        $pricing_group = $this->normalize_pricing_group($pricing_group);
        $options = $this->build_registration_pricing_options($event_uid, $lang, $selected_workshop_ids);

        if (empty($options)) {
            return array();
        }

        if ($pricing_group !== '') {
            foreach ($options as $option) {
                if ($this->normalize_pricing_group($option['pricing_group'] ?? '') === $pricing_group) {
                    return $option;
                }
            }
        }

        return $options[0];
    }

    public function normalize_pricing_group($pricing_group) {
        $ids = Event_Registration_Helpers::sanitize_ids((string) $pricing_group);
        $ids = array_values(array_unique(array_filter($ids)));

        return implode(',', $ids);
    }

    public function normalize_price($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        $value = str_replace(array('CHF', 'chf', ' '), '', $value);
        $value = str_replace(array("'", '’'), '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }


    protected function sanitize_language($lang) {
        return Event_Registration_Helpers::sanitize_language($lang);
    }
}
