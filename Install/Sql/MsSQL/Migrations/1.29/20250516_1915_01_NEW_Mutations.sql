-- UP

CREATE TABLE exf_mutation_set (
    oid binary(16) NOT NULL,
    created_on datetime NOT NULL,
    modified_on datetime NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    app_oid binary(16) NOT NULL,
    name nvarchar(128) NOT NULL,
    description nvarchar(max),
    enabled_flag tinyint NOT NULL DEFAULT '1',
    CONSTRAINT PK_exf_mutation_set PRIMARY KEY (oid),
    CONSTRAINT UQ_exf_mutation_set_name_per_app UNIQUE (name, app_oid),
    CONSTRAINT FK_exf_mutation_set_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid)
)
CREATE INDEX IX_exf_mutation_set_app ON exf_mutation_set (app_oid);

CREATE TABLE exf_mutation_target (
    oid binary(16) NOT NULL,
    created_on datetime NOT NULL,
    modified_on datetime NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    app_oid binary(16) NOT NULL,
    name nvarchar(128) NOT NULL,
    description nvarchar(max),
    object_oid binary(16) NOT NULL,
    CONSTRAINT PK_exf_mutation_target PRIMARY KEY (oid),
    CONSTRAINT UQ_exf_mutation_target UNIQUE (name,app_oid),
    CONSTRAINT FK_mutation_target_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid)
);
CREATE INDEX IX_exf_mutation_target_app ON exf_mutation_target (app_oid);

CREATE TABLE exf_mutation_type (
    oid binary(16) NOT NULL,
    created_on datetime NOT NULL,
    modified_on datetime NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    app_oid binary(16) NOT NULL,
    name nvarchar(128) NOT NULL,
    description nvarchar(max),
    mutation_point_file nvarchar(200) NOT NULL,
    mutation_prototype_file nvarchar(200) NOT NULL,
    mutation_target_oid binary(16) NOT NULL,
    CONSTRAINT PK_exf_mutation_type PRIMARY KEY (oid),
    CONSTRAINT UQ_exf_mutation_type_name_per_app UNIQUE (name, app_oid),
    CONSTRAINT FK_exf_mutation_type_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid),
    CONSTRAINT FK_mutation_type_target FOREIGN KEY (mutation_target_oid) REFERENCES exf_mutation_target (oid)
);
CREATE INDEX IX_exf_mutation_type_app ON exf_mutation_type (app_oid);
CREATE INDEX IX_exf_mutation_type_target ON exf_mutation_type (mutation_target_oid);

CREATE TABLE exf_mutation (
    oid binary(16) NOT NULL,
    created_on datetime NOT NULL,
    modified_on datetime NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    name nvarchar(128) NOT NULL,
    description nvarchar(max),
    enabled_flag tinyint NOT NULL DEFAULT '1',
    mutation_set_oid binary(16) NOT NULL,
    mutation_type_oid binary(16) NOT NULL,
    config_base_object_oid binary(16) NULL,
    config_uxon nvarchar(max),
    targets_json nvarchar(max),
    CONSTRAINT PK_exf_mutation PRIMARY KEY (oid),
    CONSTRAINT UQ_exf_mutation_name_per_set UNIQUE (name, mutation_set_oid),
    CONSTRAINT FK_exf_mutation_mutation_set FOREIGN KEY (mutation_set_oid) REFERENCES exf_mutation_set (oid),
    CONSTRAINT FK_exf_mutation_mutation_type FOREIGN KEY (mutation_type_oid) REFERENCES exf_mutation_type (oid)
);
CREATE INDEX IX_exf_mutation_mutation_set ON exf_mutation (mutation_set_oid);
CREATE INDEX IX_exf_mutation_mutation_type ON exf_mutation (mutation_type_oid);
CREATE INDEX IX_exf_mutation_config_base_object ON exf_mutation (config_base_object_oid);

-- DOWN

/*
-- Do not DROP tables by default

DROP TABLE exf_mutation;
DROP TABLE exf_mutation_type;
DROP TABLE exf_mutation_set;
DROP TABLE exf_mutation_target;
*/