-- UP

ALTER TABLE dbo.exf_monitor_error
	ADD [request_id] nvarchar(50) NOT NULL DEFAULT '',
		[comment] nvarchar(max) DEFAULT NULL,
		[ticket_no] nvarchar(20) DEFAULT NULL;
	
-- DOWN

DECLARE @sql NVARCHAR(MAX)
WHILE 1=1
BEGIN
    SELECT TOP 1 @sql = N'ALTER TABLE exf_monitor_error DROP CONSTRAINT ['+dc.NAME+N']'
    from sys.default_constraints dc
    JOIN sys.columns c
        ON c.default_object_id = dc.object_id
    WHERE 
        dc.parent_object_id = OBJECT_ID('exf_monitor_error')
    AND c.name IN (N'request_id', N'comment', N'ticket_no')
    IF @@ROWCOUNT = 0 BREAK
    EXEC (@sql)
END

ALTER TABLE [dbo].[exf_monitor_error]
	DROP COLUMN [request_id],
				[comment],
				[ticket_no];