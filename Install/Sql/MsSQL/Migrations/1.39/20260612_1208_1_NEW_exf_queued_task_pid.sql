/*
 * Add PID column to queued tasks
 *
 * Adds the process ID column `pid` to `exf_queued_task` if it does not
 * exist yet. The DOWN section removes the column only if it exists.
 *
 * @author OpenAI
 */
-- UP
IF COL_LENGTH('exf_queued_task', 'pid') IS NULL
BEGIN
    ALTER TABLE exf_queued_task
        ADD pid INT NULL;
END;

-- DOWN
IF COL_LENGTH('exf_queued_task', 'pid') IS NOT NULL
BEGIN
    ALTER TABLE exf_queued_task
        DROP COLUMN pid;
END;