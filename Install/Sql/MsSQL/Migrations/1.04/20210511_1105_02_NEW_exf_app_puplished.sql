-- UP

ALTER TABLE [dbo].[exf_app]
	ADD [puplished] INT NOT NULL DEFAULT '0';
	
-- DOWN

DECLARE @sql NVARCHAR(MAX)
WHILE 1=1
BEGIN
    SELECT TOP 1 @sql = N'ALTER TABLE exf_app DROP CONSTRAINT ['+dc.NAME+N']'
    from sys.default_constraints dc
    JOIN sys.columns c
        ON c.default_object_id = dc.object_id
    WHERE 
        dc.parent_object_id = OBJECT_ID('exf_app')
    AND c.name IN (N'puplisheds')
    IF @@ROWCOUNT = 0 BREAK
    EXEC (@sql)
END
	
ALTER TABLE [dbo].[exf_app]
	DROP COLUMN [puplished];