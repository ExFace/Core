-- UP
IF COL_LENGTH('dbo.exf_user_role_users','authenticator_id') IS NULL
ALTER TABLE [dbo].[exf_user_role_users] ADD [authenticator_id] nvarchar(100) NULL;

-- DOWN
IF COL_LENGTH('dbo.exf_user_role_users','authenticator_id') IS NOT NULL
ALTER TABLE [dbo].[exf_user_role_users] DROP COLUMN [authenticator_id];