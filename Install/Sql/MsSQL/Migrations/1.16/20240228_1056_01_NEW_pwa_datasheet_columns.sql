-- UP
IF COL_LENGTH('dbo.exf_pwa_dataset','incremental_columns') IS NULL
    ALTER TABLE [dbo].[exf_pwa_dataset] ADD [incremental_columns] int NULL;

IF COL_LENGTH('dbo.exf_pwa_dataset','columns') IS NULL
    ALTER TABLE [dbo].[exf_pwa_dataset] ADD [columns] int NULL;

-- DOWN
IF COL_LENGTH('dbo.exf_pwa_dataset','incremental_columns') IS NOT NULL
    ALTER TABLE [dbo].[exf_pwa_dataset] DROP COLUMN [incremental_columns];

IF COL_LENGTH('dbo.exf_pwa_dataset','columns') IS NOT NULL
    ALTER TABLE [dbo].[exf_pwa_dataset] DROP COLUMN [columns];
