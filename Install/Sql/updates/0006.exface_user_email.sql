ALTER TABLE `exf_user` ADD `locale` VARCHAR(20) NOT NULL AFTER `username`;
ALTER TABLE `exf_user` ADD `email` VARCHAR(100) NOT NULL AFTER `locale`;

UPDATE modx_manager_users mu LEFT JOIN exf_user eu ON mu.username = eu.username LEFT JOIN modx_user_attributes ua ON mu.id = ua.internalKey SET eu.email = ua.email;
UPDATE modx_web_users wu LEFT JOIN exf_user eu ON wu.username = eu.username LEFT JOIN modx_web_user_attributes wua ON wu.id = wua.internalKey SET eu.email = wua.email;
