-- UP

ALTER TABLE [dbo].[exf_queued_task] ALTER COLUMN [queue_oid] binary(16) NULL;

-- DOWN
-- Do not make not-null again to avoid errors if null-data already preset. After all, it was an error 
-- from the very beginning