-- UP

CREATE TABLE exf_api (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16) NOT NULL,
  modified_by_user_oid binary(16) NOT NULL,
  url nvarchar(256) NULL,
  name nvarchar(128) NOT NULL,
  type nvarchar(2) NOT NULL,
  facade nvarchar(128) NULL,
  app_oid binary(16) NULL,
  uxon text NULL,
  last_call_on datetime NULL,
  last_call_code smallint NULL,
  health_url nvarchar(256) NULL,
  metadata_url nvarchar(256) NULL,
  explorer_url nvarchar(256) NULL,
  description text NULL,
  CONSTRAINT PK_exf_api PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT FK_api_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid)
);

CREATE TABLE exf_external_system (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16) NOT NULL,
  modified_by_user_oid binary(16) NOT NULL,
  name nvarchar(128) NOT NULL,
  app_oid binary(16) NULL,
  ip_mask nvarchar(40) NULL,
  CONSTRAINT PK_exf_external_system PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT FK_external_system_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid)
);

CREATE TABLE exf_api_system (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16) NOT NULL,
  modified_by_user_oid binary(16) NOT NULL,
  api_oid binary(16) NOT NULL,
  external_system_oid binary(16) NOT NULL,
  triggered_by nvarchar(2) NOT NULL,
  data_flow_direction nvarchar(2) NOT NULL,
  authentication nvarchar(250) NULL,
  interval nvarchar(20) NULL,
  info nvarchar(500) NULL,
  CONSTRAINT PK_exf_api_system PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT FK_api_system_api FOREIGN KEY (api_oid) REFERENCES exf_api (oid),
  CONSTRAINT FK_api_system_external_system FOREIGN KEY (external_system_oid) REFERENCES exf_external_system (oid)
); 

-- DOWN

ALTER TABLE exf_api DROP CONSTRAINT FK_api_app;

ALTER TABLE exf_external_system DROP CONSTRAINT FK_external_system_app;

ALTER TABLE exf_api_system DROP CONSTRAINT FK_api_system_api;

ALTER TABLE exf_api_system DROP CONSTRAINT FK_api_system_external_system;

DROP TABLE exf_api_system;

DROP TABLE exf_api;

DROP TABLE exf_external_system;