-- UP

CREATE TABLE IF NOT EXISTS `exf_widget_setup` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `name` varchar(100) NOT NULL,
    `description` varchar(200) DEFAULT NULL,
    `app_oid` binary(16) DEFAULT NULL,
    `page_oid` binary(16) NOT NULL,
    `object_oid` binary(16) DEFAULT NULL,
    `widget_id` varchar(2000) NOT NULL,
    `prototype_file` varchar(200) NOT NULL,
    `setup_uxon` longtext NOT NULL,
    `private_for_user_oid` binary(16) DEFAULT NULL,
    PRIMARY KEY (`oid`) USING BTREE,
    KEY `FK_widget_setup_app` (`app_oid`),
    CONSTRAINT `FK_widget_setup_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY `FK_widget_setup_page` (`page_oid`),
    CONSTRAINT `FK_widget_setup_page` FOREIGN KEY (`page_oid`) REFERENCES `exf_page` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY `FK_widget_setup_user` (`private_for_user_oid`),
    CONSTRAINT `FK_widget_setup_user` FOREIGN KEY (`private_for_user_oid`) REFERENCES `exf_user` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY `IX_page_user` (`page_oid`, `private_for_user_oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_widget_setup_user` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `user_oid` binary(16) NOT NULL,
    `widget_setup_oid` binary(16) NOT NULL,
    `favorite_flag` tinyint NOT NULL DEFAULT '0',
    `default_setup_flag` tinyint NOT NULL DEFAULT '0',
    PRIMARY KEY (`oid`) USING BTREE,
    UNIQUE KEY `Unique per setup and user` (`user_oid`,`widget_setup_oid`) USING BTREE,
    KEY `FK_widget_setup_user_setup` (`widget_setup_oid`),
    CONSTRAINT `FK_widget_setup_user_setup` FOREIGN KEY (`widget_setup_oid`) REFERENCES `exf_widget_setup` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;

-- DOWN
-- Do not automatically drop tables to avoid data loss!