-- UP

IF OBJECT_ID('dbo.exf_monitor_error', 'U') IS NULL 
CREATE TABLE [dbo].[exf_monitor_error] (
  	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2] NOT NULL,
	[modified_on] [datetime2] NOT NULL,
	[created_by_user_oid] [binary](16) DEFAULT NULL,
	[modified_by_user_oid] [binary](16) DEFAULT NULL,
    [log_id] nvarchar(10) NOT NULL,
    [error_level] nvarchar(20) NOT NULL,
    [error_widget] nvarchar(max) NOT NULL,
    [message] nvarchar(max) NOT NULL,
    [date] date NOT NULL,
    [status] smallint NOT NULL,
    [user_oid] binary(16) DEFAULT NULL,
    [action_oid] binary(16) DEFAULT NULL,
  CONSTRAINT [PK_exf_monitor_action_oid] PRIMARY KEY CLUSTERED (oid)
)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'IDX_exf_monitor_error_date_user_status') 
CREATE INDEX [exf_idx_monitor_error_date_user_status] ON [dbo].[exf_monitor_error] ([date, user_oid, status])
GO

-- DOWN

IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'IDX_exf_monitor_error_date_user_status') 
DROP INDEX [exf_idx_monitor_error_date_user_status] ON [dbo].[exf_monitor_error]
GO

DROP TABLE IF EXISTS [dbo].[exf_monitor_error]
GO