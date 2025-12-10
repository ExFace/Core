-- UP

ALTER TABLE dbo.exf_page_group_pages
    ADD app_oid BINARY(16) NULL;

ALTER TABLE dbo.exf_page_group_pages
    ADD CONSTRAINT FK_page_group_pages_app FOREIGN KEY (app_oid)
        REFERENCES exf_app (oid);

GO;

UPDATE exf_page_group_pages SET app_oid = (SELECT p.app_oid FROM exf_page p WHERE p.oid = exf_page_group_pages.page_oid);

-- DOWN

ALTER TABLE exf_page_group_pages
DROP CONSTRAINT FK_page_group_pages_app;

ALTER TABLE exf_page_group_pages
DROP COLUMN app_oid;