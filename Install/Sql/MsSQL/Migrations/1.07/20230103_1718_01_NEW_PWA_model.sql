-- UP
IF OBJECT_ID('dbo.exf_pwa', 'U') IS NULL 
CREATE TABLE dbo.exf_pwa (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16),
  modified_by_user_oid binary(16),
  name nvarchar(100) NOT NULL,
  description nvarchar(400),
  icon_uri nvarchar(100),
  start_page_oid binary(16) NOT NULL,
  page_template_oid binary(16) NOT NULL,
  alias nvarchar(100) NOT NULL,
  app_oid binary(16),
  url nvarchar(100) NOT NULL,
  active_flag tinyint NOT NULL DEFAULT '1',
  installable_flag tinyint NOT NULL DEFAULT '1',
  available_offline_flag tinyint NOT NULL DEFAULT '1',
  available_offline_help_flag tinyint NOT NULL DEFAULT '0',
  available_offline_unpublished_flag tinyint NOT NULL DEFAULT '0',
  CONSTRAINT [PK_exf_pwa] PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT [U_exf_pwa_app_alias] UNIQUE (alias,app_oid)
);

IF OBJECT_ID('dbo.exf_pwa_action', 'U') IS NULL 
CREATE TABLE dbo.exf_pwa_action (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16),
  modified_by_user_oid binary(16),
  pwa_oid binary(16) NOT NULL,
  description nvarchar(400) NOT NULL,
  action_alias nvarchar(100) NOT NULL,
  object_action_oid binary(16),
  offline_strategy nvarchar(20) NOT NULL,
  page_oid binary(16) NOT NULL,
  trigger_widget_id nvarchar(400) NOT NULL,
  trigger_widget_type nvarchar(100) NOT NULL,
  pwa_dataset_oid binary(16),
  CONSTRAINT [PK_exf_pwa_action] PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT [U_exf_pwa_action_page_trigger_pwa] UNIQUE (page_oid,trigger_widget_id,pwa_oid)
);

IF OBJECT_ID('dbo.exf_pwa_dataset', 'U') IS NULL 
CREATE TABLE dbo.exf_pwa_dataset (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16),
  modified_by_user_oid binary(16),
  pwa_oid binary(16) NOT NULL,
  object_oid binary(16) NOT NULL,
  description nvarchar(400) NOT NULL,
  data_sheet_uxon text NOT NULL,
  user_defined_flag tinyint NOT NULL DEFAULT '1',
  CONSTRAINT [PK_exf_pwa_dataset] PRIMARY KEY CLUSTERED (oid)
);

IF OBJECT_ID('dbo.exf_pwa_route', 'U') IS NULL 
CREATE TABLE dbo.exf_pwa_route (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16),
  modified_by_user_oid binary(16),
  pwa_oid binary(16) NOT NULL,
  pwa_action_oid binary(16) NOT NULL,
  url nvarchar(1024) NOT NULL,
  description nvarchar(400) NOT NULL,
  user_defined_flag tinyint NOT NULL DEFAULT '1',
  CONSTRAINT [PK_exf_pwa_route] PRIMARY KEY CLUSTERED (oid),
  CONSTRAINT [U_pwa_route_one_per_action] UNIQUE (pwa_action_oid)
);
	
-- DOWN

IF OBJECT_ID('dbo.exf_pwa', 'U') IS NULL 
DROP TABLE dbo.exf_pwa;
IF OBJECT_ID('dbo.exf_pwa_action', 'U') IS NULL 
DROP TABLE IF EXISTS exf_pwa_action;
IF OBJECT_ID('dbo.exf_pwa_route', 'U') IS NULL 
DROP TABLE IF EXISTS exf_pwa_route;
IF OBJECT_ID('dbo.exf_pwa_dataset', 'U') IS NULL 
DROP TABLE IF EXISTS exf_pwa_dataset;