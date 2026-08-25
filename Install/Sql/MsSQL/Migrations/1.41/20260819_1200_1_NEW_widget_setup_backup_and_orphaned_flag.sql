/*
 * Prepares exf_widget_setup for the widget setup cleanup.
 *
 * 1. Creates a full backup copy of the table as exf_widget_setup_backup
 *    (structure + data) before the cleanup converts old setups.
 * 2. Adds an orphaned_flag column used to mark setups whose widget could no
 *    longer be found during the cleanup.
 *
 * @author Saskia Hustinx
 */
-- UP
-- Create a full backup copy of the table (structure + data) only if missing
IF OBJECT_ID('dbo.exf_widget_setup_backup', 'U') IS NULL
    SELECT * INTO dbo.exf_widget_setup_backup FROM dbo.exf_widget_setup;
GO

-- Add the orphaned_flag column only if it does not exist yet
IF COL_LENGTH('dbo.exf_widget_setup', 'orphaned_flag') IS NULL
    ALTER TABLE exf_widget_setup
        ADD orphaned_flag tinyint NOT NULL
            CONSTRAINT DF_exf_widget_setup_orphaned_flag DEFAULT '0';
GO

-- DOWN
-- Keep the orphaned_flag column and exf_widget_setup_backup on purpose to avoid
-- losing the cleanup results and the safety backup.
