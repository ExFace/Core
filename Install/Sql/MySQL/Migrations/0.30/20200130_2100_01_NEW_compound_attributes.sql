-- UP

CREATE TABLE IF NOT EXISTS `exf_attribute_compound` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `attribute_oid` binary(16) NOT NULL,
  `compound_attribute_oid` binary(16) NOT NULL,
  `sequence_index` int(11) NOT NULL,
  `delimiter` varchar(3) DEFAULT NULL,
  PRIMARY KEY (`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

ALTER TABLE `exf_attribute`
	ADD COLUMN `attribute_type` VARCHAR(1) NOT NULL DEFAULT 'D' AFTER `value_list_delimiter`;

-- DOWN

DROP TABLE IF EXISTS `exf_attribute_compound`;
