-- UP

ALTER TABLE exf_attribute
    ADD COLUMN abbreviation VARCHAR(10),
    ADD COLUMN icon        TEXT,
    ADD COLUMN icon_set    VARCHAR(100);

-- DOWN
-- Do not delete columns to avoid losing data!