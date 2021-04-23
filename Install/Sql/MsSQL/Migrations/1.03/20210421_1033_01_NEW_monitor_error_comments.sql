-- UP

ALTER TABLE dbo.exf_monitor_error
	ADD [request_id] nvarchar(50) NOT NULL DEFAULT '',
	ADD [comment] nvarchar(max) DEFAULT NULL,
	ADD [ticket_no] nvarchar(20) DEFAULT NULL;
	
-- DOWN

ALTER TABLE [dbo].[exf_monitor_error]
	DROP COLUMN [request_id],
	DROP COLUMN [comment],
	DROP COLUMN [ticket_no];