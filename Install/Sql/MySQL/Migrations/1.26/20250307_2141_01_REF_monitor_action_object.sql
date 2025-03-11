-- UP

ALTER TABLE `exf_queued_task`
    ADD `action_alias` varchar(100) NULL,
    ADD `object_alias` varchar(100) NULL AFTER `action_alias`;

UPDATE exf_queued_task SET
    action_alias = JSON_UNQUOTE(JSON_EXTRACT(task_uxon, '$.action_alias')),
    object_alias = JSON_UNQUOTE(JSON_EXTRACT(task_uxon, '$.object_alias'));

-- DOWN

ALTER TABLE `exf_queued_task`
    DROP COLUMN `action_alias`,
    DROP COLUMN `object_alias`;