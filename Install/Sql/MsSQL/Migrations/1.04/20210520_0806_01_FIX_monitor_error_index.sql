-- UP

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'exf_idx_monitor_error_log_id')
CREATE INDEX [exf_idx_monitor_error_log_id] ON [dbo].[exf_monitor_error] ([log_id])
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'exf_idx_monitor_error_date_user_status') 
CREATE INDEX [exf_idx_monitor_error_date_user_status] ON [dbo].[exf_monitor_error] ([date, user_oid, status])
GO
	
-- DOWN

IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'exf_idx_monitor_error_log_id') 
DROP INDEX [exf_idx_monitor_error_log_id] ON [dbo].[exf_monitor_error]
GO

IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'exf_idx_monitor_error_date_user_status') 
DROP INDEX [exf_idx_monitor_error_date_user_status] ON [dbo].[exf_monitor_error]
GO