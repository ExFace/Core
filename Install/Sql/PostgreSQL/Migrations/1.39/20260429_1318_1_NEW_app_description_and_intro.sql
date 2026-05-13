-- UP

ALTER TABLE exf_app
    ADD description VARCHAR(500) NULL;

ALTER TABLE exf_app
    ADD docs_intro_path VARCHAR(200) NULL;

-- DOWN
-- Do not delete columns to avoid losing data!