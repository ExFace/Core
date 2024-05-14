-- UP
IF COL_LENGTH('dbo.exf_user_role_external','keep_manual_assignments_flag') IS NULL
ALTER TABLE [dbo].[exf_user_role_external] ADD [keep_manual_assignments_flag] tinyint NULL;

-- DOWN
IF COL_LENGTH('dbo.exf_user_role_external','keep_manual_assignments_flag') IS NOT NULL
ALTER TABLE [dbo].[exf_user_role_external] DROP COLUMN [keep_manual_assignments_flag];