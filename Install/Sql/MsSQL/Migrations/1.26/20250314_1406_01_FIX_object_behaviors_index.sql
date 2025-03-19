-- UP

CREATE INDEX IX_exf_object_behaviors_object ON exf_object_behaviors (object_oid);

-- DOWN

DROP INDEX IF EXISTS IX_exf_object_behaviors_object ON exf_object_behaviors;