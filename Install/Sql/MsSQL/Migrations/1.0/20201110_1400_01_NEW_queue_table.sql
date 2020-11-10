-- UP

CREATE TABLE [dbo].[exf_queue] (
  [oid] [binary](16) NOT NULL,
  [created_on] [datetime2] NOT NULL,
  [modified_on] [datetime2] NOT NULL,
  [created_by_user_oid] [binary](16) DEFAULT NULL,
  [modified_by_user_oid] [binary](16) DEFAULT NULL,
  [alias] [nvarchar](50) NOT NULL,
  [name] [nvarchar](50) NOT NULL,
  [prototype_class] [nvarchar](200) NOT NULL,
  [app_oid] [binary](16) DEFAULT NULL,
  [allow_multi_queue_handling] [int] NOT NULL DEFAULT '0',
  [config_uxon] [nvarchar](max),
	CONSTRAINT [PK_exf_queue_oid] PRIMARY KEY CLUSTERED
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];
	
-- DOWN

DROP TABLE IF EXISTS [dbo].[exf_queue];