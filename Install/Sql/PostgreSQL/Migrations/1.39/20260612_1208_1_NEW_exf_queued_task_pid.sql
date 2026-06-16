/*
 * Add PID column to queued tasks
 *
 * Adds the process ID column `pid` to `exf_queued_task` if it does not
 * exist yet. The DOWN section removes the column only if it exists.
 *
 * @author OpenAI
 */
-- UP
ALTER TABLE exf_queued_task
    ADD COLUMN IF NOT EXISTS pid INTEGER NULL;

-- DOWN
ALTER TABLE exf_queued_task
    DROP COLUMN IF EXISTS pid;