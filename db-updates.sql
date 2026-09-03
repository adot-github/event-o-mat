-- ============================================================================
--  event-registration – DB-Updates (Schema-Angleich zweite Website)
--
--  MySQL 8.0-kompatibel: KEIN "ADD COLUMN IF NOT EXISTS" (MariaDB-only).
--  Jeder Block prüft zuerst information_schema und ist mehrfach ausführbar.
--  Tabellen-Präfix ggf. anpassen (hier: wp_).
-- ============================================================================


-- ----------------------------------------------------------------------------
--  1) wp_evtmgr_workshop_types.str_event_typename_plural_{de,fr,it,en}
--     Pluralform des Workshop-Typ-Namens ("Workshops", "Keynotes" …).
--     Genutzt von Evtmgr_Workshops::get_workshop_types_for_output()
--     (Zwischentitel im Shortcode presenters_by_workshop_type).
-- ----------------------------------------------------------------------------
SET @ddl := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `wp_evtmgr_workshop_types`
            ADD COLUMN `str_event_typename_plural_de` VARCHAR(255) NULL DEFAULT NULL AFTER `str_event_typename_en`,
            ADD COLUMN `str_event_typename_plural_fr` VARCHAR(255) NULL DEFAULT NULL AFTER `str_event_typename_plural_de`,
            ADD COLUMN `str_event_typename_plural_it` VARCHAR(255) NULL DEFAULT NULL AFTER `str_event_typename_plural_fr`,
            ADD COLUMN `str_event_typename_plural_en` VARCHAR(255) NULL DEFAULT NULL AFTER `str_event_typename_plural_it`',
        'DO 0'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'wp_evtmgr_workshop_types'
      AND COLUMN_NAME  = 'str_event_typename_plural_de'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ----------------------------------------------------------------------------
--  2) wp_evtmgr_events.str_event_url
--     Öffentliche Event-/Anmelde-URL (einsprachig). Genutzt von
--     Evtmgr_Pdf_Content::get_event_url() (Booklet, QR-Code, Shortcodes).
-- ----------------------------------------------------------------------------
SET @ddl := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `wp_evtmgr_events`
            ADD COLUMN `str_event_url` VARCHAR(255) NOT NULL DEFAULT ''''
            AFTER `dtm_registration_closed`',
        'DO 0'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'wp_evtmgr_events'
      AND COLUMN_NAME  = 'str_event_url'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ----------------------------------------------------------------------------
--  3) wp_evtmgr_workshops.str_workshop_pdf_{de,fr,it,en}
--     Dateiname/Pfad des workshop-spezifischen PDFs je Sprache.
--     Eingefügt nach mem_workshop_description_long_it.
-- ----------------------------------------------------------------------------
SET @ddl := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `wp_evtmgr_workshops`
            ADD COLUMN `str_workshop_pdf_de` VARCHAR(255) NULL DEFAULT NULL AFTER `mem_workshop_description_long_it`,
            ADD COLUMN `str_workshop_pdf_fr` VARCHAR(255) NULL DEFAULT NULL AFTER `str_workshop_pdf_de`,
            ADD COLUMN `str_workshop_pdf_it` VARCHAR(255) NULL DEFAULT NULL AFTER `str_workshop_pdf_fr`,
            ADD COLUMN `str_workshop_pdf_en` VARCHAR(255) NULL DEFAULT NULL AFTER `str_workshop_pdf_it`',
        'DO 0'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'wp_evtmgr_workshops'
      AND COLUMN_NAME  = 'str_workshop_pdf_de'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ----------------------------------------------------------------------------
--  3b) wp_evtmgr_events.str_event_pdf_{de,fr,it,en}
--      Dateiname des Booklet-PDFs je Sprache ("booklet-<slug des Event-Titels>.pdf").
--      Befüllt von Evtmgr_Events::event_update_booklet_pdf_filenames()
--      (Aufruf aus admin/pages/booklet-pdf-create.php); Evtmgr_Booklet::generate()
--      nimmt den Namen aus diesem Feld. Eingefügt nach mem_event_description_it.
-- ----------------------------------------------------------------------------
SET @ddl := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `wp_evtmgr_events`
            ADD COLUMN `str_event_pdf_de` VARCHAR(255) NULL DEFAULT NULL AFTER `mem_event_description_it`,
            ADD COLUMN `str_event_pdf_fr` VARCHAR(255) NULL DEFAULT NULL AFTER `str_event_pdf_de`,
            ADD COLUMN `str_event_pdf_it` VARCHAR(255) NULL DEFAULT NULL AFTER `str_event_pdf_fr`,
            ADD COLUMN `str_event_pdf_en` VARCHAR(255) NULL DEFAULT NULL AFTER `str_event_pdf_it`',
        'DO 0'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'wp_evtmgr_events'
      AND COLUMN_NAME  = 'str_event_pdf_de'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ----------------------------------------------------------------------------
--  4) Uploads-Ordnerstruktur: gespeicherte Pfade auf die neue Struktur ziehen
--     (Ordner-Moves selbst: uploads-restructure.sh). Mehrfach ausführbar –
--     REPLACE auf bereits migrierten Werten ist ein No-op.
--       <event>/presenters/     -> <event>/assets/presenter-images/
--       .../partner-logos/       -> .../assets/partner-logos/
-- ----------------------------------------------------------------------------
UPDATE wp_evtmgr_presenters
   SET str_person_image = REPLACE(str_person_image,
        CONCAT(fky_event_uid, '/presenters/'),
        CONCAT(fky_event_uid, '/assets/presenter-images/'))
 WHERE str_person_image LIKE CONCAT('%', fky_event_uid, '/presenters/%');

UPDATE wp_evtmgr_sponsors_und_partner SET str_sponsor_logo_de =
   REPLACE(str_sponsor_logo_de, '/partner-logos/', '/assets/partner-logos/')
 WHERE str_sponsor_logo_de LIKE '%/partner-logos/%' AND str_sponsor_logo_de NOT LIKE '%/assets/partner-logos/%';
UPDATE wp_evtmgr_sponsors_und_partner SET str_sponsor_logo_fr =
   REPLACE(str_sponsor_logo_fr, '/partner-logos/', '/assets/partner-logos/')
 WHERE str_sponsor_logo_fr LIKE '%/partner-logos/%' AND str_sponsor_logo_fr NOT LIKE '%/assets/partner-logos/%';
UPDATE wp_evtmgr_sponsors_und_partner SET str_sponsor_logo_it =
   REPLACE(str_sponsor_logo_it, '/partner-logos/', '/assets/partner-logos/')
 WHERE str_sponsor_logo_it LIKE '%/partner-logos/%' AND str_sponsor_logo_it NOT LIKE '%/assets/partner-logos/%';
