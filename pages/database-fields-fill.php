erstelle eine neue klasse: classes/class_database_fields.php
all database operations with this table land here

erstelle eine neue Seite: pages/database-fields-extract.php
diese seite ruft die funktion auf: database-fields-extract()
select alle tabellen where name like *evtmgr*
select alle felder pro tabelle
ietriere über alle felder:
bereist vorhanden in wp_evtmgr_database_fields: nichts machen
nicht vorhanden: einfügen str_table_name, str_frm_field_name

generiere eine funktion get_labels($str_table_name);
sie gibt eine assoziates array zurück
$labels[str_frm_field_name]

füge die klasse oben in folgedne seite ein, so dass ich das array nutzen kann
wp_evtmgr_options.php 
