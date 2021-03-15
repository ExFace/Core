-- UP

IF OBJECT_ID('dbo.exf_notification', 'U') IS NULL 
CREATE TABLE dbo.exf_notification (
  	oid binary(16) NOT NULL,
    created_on datetime2 NOT NULL,
    modified_on datetime2 NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    user_oid binary(16) NOT NULL,
  	title nvarchar(200) NOT NULL,
  	icon nvarchar(50) DEFAULT NULL,
  	widget_uxon nvarchar(max) NOT NULL,
  	CONSTRAINT PK_exf_customizing_oid PRIMARY KEY (oid)
);

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'user_notifications') 
CREATE INDEX user_notifications ON dbo.exf_notification (user_oid, created_on);
	
-- DOWN

IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'user_notifications') 
DROP INDEX user_notifications ON dbo.exf_notification;

DROP TABLE IF NOT EXISTS exf_notification;