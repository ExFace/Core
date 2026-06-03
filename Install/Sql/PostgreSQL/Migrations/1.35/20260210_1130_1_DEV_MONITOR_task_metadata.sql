-- UP

ALTER TABLE exf_monitor_action
    ADD COLUMN task_class VARCHAR(100),
    ADD COLUMN ui_flag SMALLINT,
    ADD COLUMN request_size INTEGER;

-- DOWN
-- Do not delete columns to avoid losing data!