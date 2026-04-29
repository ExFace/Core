-- UP

ALTER TABLE dbo.exf_app
    ADD description NVARCHAR(500) NULL;

ALTER TABLE dbo.exf_app
    ADD docs_intro_path NVARCHAR(200) NULL;

-- DOWN
-- Do not delete columns to avoid losing data!