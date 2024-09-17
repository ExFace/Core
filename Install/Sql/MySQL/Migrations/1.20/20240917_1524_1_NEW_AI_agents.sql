-- UP

CREATE TABLE `exf_ai_agent` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `app_oid` binary(16) DEFAULT NULL,
  `alias` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `prototype_class` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `config_uxon` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `data_connection_default_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE,
  UNIQUE KEY `Key unique` (`alias`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;


CREATE TABLE `exf_ai_conversation` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `ai_agent_oid` binary(16) NOT NULL,
  `user_oid` binary(16) DEFAULT NULL,
  `title` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `meta_object_oid` binary(16) DEFAULT NULL,
  `context_data_uxon` text,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;


CREATE TABLE `exf_ai_message` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) DEFAULT NULL,
  `modified_by_user_oid` binary(16) DEFAULT NULL,
  `ai_conversation_oid` binary(16) NOT NULL,
  `role` varchar(30) NOT NULL,
  `message` text NOT NULL,
  `data` text,
  `tokens_prompt` int DEFAULT NULL,
  `tokens_completion` int DEFAULT NULL,
  PRIMARY KEY (`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC;

-- DOWN