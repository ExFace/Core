-- UP

CREATE TABLE IF NOT EXISTS `exf_announcement` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `communication_template_oid` binary(16) NOT NULL,
  `title` varchar(100) NOT NULL,
  `enabled_flag` tinyint DEFAULT '1',
  `show_from` datetime NOT NULL,
  `show_to` datetime DEFAULT NULL,
  `message_uxon` text,
  `message_type` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  KEY `IDX_communication_template_oid` (`communication_template_oid`),
  CONSTRAINT `FK_announcement_communication_template` FOREIGN KEY (`communication_template_oid`) REFERENCES `exf_communication_template` (`oid`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;

ALTER TABLE `exf_notification`
	ADD `hide_from_inbox` tinyint NOT NULL DEFAULT '0',
	ADD `folder` varchar(100) NULL,
	ADD `sent_by` varchar(100) NULL,
	ADD `sent_on` datetime NULL,
	ADD `reference` varchar(200) NULL;

UPDATE exf_notification SET sent_by = (SELECT COALESCE(u.username, '') FROM exf_user u WHERE u.oid = exf_notification.created_by_user_oid) WHERE sent_by = NULL;
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

DROP TABLE `exf_announcement`;
