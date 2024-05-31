-- UP
IF OBJECT_ID('dbo.[exf_user_role_external$Alias unique per authenticator]') IS NOT NULL 
ALTER TABLE [dbo].[exf_user_role_external] DROP CONSTRAINT [exf_user_role_external$Alias unique per authenticator];

-- DOWN
-- not needed as we can't revert that change as data might exist that does not fit that constraint