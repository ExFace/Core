-- UP

ALTER TABLE `exf_app`
    ADD COLUMN `description` VARCHAR(500) NULL DEFAULT NULL;

ALTER TABLE `exf_app`
    ADD COLUMN `docs_intro_path` VARCHAR(200) NULL DEFAULT NULL;

-- DOWN
-- Do not delete columns to avoid losing data!