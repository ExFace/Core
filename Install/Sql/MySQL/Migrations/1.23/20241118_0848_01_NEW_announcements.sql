-- UP

ALTER TABLE `exf_notification`
ADD `hide_from_inbox` tinyint NOT NULL DEFAULT '0',
ADD `folder` varchar(100) NULL AFTER `hide_from_inbox`,
ADD `sender` varchar(100) NULL;

UPDATE exf_notification SET sender = (SELECT u.username FROM exf_user u WHERE u.oid = exf_notification.created_by_user_oid) WHERE sender = NULL;

ALTER TABLE `exf_notification`
ADD INDEX `NotificationContext` (`user_oid`, `read_on`, `hide_from_inbox`, `created_on`);

-- DOWN

ALTER TABLE `exf_notification`
	DROP COLUMN `hide_from_inbox`,
				`folder`,
				`sender`;
