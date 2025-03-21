-- UP

CREATE TABLE dbo.exf_uxon_snippet (
    oid BINARY(16) NOT NULL,
    created_on DATETIME NOT NULL,
    modified_on DATETIME NOT NULL,
    created_by_user_oid BINARY(16) NULL,
    modified_by_user_oid BINARY(16) NULL,
    object_oid BINARY(16) NULL,
    name NVARCHAR(128) NOT NULL,
    app_oid BINARY(16) NOT NULL,
    alias NVARCHAR(128) NOT NULL,
    description NVARCHAR(MAX) NULL,
    uxon NVARCHAR(MAX) NOT NULL,
    uxon_schema NVARCHAR(200) NULL,
    prototype NVARCHAR(200) NOT NULL,
    CONSTRAINT PK_exf_uxon_snippet PRIMARY KEY (oid),
    CONSTRAINT UQ_exf_uxon_snippet_alias UNIQUE (app_oid, alias),
    CONSTRAINT FK_exf_uxon_snippet_app FOREIGN KEY (app_oid) REFERENCES dbo.exf_app (oid),
    CONSTRAINT FK_exf_uxon_snippet_object FOREIGN KEY (object_oid) REFERENCES dbo.exf_object (oid)
);