-- UP

CREATE TABLE `exf_api` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `url` varchar(256) NULL,
  `name` varchar(128) NOT NULL,
  `type` varchar(2) NOT NULL,
  `facade` varchar(128) NULL,
  `app_oid` binary(16) NULL,
  `uxon` longtext NULL,
  `last_call_on` datetime NULL,
  `last_call_code` smallint NULL,
  `health_url` varchar(256) NULL,
  `metadata_url` varchar(256) NULL,
  `explorer_url` varchar(256) NULL,
  `description` longtext NULL,
  CONSTRAINT PK_exf_api PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT FK_api_app FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`)
);

CREATE TABLE `exf_external_system` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `name` varchar(128) NOT NULL,
  `app_oid` binary(16) NULL,
  `ip_mask` varchar(40) NULL,
  CONSTRAINT PK_exf_external_system PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT FK_external_system_app FOREIGN KEY (`app_oid`) REFERENCES `exf_app` (`oid`)
);

CREATE TABLE `exf_api_system` (
  `oid` binary(16) NOT NULL,
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `created_by_user_oid` binary(16) NOT NULL,
  `modified_by_user_oid` binary(16) NOT NULL,
  `api_oid` binary(16) NOT NULL,
  `external_system_oid` binary(16) NOT NULL,
  `triggered_by` varchar(2) NOT NULL,
  `data_flow_direction` varchar(2) NOT NULL,
`authentication` varchar(250) NULL,
`interval` varchar(20) NULL,
`info` VARCHAR(500) NULL,
 CONSTRAINT PK_exf_api_system PRIMARY KEY CLUSTERED (oid),
 CONSTRAINT FK_api_system_api FOREIGN KEY (`api_oid`) REFERENCES `exf_api` (`oid`),
 CONSTRAINT FK_api_system_external_system FOREIGN KEY (`external_system_oid`) REFERENCES `exf_external_system` (`oid`)
); 

-- DOWN

DROP TABLE `exf_api_system`;

DROP TABLE `exf_api`;

DROP TABLE `exf_external_system`;