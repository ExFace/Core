-- UP

ALTER TABLE `exf_auth_point`
	ADD COLUMN `docs_path` VARCHAR(200) NOT NULL DEFAULT '' AFTER `target_action_applicable`;
	
-- DOWN

ALTER TABLE `exf_auth_point`
	DROP COLUMN `docs_path`;
	