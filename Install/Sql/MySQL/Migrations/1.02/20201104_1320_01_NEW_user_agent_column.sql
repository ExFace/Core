-- UP

ALTER TABLE `exf_queued_task` ADD `user_agent` VARCHAR(500) NULL AFTER `topics`;

-- DOWN

ALTER TABLE `exf_queued_task` DROP `user_agent`