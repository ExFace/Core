-- UP

CREATE TABLE [dbo].[exf_monitor_action] (
  	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2] NOT NULL,
	[modified_on] [datetime2] NOT NULL,
	[created_by_user_oid] [binary](16) DEFAULT NULL,
	[modified_by_user_oid] [binary](16) DEFAULT NULL,
	[action_name] [nvarchar](200) NOT NULL,
  	[widget_name] [nvarchar](200) DEFAULT NULL,
  	[time] [datetime2] NOT NULL,
  	[date] [date] NOT NULL,
  	[action_alias] [nvarchar](100) DEFAULT NULL,
  	[duration_ms] [int] DEFAULT NULL,
	[object_oid] [binary](16) DEFAULT NULL,
  	[page_oid] [binary](16) DEFAULT NULL,
  	[user_oid] [binary](16) DEFAULT NULL,
  	[facade_alias] [nvarchar](100) DEFAULT NULL,
  CONSTRAINT [PK_exf_monitor_action_oid] PRIMARY KEY CLUSTERED
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

-- DOWN

DROP TABLE IF EXISTS [dbo].[exf_monitor_action];