<?php

require_once ADOT_SYS_COMPONENTS_PATH . '/email_sendout/classes/AdotEmailSendoutDatasource.php';
class Email_Invoice extends AdotEmailSendoutDatasource {
    public function __construct($options = []) {
        // All possible options are located in email_sendout/classes/AdotEmailSendoutDatasource.php
        parent::__construct(array_merge([
            'custom_page' => 'invoice-send-by-email',
            'title_base' => 'Rechnungen für Teilnehmende',
            'languages' => ["DE"],
            'language' => 'DE',
            'group' => "invoices",
            'vars_allowed' => [
                'str_first_name' => 'Vorname',
                'str_last_name' => 'Nachname',
            ],
            'tags_allowed' => [
                'attachement_pdf'=> 'Rechnung als PDF mitsenden',
                'change_billing_status' => 'Rechnungsstatus der belieferten Personen aktualisieren'
            ],
            'tags_default' => "attachement_pdf,change_billing_status",
            'filters' => [
                'type' => 'checkbox',
                //'layout' => 'horizontal',
                'choices' => [
                    '0' => "Alle Personen, welche die Rechnung noch nicht erhalten haben",
                    '1' => "Alle Personen, welche die Rechnung erhalten haben, aber noch nicht bezahlt haben",
                    '11' => "Alle Personen, welche die erste Mahnung erhalten haben, aber noch nicht bezahlt haben",
                    '12' => "Alle Personen, welche die zweite Mahnung erhalten haben, aber noch nicht bezahlt haben",
                    '13' => "Alle Personen, welche die dritte Mahnung erhalten haben, aber noch nicht bezahlt haben"
                ]
            ],
            "pdf_folder_abs" => wp_upload_dir()["path"],
            "pdf_folder_http" => wp_upload_dir()["url"],
            'hide_in_menu' => true
        ], $options));
    }

    // Override functions, to get real data:
    
    public function get_head_content(): string {
        ob_start();
        ?>
        <h1>Versand von Rechnungen</h1>
        Vor dem Versand müssen zuerst alle Rechnungen generiert werden.<br>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    public function get_persons_query(string $ids = "", string $filters = "", string $search = "", int $limit = 0): string {
        global $wpdb;
        $ids = $ids ? implode(",", array_map("absint", explode(",", $ids))) : '';
        $filters = $filters ? implode(",", array_map("absint", explode(",", $filters))) : '';
        $where = [];
        $data = [];
        if ($ids) {
            $where[] = "id in ({$ids})";
        }
        if ($filters) {
            $where[] = "int_billing_status in ({$filters})";
        }

        if ($search) {
            $search = implode("%", array_map(function($el) use ($wpdb){
                return $wpdb->esc_like($el);
            }, explode("*", $search)));
            $where[] = "CONCAT(ifnull(str_first_name, '-'), ' ', ifnull(str_last_name, '-'), ', ', ifnull(str_email, '-'), ', ID=', id) like %s";
            $data[] = '%' . $search . '%';
        }
        if (!count($where)){
            $where[] = '1 = 0';
        };
        $where_str = implode(" AND ", $where);
        $sql = "
            SELECT  
                id,
                CONCAT(ifnull(str_first_name, '-'), ' ', ifnull(str_last_name, '-'), ', ', ifnull(str_email, '-'), ', ID=', id) AS label, 
                str_email AS email,
                int_billing_status AS filter_value,
                str_language AS lang,
                str_invoice_pdf,
                str_first_name,
                str_last_name
            FROM 
                {$wpdb->prefix}evtmgr_persons 
            WHERE 
                {$where_str}
            ORDER BY label";
        if (count($data)) {
            $sql = $wpdb->prepare($sql, $data);
        }
        return $sql;
    }

    public function get_vars(array $st_person, string $str_filters_external = '', bool $is_test = false): array {
        $vars = [
            "str_first_name" => $st_person["str_first_name"],
            "str_last_name" => $st_person["str_last_name"]
        ];
        return $vars;
    }

    public function get_attachments(array $st_person, string $tags): array {

        if (!$st_person['str_invoice_pdf']){
            return [];
        }
        if (!in_array("attachement_pdf", explode(",", $tags))) {
            return [];
        }
        $files = [
            [
                "abs" => $this->options["pdf_folder_abs"] . "/{$st_person['str_invoice_pdf']}",
                "http" => $this->options["pdf_folder_http"] . "/{$st_person['str_invoice_pdf']}"
            ]
        ];
        return $files;
    }

    public function on_send(array $st_person, string $tags){
        global $wpdb;
        $billing_status = $st_person["filter_value"];
        $mapping_update_statuses = [
            "0" => "1",
            "1" => "11",
            "11" => "12",
            "12" => "13"
        ];
        if (in_array("change_billing_status", explode(",", $tags)) && !empty($mapping_update_statuses[$billing_status])) {
            $wpdb->update("{$wpdb->prefix}evtmgr_persons", ["int_billing_status" => $mapping_update_statuses[$billing_status]], ["id" => $st_person["id"]]);
        }
    }
}