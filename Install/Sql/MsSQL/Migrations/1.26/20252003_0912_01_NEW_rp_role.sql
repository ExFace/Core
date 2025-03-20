-- UP

CREATE TABLE dbo.rp_role (
  oid BINARY(16) NOT NULL,
  created_on DATETIME NOT NULL,
  modified_on DATETIME NOT NULL,
  created_by_user_oid BINARY(16) NOT NULL,
  modified_by_user_oid BINARY(16) NOT NULL,
  user_role_oid BINARY(16) NULL,
  name NVARCHAR(50) COLLATE Latin1_General_CI_AI NOT NULL,
  description NVARCHAR(400) COLLATE Latin1_General_CI_AI NULL,
  external_role_alias NVARCHAR(50) COLLATE Latin1_General_CI_AI NULL,
  external_role_name NVARCHAR(50) COLLATE Latin1_General_CI_AI NULL,
  permissions_read NVARCHAR(max) COLLATE Latin1_General_CI_AI NULL,
  permissions_write NVARCHAR(max) COLLATE Latin1_General_CI_AI NULL,
  notifications NVARCHAR(max) COLLATE Latin1_General_CI_AI NULL,
  comments NVARCHAR(max) COLLATE Latin1_General_CI_AI NULL,
  status tinyint NOT NULL,
  custom_attributes NVARCHAR(max) COLLATE Latin1_General_CI_AI NULL,
  external_role_mapped_flag bit NULL,
  external_role_sync_flag bit NULL,
  app_oid BINARY(16) NULL,
  CONSTRAINT PK_rp_role PRIMARY KEY (oid)
);

-- DOWN

-- DO NOT drop tables with potential content!