-- UP

CREATE TABLE [dbo].[exf_queued_task] (
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2] NOT NULL,
	[modified_on] [datetime2] NOT NULL,
	[created_by_user_oid] [binary](16) DEFAULT NULL,
	[modified_by_user_oid] [binary](16) DEFAULT NULL,
	[producer] [nvarchar](50) NOT NULL,
	[message_id] [nvarchar](50) DEFAULT NULL,
	[task_assigned_on] [datetime2] NOT NULL,
	[task_uxon] [nvarchar](max) NOT NULL,
	[owner] [binary](16) NOT NULL,
    [status] [int] NOT NULL,
	[topics] [nvarchar](500) DEFAULT NULL,
	[user_agent] [nvarchar](500) NULL,
	[result_code] [int] DEFAULT NULL,
    [result] [nvarchar](max) DEFAULT NULL,
    [error_message] [nvarchar](max) DEFAULT NULL,
    [error_logid] [nvarchar](20) DEFAULT NULL,
    [parent_item_oid] [binary](16) DEFAULT NULL,
	[queue] [binary](16) NOT NULL,
	CONSTRAINT [PK_exf_queued_task_oid] PRIMARY KEY CLUSTERED
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

-- DOWN

DROP TABLE IF EXISTS [dbo].[exf_queued_task];