-- UP
IF COL_LENGTH('dbo.exf_user','employee_id') IS NULL
ALTER TABLE [dbo].[exf_user] ADD [employee_id] nvarchar(50) NULL;

-- DOWN
IF COL_LENGTH('dbo.exf_user','employee_id') IS NOT NULL
ALTER TABLE [dbo].[exf_user] DROP COLUMN [employee_id];