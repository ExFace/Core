-- UP

-- Recommended by Azure Insights
DROP INDEX IF EXISTS [IX_exf_notification_hide_from_inbox_user_oid] ON [dbo].[exf_notification];
GO
CREATE NONCLUSTERED INDEX [IX_exf_notification_hide_from_inbox_user_oid] ON [dbo].[exf_notification] ( 
    [user_oid],
    [hide_from_inbox]
) INCLUDE (
    [icon], 
    [modified_on], 
    [read_on], 
    [reference], 
    [sent_by], 
    [sent_on], 
    [title], 
    [widget_uxon]
);

-- DOWN

DROP INDEX IF EXISTS [IX_exf_notification_hide_from_inbox_user_oid] ON [dbo].[exf_notification];


