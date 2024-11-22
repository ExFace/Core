-- UP

ALTER TABLE `exf_notification`
	ADD `hide_from_inbox` tinyint NOT NULL DEFAULT '0',
	ADD `folder` varchar(100) NULL,
	ADD `sent_by` varchar(100) NULL,
	ADD `sent_on` datetime NULL,
	ADD `reference` varchar(200) NULL;

UPDATE exf_notification SET sent_by = (SELECT COALESCE(u.username, '') FROM exf_user u WHERE u.oid = exf_notification.created_by_user_oid) WHERE sender = NULL;
UPDATE exf_notification SET sent_on = created_on;

ALTER TABLE `exf_notification`
	CHANGE `sent_by` `sent_by` varchar(100) NOT NULL,
	CHANGE `sent_on` `sent_on` datetime NOT NULL;

ALTER TABLE `exf_notification`
ADD INDEX `IDX_NotificationContext` (`user_oid`, `read_on`, `hide_from_inbox`, `sent_on`);

ALTER TABLE `exf_notification`
ADD INDEX `IDX_Announcement_search` (`user_oid`, `reference`);

-- DOWN

ALTER TABLE `exf_notification`
DROP INDEX `IDX_NotificationContext`;

ALTER TABLE `exf_notification`
DROP INDEX `IDX_Announcement_search`;

ALTER TABLE `exf_notification`
	DROP COLUMN `hide_from_inbox`,
				`folder`,
				`sent_by`,
				`sent_on`,
				`reference`;
