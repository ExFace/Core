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
CREATE TABLE IF NOT EXISTS exf_widget_setup_backup AS TABLE exf_widget_setup;

-- Add the orphaned_flag column only if it does not exist yet
ALTER TABLE exf_widget_setup
    ADD COLUMN IF NOT EXISTS orphaned_flag smallint NOT NULL DEFAULT 0;

-- DOWN
-- Keep the orphaned_flag column and exf_widget_setup_backup on purpose to avoid
-- losing the cleanup results and the safety backup.
