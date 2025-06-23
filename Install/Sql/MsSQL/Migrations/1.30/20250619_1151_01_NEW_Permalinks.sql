-- UP

CREATE TABLE exf_permalink (
    oid BINARY(16) NOT NULL,
    created_on DATETIME NOT NULL,
    modified_on DATETIME NOT NULL,
    created_by_user_oid BINARY(16) NOT NULL,
    modified_by_user_oid BINARY(16) NOT NULL,
    app_oid BINARY(16) NULL,
    object_oid BINARY(16) NULL,
    name VARCHAR(50) NOT NULL,
    alias VARCHAR(100) NULL,
    description VARCHAR(400) NULL,
    prototype_file VARCHAR(200) COLLATE Latin1_General_CI_AS NULL,
    config_uxon NVARCHAR(MAX) COLLATE Latin1_General_CI_AS,
    CONSTRAINT PK_exf_permalink PRIMARY KEY (oid),
    CONSTRAINT UQ_exf_permalink_alias UNIQUE (alias),
    CONSTRAINT FK_exf_permalink_set_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid),
    CONSTRAINT FK_exf_permalink_object_app FOREIGN KEY (object_oid) REFERENCES exf_object (oid)
);

-- DOWN