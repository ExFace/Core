ALTER TABLE `exf_user` ADD `locale` VARCHAR(20) NOT NULL AFTER `username`;
ALTER TABLE `exf_user` ADD `email` VARCHAR(100) NOT NULL AFTER `locale`;
