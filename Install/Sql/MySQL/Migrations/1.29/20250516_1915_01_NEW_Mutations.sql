-- UP

CREATE TABLE IF NOT EXISTS `exf_mutation_set` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `app_oid` binary(16) NOT NULL,
    `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    `enabled_flag` tinyint NOT NULL DEFAULT '1',
    PRIMARY KEY (`oid`),
    UNIQUE KEY `Name unique per app` (`name`,`app_oid`) USING BTREE,
    KEY `FK_mutation_set_app` (`app_oid`),
    CONSTRAINT `FK_mutation_set_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_mutation_target` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `app_oid` binary(16) NOT NULL,
    `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    `object_oid` binary(16) NOT NULL,
    PRIMARY KEY (`oid`) USING BTREE,
    UNIQUE KEY `Name unique per app` (`name`,`app_oid`) USING BTREE,
    KEY `FK_mutation_target_app` (`app_oid`),
    CONSTRAINT `FK_mutation_target_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_mutation_type` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `app_oid` binary(16) NOT NULL,
    `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    `mutation_point_file` varchar(200) NOT NULL,
    `mutation_prototype_file` varchar(200) NOT NULL,
    `mutation_target_oid` binary(16) NOT NULL,
    PRIMARY KEY (`oid`),
    UNIQUE KEY `Name unique per app` (`name`,`app_oid`) USING BTREE,
    KEY `FK_mutation_type_app` (`app_oid`),
    CONSTRAINT `FK_mutation_type_app` FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY `FK_mutation_target_app` (`mutation_target_oid`),
    CONSTRAINT `FK_mutation_type_target` FOREIGN KEY (`mutation_target_oid`) REFERENCES `exf_mutation_target` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `exf_mutation` (
    `oid` binary(16) NOT NULL,
    `created_on` datetime NOT NULL,
    `modified_on` datetime NOT NULL,
    `created_by_user_oid` binary(16) NOT NULL,
    `modified_by_user_oid` binary(16) NOT NULL,
    `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    `enabled_flag` tinyint NOT NULL DEFAULT '1',
    `mutation_set_oid` binary(16) NOT NULL,
    `mutation_type_oid` binary(16) NOT NULL,
    `config_base_object_oid` binary(16) NULL,
    `config_uxon` longtext,
    `targets_json` text,
    PRIMARY KEY (`oid`),
    UNIQUE KEY `Name unique per set` (`name`,`mutation_set_oid`) USING BTREE,
    KEY `FK_mutation_mutation_set` (`mutation_set_oid`),
    CONSTRAINT `FK_mutation_mutation_set` FOREIGN KEY (`mutation_set_oid`) REFERENCES `exf_mutation_set` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY `FK_mutation_mutation_type` (`mutation_type_oid`),
    CONSTRAINT `FK_mutation_mutation_type` FOREIGN KEY (`mutation_type_oid`) REFERENCES `exf_mutation_type` (`oid`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY `IX_mutation_config_base_object` (`mutation_type_oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

-- DOWN

/*
-- Do not DROP tables by default

DROP TABLE IF EXISTS exf_mutation;
DROP TABLE IF EXISTS exf_mutation_type;
DROP TABLE IF EXISTS exf_mutation_set;
DROP TABLE IF EXISTS exf_mutation_target;
*/

