-- UP

CREATE INDEX IX_exf_object_behaviors_object (object_oid) ON exf_object_behaviors;

-- DOWN

DROP INDEX IF EXISTS IX_exf_object_behaviors_object ON exf_object_behaviors;