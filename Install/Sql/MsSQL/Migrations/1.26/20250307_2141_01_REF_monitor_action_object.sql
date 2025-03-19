-- UP

ALTER TABLE dbo.exf_queued_task
    ADD action_alias nvarchar(100) NULL,
    object_alias nvarchar(100) NULL;

GO;

UPDATE exf_queued_task SET
    action_alias = JSON_VALUE(task_uxon, '$.action_alias'),
    object_alias = JSON_VALUE(task_uxon, '$.object_alias');

-- DOWN

ALTER TABLE dbo.exf_queued_task
    DROP COLUMN action_alias,
    object_alias;