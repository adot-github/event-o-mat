
<?php

$report_title = 'Teilnehmende mit Workshops';

$report_fields = array(
    'id'                => 'id',
    'str_first_name'    => 'Vorname',
    'str_last_name'     => 'Nachname',
    'str_institution'   => 'Institution',
    'work_shop_titles'  => 'Workshops',
);

$fields_as_list = array(
    'work_shop_titles',
);

$report_custom_sql = "
    SELECT
        p.id,
        p.str_salutation,
        p.str_academic_title,
        p.str_first_name,
        p.str_last_name,
        p.str_city,
        p.str_email,
        p.str_phone,
        p.str_institution,
        p.str_language,
        p.fky_event_uid,
        workshops.work_shop_titles

    FROM wp_evtmgr_persons AS p

    LEFT JOIN (
        SELECT
            rw.fky_person_id,
            rw.fky_event_uid,
            GROUP_CONCAT(
                DISTINCT w.str_workshop_title_de
                ORDER BY w.str_workshop_title_de
                SEPARATOR '¦ '
            ) AS work_shop_titles
        FROM wp_evtmgr_registrations_workshops AS rw
        LEFT JOIN wp_evtmgr_workshops AS w
            ON w.id = rw.fky_workshop_id
        GROUP BY
            rw.fky_person_id,
            rw.fky_event_uid
    ) AS workshops
        ON workshops.fky_person_id = p.id
       AND workshops.fky_event_uid = p.fky_event_uid
";

$report_owner_column = 'fky_event_uid';

require __DIR__ . '/report-creation.php';
