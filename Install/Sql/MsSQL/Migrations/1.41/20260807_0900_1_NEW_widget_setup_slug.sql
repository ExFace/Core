/*
 * Adds the slug column to exf_widget_setup, populates it with the page alias
 * from exf_page, then renames page_oid to delete_page_oid (nullable) to signal
 * it is deprecated.
 *
 * @author Saskia Hustinx
 */
-- UP
ALTER TABLE exf_widget_setup
    ADD slug nvarchar(2000) NOT NULL
        CONSTRAINT DF_exf_widget_setup_slug DEFAULT '';
GO

UPDATE ws
SET ws.slug = COALESCE(p.alias, '')
FROM exf_widget_setup ws
    INNER JOIN exf_page p ON p.oid = ws.page_oid;
GO

ALTER TABLE exf_widget_setup
    DROP CONSTRAINT DF_exf_widget_setup_slug;
GO

-- drop FKs for deprecated columns
ALTER TABLE exf_widget_setup
    DROP CONSTRAINT FK_exf_widget_setup_page;
GO

EXEC sp_rename 'exf_widget_setup.page_oid', 'delete_page_oid', 'COLUMN';
GO

ALTER TABLE exf_widget_setup
    ALTER COLUMN delete_page_oid binary(16) NULL;
GO

-- DOWN
ALTER TABLE exf_widget_setup
    ALTER COLUMN delete_page_oid binary(16) NOT NULL;
GO

EXEC sp_rename 'exf_widget_setup.delete_page_oid', 'page_oid', 'COLUMN';
GO

ALTER TABLE exf_widget_setup
    ADD CONSTRAINT FK_exf_widget_setup_page FOREIGN KEY (page_oid) REFERENCES exf_page (oid);
GO

-- Do not drop slug to avoid data loss!
