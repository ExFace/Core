-- UP

ALTER TABLE [dbo].[exf_user_role]
	ADD [start_page_oid] binary(16) DEFAULT NULL;
	
-- DOWN

DECLARE @sql NVARCHAR(MAX)
WHILE 1=1
BEGIN
    SELECT TOP 1 @sql = N'ALTER TABLE exf_user_role DROP CONSTRAINT ['+dc.NAME+N']'
    from sys.default_constraints dc
    JOIN sys.columns c
        ON c.default_object_id = dc.object_id
    WHERE 
        dc.parent_object_id = OBJECT_ID('exf_user_role')
    AND c.name IN (N'start_page_oid')
    IF @@ROWCOUNT = 0 BREAK
    EXEC (@sql)
END

ALTER TABLE [dbo].[exf_user_role]
	DROP COLUMN [start_page_oid];