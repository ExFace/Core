-- UP

ALTER TABLE [dbo].[exf_data_connection_credentials]
	ALTER COLUMN [name] [nvarchar](200) NOT NULL;

-- DOWN

ALTER TABLE [dbo].[exf_data_connection_credentials]
	ALTER COLUMN [name] [nvarchar](50) NOT NULL;