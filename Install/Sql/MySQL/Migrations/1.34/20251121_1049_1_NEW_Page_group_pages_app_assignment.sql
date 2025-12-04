-- UP

ALTER TABLE `exf_page_group_pages`
    ADD COLUMN `app_oid` BINARY(16) NULL DEFAULT NULL AFTER `page_group_oid`;

ALTER TABLE `exf_page_group_pages`
    ADD CONSTRAINT `FK_page_group_pages_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`) ON UPDATE NO ACTION ON DELETE NO ACTION;

-- BATCH-DELIMITER

UPDATE exf_page_group_pages SET app_oid = (SELECT p.app_oid FROM exf_page p WHERE p.oid = exf_page_group_pages.page_oid);

-- DOWN

ALTER TABLE `exf_page_group_pages`
    DROP CONSTRAINT `FK_page_group_pages_app`;

ALTER TABLE `exf_page_group_pages`
    DROP COLUMN `app_oid`;