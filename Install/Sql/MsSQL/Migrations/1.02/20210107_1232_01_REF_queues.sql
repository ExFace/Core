-- UP

ALTER TABLE [exf_queued_task]
	ALTER COLUMN [owner] [owner_oid] [binary](16) NOT NULL,
	ADD COLUMN [channel] [nvarchar](50) NULL,
	ADD COLUMN [processed_on] [datetime2] NULL,
	ADD COLUMN [duration_ms] [decimal](10,2) NULL;
	
ALTER TABLE [exf_queued_task]
    ALTER COLUMN [queue] [queue_oid] [binary](16) NULL;
	
UPDATE [exf_queued_task] SET [status] = 30 WHERE [status] = 10;
UPDATE [exf_queued_task] SET [status] = 98 WHERE [status] = 90;

ALTER TABLE [exf_queue]
	ADD COLUMN [description] [nvarchar](400) NOT NULL DEFAULT '';
	
-- DOWN

ALTER TABLE [exf_queued_task]
	ALTER COLUMN [owner_oid] [owner] [binary](16) NOT NULL,
	ALTER COLUMN [queue_oid] [queue] [binary](16) NOT NULL,
	DROP COLUMN [channel],
	DROP COLUMN [processed_on],
	DROP COLUMN [duration_ms];
	
UPDATE [exf_queued_task] exf_queued_task SET [status] = 10 WHERE [status] = 30;
UPDATE [exf_queued_task] exf_queued_task SET [status] = 90 WHERE [status] = 98;

ALTER TABLE [exf_queue]
	DROP COLUMN [description];
	
ALTER TABLE [exf_queued_task]
	ALTER COLUMN [queue] [queue] [binary](16) NOT NULL;