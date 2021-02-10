-- UP

IF COL_LENGTH('dbo.exf_queued_task','scheduler_oid') IS NULL
ALTER TABLE  dbo.exf_queued_task
	ADD scheduler_oid BINARY(16) NULL;

IF OBJECT_ID('dbo.exf_customizing', 'U') IS NULL 
CREATE TABLE dbo.exf_customizing (
  	oid binary(16) NOT NULL,
    created_on datetime2 NOT NULL,
    modified_on datetime2 NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    table_name nvarchar(50) NOT NULL,
    row_oid binary(16) NOT NULL,
    column_name nvarchar(50) NOT NULL,
    value nvarchar(200) NOT NULL,
	CONSTRAINT PK_exf_customizing_oid PRIMARY KEY (oid),
	CONSTRAINT Ref_table_cell UNIQUE (row_oid, column_name)
);

IF OBJECT_ID('dbo.exf_scheduler', 'U') IS NULL 
CREATE TABLE dbo.exf_scheduler (
    oid binary(16) NOT NULL,
    created_on datetime2 NOT NULL,
    modified_on datetime2 NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    name nvarchar(50) NOT NULL,
    schedule nvarchar(50) NOT NULL,
    description nvarchar(400) DEFAULT NULL,
    action_uxon nvarchar(max),
    task_uxon nvarchar(max),
    app_oid binary(16) DEFAULT NULL,
    queue_topics nvarchar(50) NOT NULL,
    first_run datetime2 NOT NULL,
    last_run datetime2 DEFAULT NULL,
	CONSTRAINT PK_exf_scheduler PRIMARY KEY (oid)
);

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'Find_duplicates') 
CREATE INDEX Find_duplicates ON dbo.exf_queued_task (message_id, producer, queue_oid, status);
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'Scheduler') 
CREATE INDEX Scheduler ON dbo.exf_queued_task (scheduler_oid, created_on);
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'Initial_views') 
CREATE INDEX Initial_views ON dbo.exf_queued_task (created_on, task_assigned_on, owner_oid, queue_oid);
	
-- DOWN

IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'Find_duplicates') 
DROP INDEX Find_duplicates ON dbo.exf_queued_task;
IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'Scheduler') 
DROP INDEX Scheduler ON dbo.exf_queued_task;
IF EXISTS (SELECT * FROM sys.indexes WHERE NAME = N'Initial_views') 
DROP INDEX Initial_views ON dbo.exf_queued_task;

IF COL_LENGTH('dbo.exf_queued_task','scheduler_oid') IS NOT NULL
ALTER TABLE dbo.exf_queued_task
	DROP COLUMN scheduler_oid;

IF OBJECT_ID('dbo.exf_customizing', 'U') IS NOT NULL 	
DROP TABLE IF EXISTS dbo.exf_customizing;

IF OBJECT_ID('dbo.exf_scheduler', 'U') IS NOT NULL 
DROP TABLE IF EXISTS dbo.exf_scheduler;