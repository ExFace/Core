/*
 * Add logbook column to queued tasks
 *
 * Stores task logbook details directly on queued task records.
 *
 * @author Sergej Riel
 */
-- UP

ALTER TABLE dbo.exf_queued_task
    ADD logbook NVARCHAR(MAX) NULL;

-- DOWN
-- Do not delete columns to avoid losing data!