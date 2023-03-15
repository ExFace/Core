-- UP

IF OBJECT_ID('dbo.exf_pwa_build', 'U') IS NULL 
CREATE TABLE dbo.exf_pwa_build (
  oid binary(16) NOT NULL,
  created_on datetime2 NOT NULL,
  modified_on datetime2 NOT NULL,
  created_by_user_oid binary(16) NULL,
  modified_by_user_oid binary(16) NULL,
  pwa_oid binary(16) NOT NULL,
  filename nvarchar(100) NOT NULL,
  size int NOT NULL,
  content longtext NOT NULL,
  mimetype nvarchar(100) NOT NULL,
  username nvarchar(100) DEFAULT NULL,
  CONSTRAINT [PK_exf_pwa_build] PRIMARY KEY CLUSTERED (oid)
);

ALTER TABLE dbo.exf_pwa
	ADD generated_on datetime NULL,
		regenerate_after datetime NULL;

UPDATE dbo.exf_pwa SET generated_on = modified_on;
	
-- DOWN

ALTER TABLE dbo.exf_pwa
	DROP COLUMN generated_on;
ALTER TABLE dbo.exf_pwa	
	DROP COLUMN regenerate_after;
	
DROP CONSTRAINT [PK_exf_pwa_build];
DROP TABLE exf_pwa_build;