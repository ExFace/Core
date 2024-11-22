-- UP

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