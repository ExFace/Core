-- UP
IF COL_LENGTH('dbo.exf_pwa_dataset','data_set_uxon') IS NULL
ALTER TABLE [dbo].[exf_pwa_dataset] ADD [data_set_uxon] nvarchar(max) NULL;

-- DOWN
IF COL_LENGTH('dbo.exf_pwa_dataset','incremental_columns') IS NOT NULL
ALTER TABLE [dbo].[exf_pwa_dataset] DROP COLUMN [data_set_uxon];