-- UP

IF OBJECT_ID('dbo.exf_communication_template', 'U') IS NULL 
CREATE TABLE dbo.exf_communication_template (
	oid binary(16) NOT NULL,
	created_on datetime2 NOT NULL,
	modified_on datetime2 NOT NULL,
	created_by_user_oid binary(16),
	modified_by_user_oid binary(16),
	name nvarchar(100) NOT NULL,
	alias nvarchar(100) NOT NULL,
	app_oid binary(16),
	communication_channel_oid binary(16) NOT NULL,
	message_uxon nvarchar(max) NOT NULL,
	object_oid binary(16),
	CONSTRAINT [PK_exf_communication_template] PRIMARY KEY CLUSTERED (oid),
	CONSTRAINT [UC_exf_communication_template_alias] UNIQUE (alias, app_oid)
)
	
-- DOWN

IF OBJECT_ID('dbo.exf_communication_template', 'U') IS NOT NULL 
BEGIN
	ALTER TABLE dbo.exf_communication_template DROP CONSTRAINT [UC_exf_communication_template_alias]
	DROP TABLE dbo.exf_communication_template
END