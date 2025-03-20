-- UP

CREATE TABLE dbo.rp_role_attribute (
  oid BINARY(16) NOT NULL,
  created_on DATETIME NOT NULL,
  modified_on DATETIME NOT NULL,
  created_by_user_oid BINARY(16) NOT NULL,
  modified_by_user_oid BINARY(16) NOT NULL,
  name NVARCHAR(50) COLLATE Latin1_General_CI_AI NOT NULL,
  hint NVARCHAR(200) COLLATE Latin1_General_CI_AI NULL,
  type NVARCHAR(50) COLLATE Latin1_General_CI_AI NOT NULL,
  json_property NVARCHAR(50) COLLATE Latin1_General_CI_AI NOT NULL,
  app_oid BINARY(16) NULL,
  required tinyint NOT NULL,
  display_order tinyint NOT NULL,
  CONSTRAINT PK_rp_role_attribute PRIMARY KEY (oid)
);

-- DOWN

-- DO NOT drop tables with potential content!