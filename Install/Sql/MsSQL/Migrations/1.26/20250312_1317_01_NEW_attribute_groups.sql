-- UP

CREATE TABLE dbo.exf_attribute_group (
  oid BINARY(16) NOT NULL,
  created_on DATETIME NOT NULL,
  modified_on DATETIME NOT NULL,
  created_by_user_oid BINARY(16) NULL,
  modified_by_user_oid BINARY(16) NULL,
  object_oid BINARY(16) NOT NULL,
  name NVARCHAR(50) COLLATE Latin1_General_CI_AI NOT NULL,
  alias NVARCHAR(50) COLLATE Latin1_General_CI_AI NOT NULL,
  app_oid BINARY(16) NOT NULL,
  description NVARCHAR(200) COLLATE Latin1_General_CI_AI NULL,
  CONSTRAINT PK_exf_attribute_group PRIMARY KEY (oid),
  CONSTRAINT UQ_Name_Per_App UNIQUE (name, app_oid),
  CONSTRAINT FK_attribute_group_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid)
);

CREATE INDEX IX_exf_attribute_group_object_oid ON exf_attribute_group (object_oid);
CREATE INDEX IX_exf_attribute_group_app_oid ON exf_attribute_group (app_oid);

CREATE TABLE dbo.exf_attribute_group_attributes (
  oid BINARY(16) NOT NULL,
  created_on DATETIME NOT NULL,
  modified_on DATETIME NOT NULL,
  created_by_user_oid BINARY(16) NULL,
  modified_by_user_oid BINARY(16) NULL,
  attribute_oid BINARY(16) NOT NULL,
  attribute_group_oid BINARY(16) NOT NULL,
  pos TINYINT NOT NULL,
  CONSTRAINT PK_exf_attribute_group_attributes PRIMARY KEY (oid),
  CONSTRAINT UQ_Attribute_Per_Group UNIQUE (attribute_oid, attribute_group_oid)
);

CREATE INDEX IX_exf_attribute_group_attributes_group_pos ON exf_attribute_group_attributes (attribute_group_oid, pos);

-- DOWN

-- DO NOT drop tables with potential content!