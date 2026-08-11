/*
 * Adds the slug column to exf_widget_setup, populates it with the page alias
 * from exf_page, then renames page_oid to delete_page_oid (nullable) to signal
 * it is deprecated.
 *
 * @author Saskia Hustinx
 */
-- UP
ALTER TABLE exf_widget_setup
    ADD COLUMN slug varchar(2000) NOT NULL DEFAULT '';

UPDATE exf_widget_setup ws
SET slug = COALESCE(p.alias, '')
FROM exf_page p
WHERE p.oid = ws.page_oid;

ALTER TABLE exf_widget_setup
    ALTER COLUMN slug DROP DEFAULT;

-- dropd FKs for deprecated columns
ALTER TABLE exf_widget_setup
    RENAME COLUMN page_oid TO delete_page_oid;

ALTER TABLE exf_widget_setup
    ALTER COLUMN delete_page_oid DROP NOT NULL;

-- DOWN
ALTER TABLE exf_widget_setup
    RENAME COLUMN delete_page_oid TO page_oid;

ALTER TABLE exf_widget_setup
    ALTER COLUMN page_oid SET NOT NULL;

-- Do not drop slug to avoid data loss!
