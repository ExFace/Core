-- UP

ALTER TABLE  exf_queued_task
	ADD scheduler_oid BINARY(16) NULL;
	
CREATE TABLE IF NOT EXISTS  exf_customizing (
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

CREATE TABLE IF NOT EXISTS  exf_scheduler (
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
	CONSTRAINT PK_exf_customizing_oid PRIMARY KEY (oid),
);

CREATE INDEX Find_duplicates ON exf_queued_task (message_id, producer, queue_oid, status);
CREATE INDEX Scheduler ON exf_queued_task (scheduler_oid, created_on);
CREATE INDEX Initial_views ON exf_queued_task (created_on, task_assigned_on, owner_oid, queue_oid);
	
-- DOWN

ALTER TABLE exf_queued_task
	DROP COLUMN scheduler_oid;
	
DROP TABLE IF EXISTS exf_customizing;

DROP TABLE IF EXISTS exf_scheduler;