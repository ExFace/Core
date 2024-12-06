-- UP

CREATE TABLE exf_announcement (
    oid binary(16) NOT NULL,
    created_on DATETIME NOT NULL,
    modified_on DATETIME NOT NULL,
    created_by_user_oid binary(16) NULL,
    modified_by_user_oid binary(16) NULL,
    communication_template_oid binary(16) NOT NULL,
    title NVARCHAR(100) NOT NULL,
    enabled_flag TINYINT DEFAULT 1,
    show_from DATETIME NOT NULL,
    show_to DATETIME NULL,
    message_uxon NVARCHAR(MAX),
    message_type NVARCHAR(10) NULL,
    CONSTRAINT PK_exf_announcement PRIMARY KEY CLUSTERED (oid),
    CONSTRAINT FK_announcement_communication_template FOREIGN KEY (communication_template_oid)
        REFERENCES exf_communication_template (oid)
        ON DELETE CASCADE
        ON UPDATE NO ACTION
);
CREATE INDEX IDX_communication_template_oid ON exf_announcement (communication_template_oid);

ALTER TABLE [exf_notification]
    ADD [hide_from_inbox] TINYINT NOT NULL CONSTRAINT D_notification_hide_from_inbox DEFAULT 0,
        [folder] NVARCHAR(100) NULL,
        [sent_by] NVARCHAR(100) NULL,
        [sent_on] DATETIME NULL,
        [reference] NVARCHAR(200) NULL;

EXEC sys.sp_executesql @query = N'UPDATE [exf_notification] SET [sent_by] = (SELECT u.[username] FROM [exf_user] u WHERE u.[oid] = [exf_notification].[created_by_user_oid]) WHERE [sent_by] IS NULL';
EXEC sys.sp_executesql @query = N'UPDATE [exf_notification] SET [sent_by] = '''' WHERE [sent_by] IS NULL';
EXEC sys.sp_executesql @query = N'UPDATE [exf_notification] SET [sent_on] = [created_on]';

ALTER TABLE [exf_notification]
    ALTER COLUMN [sent_by] NVARCHAR(100) NOT NULL;

ALTER TABLE [exf_notification]
    ALTER COLUMN [sent_on] DATETIME NOT NULL;

CREATE INDEX [IDX_notification_NotificationContext]
ON [exf_notification] ([user_oid], [read_on], [hide_from_inbox], [sent_on]);

CREATE INDEX [IDX_notification_reference]
ON [exf_notification] ([reference]);

-- DOWN

DROP INDEX [IDX_notification_NotificationContext] ON [exf_notification];

DROP INDEX [IDX_notification_reference] ON [exf_notification];

ALTER TABLE [exf_notification]
	DROP CONSTRAINT [D_notification_hide_from_inbox];
ALTER TABLE [exf_notification]
    DROP COLUMN [hide_from_inbox],
                [folder],
                [sent_by],
                [sent_on],
                [reference];

-- Drop the foreign key constraint
IF EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_announcement_communication_template'
)
BEGIN
    ALTER TABLE exf_announcement DROP CONSTRAINT FK_announcement_communication_template;
END

-- Drop the index
IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IDX_communication_template_oid'
)
BEGIN
    DROP INDEX IDX_communication_template_oid ON exf_announcement;
END

-- Drop the table
IF OBJECT_ID('exf_announcement', 'U') IS NOT NULL
BEGIN
    DROP TABLE exf_announcement;
END