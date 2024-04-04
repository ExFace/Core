-- UP

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'IDX_exf_monitor_error_log_id')
CREATE INDEX [exf_idx_monitor_error_log_id] ON [dbo].[exf_monitor_error] ([log_id]);
	
-- DOWN

IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'IDX_exf_monitor_error_log_id') 
DROP INDEX [exf_idx_monitor_error_log_id] ON [dbo].[exf_monitor_error];