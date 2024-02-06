-- UP

CREATE TABLE dbo.exf_user_api_key (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16) NOT NULL,
  modified_by_user_oid binary(16) NOT NULL,
  user_oid binary(16) NOT NULL,
  key_hash nvarchar(300) NOT NULL,
  name nvarchar(100) NOT NULL,
  expires datetime NULL,
  CONSTRAINT PK_user_api_key PRIMARY KEY (oid),
  CONSTRAINT U_user_api_key_hash UNIQUE (key_hash)
);

-- DOWN

DROP TABLE exf_user_api_key;