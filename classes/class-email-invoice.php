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
            'tags_default' => "attachement_pdf",
            'filters' => [
                'type' => 'radio',
                //'layout' => 'horizontal',
                'choices' => [
                    '0' => "Alle Personen, welche die Rechnung noch nicht erhalten haben",
                    '1' => "Alle Personen, welche die Rechnung erhalten haben, aber noch nicht bezahlt haben",
                    '11' => "Alle Personen, welche die erste Mahnung erhalten haben, aber noch nicht bezahlt haben",
                    '12' => "Alle Personen, welche die zweite Mahnung erhalten haben, aber noch nicht bezahlt haben",
                    '13' => "Alle Personen, welche die dritte Mahnung erhalten haben, aber noch nicht bezahlt haben"
                ]
            ],
            "pdf_folder" => wp_upload_dir()["path"],
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
            $where[] = "{$wpdb->prefix}evtmgr_registrations_billing.id in ({$ids})";
        }
        if ($filters) {
            $where[] = "{$wpdb->prefix}evtmgr_registrations_billing.int_billing_status in ({$filters})";
        }
        if ($search && $search != "*") {
            $search = str_replace("*", "%", $search);
            $search = implode("%", array_map(function($str){
                global $wpdb;
                return '%' . $wpdb->esc_like( $str ) . '%';
            }, explode("%", $search)));
            $where[] = "label like %s";
            $data[] = $search;
        }
        if (!count($where)){
            $where[] = '1 = 0';
        };
        $where_str = implode(" AND ", $where);
        $sql = "
            SELECT  
                {$wpdb->prefix}evtmgr_registrations_billing.id AS id, 
                CONCAT(str_first_name, ' ', str_last_name, ', ', str_email, ', ID=', {$wpdb->prefix}evtmgr_registrations_billing.id) AS label, 
                str_email AS email,
                int_billing_status AS filter_value,
                str_language AS lang,
                {$wpdb->prefix}evtmgr_persons.*,
                {$wpdb->prefix}evtmgr_registrations_billing.fky_congress_id,
                {$wpdb->prefix}evtmgr_registrations_billing.fky_person_id,
                {$wpdb->prefix}evtmgr_registrations_billing.id
            FROM 
                {$wpdb->prefix}evtmgr_persons INNER JOIN 
                    {$wpdb->prefix}evtmgr_registrations_billing ON {$wpdb->prefix}evtmgr_persons.id = {$wpdb->prefix}evtmgr_registrations_billing.fky_person_id
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
        $files = [
            $this->options["pdf_folder"] . "/{$st_person['str_invoice_pdf']}"
        ];
        return $files;
    }
}