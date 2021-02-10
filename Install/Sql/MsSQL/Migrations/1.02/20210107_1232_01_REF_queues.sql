-- UP

sp_rename 'exf_queued_task.owner', 'owner_oid', 'COLUMN';
sp_rename 'exf_queued_task.queue', 'queue_oid', 'COLUMN';

ALTER TABLE [exf_queued_task]
	ADD [channel] [nvarchar](50) NULL,
		[processed_on] [datetime2] NULL,
		[duration_ms] [decimal](10,2) NULL;
	
UPDATE [exf_queued_task] SET [status] = 30 WHERE [status] = 10;
UPDATE [exf_queued_task] SET [status] = 98 WHERE [status] = 90;

ALTER TABLE [exf_queue]
	ADD [description] [nvarchar](400) NOT NULL DEFAULT '';
	
-- DOWN

sp_rename 'exf_queued_task.owner_oid', 'owner', 'COLUMN';
sp_rename 'exf_queued_task.queue_oid', 'queue', 'COLUMN';

ALTER TABLE [exf_queued_task]
	DROP COLUMN [channel],
		 		[processed_on],
		 		[duration_ms];
	
UPDATE [exf_queued_task] exf_queued_task SET [status] = 10 WHERE [status] = 30;
UPDATE [exf_queued_task] exf_queued_task SET [status] = 90 WHERE [status] = 98;

ALTER TABLE [exf_queue]
	DROP COLUMN [description];
	
ALTER TABLE [exf_queued_task]
	ALTER COLUMN [queue] [binary](16) NOT NULL;