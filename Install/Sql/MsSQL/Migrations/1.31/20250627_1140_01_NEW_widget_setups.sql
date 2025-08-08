-- UP

CREATE TABLE dbo.exf_widget_setup (
    oid binary(16) NOT NULL,
    created_on datetime NOT NULL,
    modified_on datetime NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    name nvarchar(100) NOT NULL,
    description nvarchar(200),
    app_oid binary(16),
    page_oid binary(16) NOT NULL,
    object_oid binary(16),
    widget_id nvarchar(2000) NOT NULL,
    prototype_file nvarchar(200) NOT NULL,
    setup_uxon nvarchar(max) NOT NULL,
    private_for_user_oid binary(16),
    CONSTRAINT PK_exf_widget_setup PRIMARY KEY (oid),
    CONSTRAINT FK_exf_widget_setup_app FOREIGN KEY (app_oid) REFERENCES exf_app (oid),
    CONSTRAINT FK_exf_widget_setup_page FOREIGN KEY (page_oid) REFERENCES exf_page (oid),
    CONSTRAINT FK_exf_widget_setup_user FOREIGN KEY (private_for_user_oid) REFERENCES exf_user (oid)
);
CREATE INDEX IX_exf_widget_setup_app ON exf_widget_setup (app_oid);
CREATE INDEX IX_exf_widget_setup_page ON exf_widget_setup (page_oid);
CREATE INDEX IX_exf_widget_setup_user ON exf_widget_setup (private_for_user_oid);

CREATE TABLE dbo.exf_widget_setup_user (
    oid binary(16) NOT NULL,
    created_on datetime NOT NULL,
    modified_on datetime NOT NULL,
    created_by_user_oid binary(16) NOT NULL,
    modified_by_user_oid binary(16) NOT NULL,
    user_oid binary(16) NOT NULL,
    widget_setup_oid binary(16) NOT NULL,
    favorite_flag tinyint NOT NULL DEFAULT '0',
    default_setup_flag tinyint NOT NULL DEFAULT '0',
    CONSTRAINT PK_exf_widget_setup_user PRIMARY KEY (oid),
    CONSTRAINT UQ_exf_widget_setup_user_setup UNIQUE (user_oid,widget_setup_oid),
    CONSTRAINT FK_exf_widget_setup_user_setup FOREIGN KEY (widget_setup_oid) REFERENCES exf_widget_setup (oid)
);
CREATE INDEX FK_exf_widget_setup_user_setup ON exf_widget_setup_user (widget_setup_oid);

-- DOWN
-- Do not automatically drop tables to avoid data loss!