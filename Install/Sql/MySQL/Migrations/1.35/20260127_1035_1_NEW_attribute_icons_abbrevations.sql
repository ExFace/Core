-- UP

ALTER TABLE `exf_attribute`
ADD `abbreviation` varchar(10) NULL,
ADD `icon` text NULL AFTER `abbreviation`,
ADD `icon_set` varchar(100) NULL AFTER `icon`;

-- DOWN
-- Do not delete columns to avoid losing data!