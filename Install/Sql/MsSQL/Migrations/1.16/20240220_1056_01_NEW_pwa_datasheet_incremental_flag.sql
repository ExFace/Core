-- UP
IF COL_LENGTH('dbo.exf_pwa_dataset','incremental_flag') IS NULL
    ALTER TABLE [dbo].[exf_pwa_dataset] ADD [incremental_flag] TINYINT NOT NULL DEFAULT 0;

-- DOWN
IF COL_LENGTH('dbo.exf_pwa_dataset','incremental_flag') IS NOT NULL
    ALTER TABLE [dbo].[exf_pwa_dataset] DROP COLUMN [incremental_flag];
