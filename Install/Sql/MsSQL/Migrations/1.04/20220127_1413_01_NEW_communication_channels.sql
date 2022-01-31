-- UP
IF OBJECT_ID('dbo.exf_communication_channel', 'U') IS NULL 
CREATE TABLE dbo.exf_communication_channel (
  oid binary(16) NOT NULL,
  created_on datetime2 NOT NULL,
  modified_on datetime2 NOT NULL,
  created_by_user_oid binary(16) DEFAULT NULL,
  modified_by_user_oid binary(16) DEFAULT NULL,
  name nvarchar(50) NOT NULL,
  alias nvarchar(100) NOT NULL,
  descr nvarchar(200) DEFAULT NULL,
  app_oid binary(16) DEFAULT NULL,
  data_connection_oid binary(16) DEFAULT NULL,
  message_prototype nvarchar(200) NOT NULL,
  message_default_uxon nvarchar(max),
  mute_flag tinyint NOT NULL DEFAULT '0',
  CONSTRAINT [PK_exf_communication_channel] PRIMARY KEY CLUSTERED (oid)
);

	
-- DOWN

IF OBJECT_ID('dbo.exf_communication_channel', 'U') IS NOT NULL 

DECLARE @sql NVARCHAR(MAX)
WHILE 1=1
BEGIN
    SELECT TOP 1 @sql = N'ALTER TABLE exf_communication_channel DROP CONSTRAINT ['+dc.NAME+N']'
    from sys.default_constraints dc
    JOIN sys.columns c
        ON c.default_object_id = dc.object_id
    WHERE 
        dc.parent_object_id = OBJECT_ID('exf_communication_channel')
    IF @@ROWCOUNT = 0 BREAK
    EXEC (@sql)
END

DROP TABLE dbo.exf_communication_channel;