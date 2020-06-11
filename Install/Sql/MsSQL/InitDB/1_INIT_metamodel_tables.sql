CREATE TABLE [dbo].[exf_app](
	[oid] [binary](16) NOT NULL,
	[app_alias] [nvarchar](128) NOT NULL,
	[app_name] [nvarchar](256) NOT NULL,
	[default_language_code] [nvarchar](10) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_app_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_app$app_alias] UNIQUE NONCLUSTERED 
(
	[app_alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_attribute](
	[oid] [binary](16) NOT NULL,
	[attribute_alias] [nvarchar](100) NOT NULL,
	[attribute_name] [nvarchar](200) NOT NULL,
	[object_oid] [binary](16) NOT NULL,
	[data] [nvarchar](max) NOT NULL,
	[data_properties] [nvarchar](max) NULL,
	[attribute_formatter] [nvarchar](200) NULL,
	[data_type_oid] [binary](16) NOT NULL,
	[default_display_order] [int] NULL,
	[default_sorter_order] [int] NULL,
	[default_sorter_dir] [nvarchar](4) NULL,
	[object_label_flag] [smallint] NOT NULL,
	[object_uid_flag] [smallint] NOT NULL,
	[attribute_readable_flag] [smallint] NOT NULL,
	[attribute_writable_flag] [smallint] NOT NULL,
	[attribute_hidden_flag] [smallint] NOT NULL,
	[attribute_editable_flag] [smallint] NOT NULL,
	[attribute_required_flag] [smallint] NOT NULL,
	[attribute_system_flag] [smallint] NOT NULL,
	[attribute_sortable_flag] [smallint] NOT NULL,
	[attribute_filterable_flag] [smallint] NOT NULL,
	[attribute_aggregatable_flag] [smallint] NOT NULL,
	[default_value] [nvarchar](max) NULL,
	[fixed_value] [nvarchar](max) NULL,
	[related_object_oid] [binary](16) NULL,
	[related_object_special_key_attribute_oid] [binary](16) NULL,
	[relation_cardinality] [nvarchar](2) NOT NULL,
	[copy_with_related_object] [smallint] NULL,
	[delete_with_related_object] [smallint] NULL,
	[attribute_short_description] [nvarchar](400) NULL,
	[default_editor_uxon] [nvarchar](max) NULL,
	[default_display_uxon] [nvarchar](max) NULL,
	[custom_data_type_uxon] [nvarchar](max) NULL,
	[comments] [nvarchar](max) NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[default_aggregate_function] [nvarchar](50) NULL,
	[value_list_delimiter] [nvarchar](3) NOT NULL,
	[attribute_type] [nvarchar](1) NOT NULL,
 CONSTRAINT [PK_exf_attribute_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_attribute$Alias unique per object] UNIQUE NONCLUSTERED 
(
	[object_oid] ASC,
	[attribute_alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_attribute_compound](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[attribute_oid] [binary](16) NOT NULL,
	[compound_attribute_oid] [binary](16) NOT NULL,
	[sequence_index] [int] NOT NULL,
	[value_prefix] [nvarchar](10) NULL,
	[value_suffix] [nvarchar](10) NULL,
 CONSTRAINT [PK_exf_attribute_compound_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_attribute_compound$Sequence index unique per compound attribute] UNIQUE NONCLUSTERED 
(
	[compound_attribute_oid] ASC,
	[sequence_index] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_attribute_compound$Use each component attribute only once per compound] UNIQUE NONCLUSTERED 
(
	[compound_attribute_oid] ASC,
	[attribute_oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_auth_point](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[name] [nvarchar](50) NOT NULL,
	[class] [nvarchar](200) NOT NULL,
	[descr] [nvarchar](200) NULL,
	[app_oid] [binary](16) NOT NULL,
	[default_effect_in_app] [nchar](1) NOT NULL,
	[default_effect_local] [nchar](1) NULL,
	[combining_algorithm_in_app] [nvarchar](30) NOT NULL,
	[combining_algorithm_local] [nvarchar](30) NULL,
	[disabled_flag] [smallint] NOT NULL,
	[policy_prototype_class] [nvarchar](200) NOT NULL,
	[target_user_role_applicable] [smallint] NOT NULL,
	[target_page_group_applicable] [smallint] NOT NULL,
	[target_facade_applicable] [smallint] NOT NULL,
	[target_object_applicable] [smallint] NOT NULL,
	[target_action_applicable] [smallint] NOT NULL,
	[docs_path] [nvarchar](200) NOT NULL,
 CONSTRAINT [PK_exf_auth_point_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_auth_point$Class unique] UNIQUE NONCLUSTERED 
(
	[class] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_auth_policy](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[name] [nvarchar](100) NULL,
	[descr] [nvarchar](200) NULL,
	[effect] [nchar](1) NOT NULL,
	[disabled_flag] [smallint] NOT NULL,
	[app_oid] [binary](16) NULL,
	[auth_point_oid] [binary](16) NOT NULL,
	[target_page_group_oid] [binary](16) NULL,
	[target_user_role_oid] [binary](16) NULL,
	[target_object_oid] [binary](16) NULL,
	[target_object_action_oid] [binary](16) NULL,
	[target_action_class_path] [nvarchar](255) NULL,
	[target_facade_class_path] [nvarchar](255) NULL,
	[condition_uxon] [nvarchar](max) NULL,
 CONSTRAINT [PK_exf_auth_policy_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_data_connection](
	[oid] [binary](16) NOT NULL,
	[alias] [nvarchar](128) NOT NULL,
	[app_oid] [binary](16) NULL,
	[name] [nvarchar](64) NOT NULL,
	[data_connector] [nvarchar](128) NOT NULL,
	[data_connector_config] [nvarchar](max) NULL,
	[read_only_flag] [smallint] NOT NULL,
	[filter_context_uxon] [nvarchar](250) NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_data_connection_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_data_connection$Alias unique per app] UNIQUE NONCLUSTERED 
(
	[alias] ASC,
	[app_oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_data_connection_credentials](
	[oid] [binary](16) NOT NULL,
	[data_connection_oid] [binary](16) NOT NULL,
	[name] [nvarchar](200) NOT NULL,
	[data_connector_config] [nvarchar](max) NULL,
	[private] [smallint] NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_data_connection_credentials_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_data_source](
	[oid] [binary](16) NOT NULL,
	[name] [nvarchar](32) NOT NULL,
	[alias] [nvarchar](32) NOT NULL,
	[app_oid] [binary](16) NULL,
	[custom_connection_oid] [binary](16) NULL,
	[default_connection_oid] [binary](16) NULL,
	[custom_query_builder] [nvarchar](128) NULL,
	[default_query_builder] [nvarchar](128) NOT NULL,
	[base_object_oid] [binary](16) NULL,
	[readable_flag] [smallint] NOT NULL,
	[writable_flag] [smallint] NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_data_source_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_data_source$Alias unique per app] UNIQUE NONCLUSTERED 
(
	[app_oid] ASC,
	[alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_data_type](
	[oid] [binary](16) NOT NULL,
	[data_type_alias] [nvarchar](50) NOT NULL,
	[app_oid] [binary](16) NOT NULL,
	[name] [nvarchar](64) NOT NULL,
	[prototype] [nvarchar](128) NOT NULL,
	[config_uxon] [nvarchar](max) NULL,
	[default_editor_uxon] [nvarchar](max) NULL,
	[validation_error_oid] [binary](16) NULL,
	[short_description] [nvarchar](250) NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_data_type_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_data_type$Alias unique per app] UNIQUE NONCLUSTERED 
(
	[app_oid] ASC,
	[data_type_alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_message](
	[oid] [binary](16) NOT NULL,
	[app_oid] [binary](16) NOT NULL,
	[code] [nvarchar](16) NOT NULL,
	[title] [nvarchar](250) NOT NULL,
	[hint] [nvarchar](200) NULL,
	[description] [nvarchar](max) NULL,
	[type] [nvarchar](10) NOT NULL,
	[docs_path] [nvarchar](200) NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_message_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_message$code] UNIQUE NONCLUSTERED 
(
	[code] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_object](
	[oid] [binary](16) NOT NULL,
	[app_oid] [binary](16) NOT NULL,
	[object_name] [nvarchar](200) NOT NULL,
	[object_alias] [nvarchar](100) NOT NULL,
	[data_address] [nvarchar](max) NULL,
	[data_address_properties] [nvarchar](max) NULL,
	[readable_flag] [smallint] NOT NULL,
	[writable_flag] [smallint] NOT NULL,
	[data_source_oid] [binary](16) NULL,
	[inherit_data_source_base_object] [smallint] NOT NULL,
	[parent_object_oid] [binary](16) NULL,
	[short_description] [nvarchar](400) NULL,
	[docs_path] [nvarchar](200) NULL,
	[default_editor_uxon] [nvarchar](max) NULL,
	[comments] [nvarchar](max) NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_object_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_object$alias+app_oid] UNIQUE NONCLUSTERED 
(
	[object_alias] ASC,
	[app_oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_object_action](
	[oid] [binary](16) NOT NULL,
	[object_oid] [binary](16) NOT NULL,
	[action] [nvarchar](128) NOT NULL,
	[alias] [nvarchar](128) NOT NULL,
	[name] [nvarchar](128) NULL,
	[short_description] [nvarchar](max) NULL,
	[docs_path] [nvarchar](200) NULL,
	[config_uxon] [nvarchar](max) NULL,
	[action_app_oid] [binary](16) NOT NULL,
	[use_in_object_basket_flag] [smallint] NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_object_action_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_object_action$Alias unique per app] UNIQUE NONCLUSTERED 
(
	[action_app_oid] ASC,
	[alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_object_behaviors](
	[oid] [binary](16) NOT NULL,
	[object_oid] [binary](16) NOT NULL,
	[name] [nvarchar](100) NOT NULL,
	[behavior] [nvarchar](256) NOT NULL,
	[behavior_app_oid] [binary](16) NOT NULL,
	[config_uxon] [nvarchar](max) NULL,
	[description] [nvarchar](max) NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_object_behaviors_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_page](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NOT NULL,
	[modified_by_user_oid] [binary](16) NOT NULL,
	[app_oid] [binary](16) NULL,
	[page_template_oid] [binary](16) NULL,
	[name] [nvarchar](50) NOT NULL,
	[alias] [nvarchar](100) NULL,
	[description] [nvarchar](200) NULL,
	[intro] [nvarchar](200) NULL,
	[content] [nvarchar](max) NULL,
	[parent_oid] [binary](16) NULL,
	[menu_index] [int] NOT NULL,
	[menu_visible] [smallint] NOT NULL,
	[default_menu_parent_alias] [nvarchar](100) NULL,
	[default_menu_parent_oid] [binary](16) NULL,
	[default_menu_index] [int] NULL,
	[replace_page_oid] [binary](16) NULL,
	[auto_update_with_app] [smallint] NOT NULL,
	[published] [smallint] NOT NULL,
 CONSTRAINT [PK_exf_page_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_page$Alias unique] UNIQUE NONCLUSTERED 
(
	[alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_page_group](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[name] [nvarchar](50) NOT NULL,
	[descr] [nvarchar](200) NULL,
	[app_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_page_group_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_page_group$Name unique per app] UNIQUE NONCLUSTERED 
(
	[name] ASC,
	[app_oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_page_group_pages](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[page_oid] [binary](16) NOT NULL,
	[page_group_oid] [binary](16) NOT NULL,
 CONSTRAINT [PK_exf_page_group_pages_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_page_group_pages$Page unique per group] UNIQUE NONCLUSTERED 
(
	[page_oid] ASC,
	[page_group_oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_page_template](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[app_oid] [binary](16) NULL,
	[name] [nvarchar](50) NOT NULL,
	[description] [nvarchar](200) NULL,
	[facade_filepath] [nvarchar](100) NOT NULL,
	[facade_uxon] [nvarchar](max) NULL,
 CONSTRAINT [PK_exf_page_template_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

CREATE TABLE [dbo].[exf_user](
	[oid] [binary](16) NOT NULL,
	[first_name] [nvarchar](64) NULL,
	[last_name] [nvarchar](64) NULL,
	[username] [nvarchar](60) NOT NULL,
	[password] [nvarchar](60) NULL,
	[locale] [nvarchar](20) NOT NULL,
	[email] [nvarchar](100) NULL,
	[disabled_flag] [smallint] NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_user_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_user$username] UNIQUE NONCLUSTERED 
(
	[username] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_user_authenticator](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[authenticator_id] [nvarchar](100) NOT NULL,
	[user_oid] [binary](16) NOT NULL,
	[authenticator_username] [nvarchar](100) NOT NULL,
	[disabled_flag] [int] NOT NULL,
	[last_authenticated_on] [datetime2](0) NULL,
 CONSTRAINT [PK_exf_user_authenticator_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_user_authenticator$Authenticator unique per user] UNIQUE NONCLUSTERED 
(
	[user_oid] ASC,
	[authenticator_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_user_credentials](
	[oid] [binary](16) NOT NULL,
	[user_oid] [binary](16) NOT NULL,
	[data_connection_credentials_oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_user_credentials_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_user_role](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[name] [nvarchar](50) NOT NULL,
	[alias] [nvarchar](50) NOT NULL,
	[descr] [nvarchar](200) NULL,
	[app_oid] [binary](16) NULL,
	[sync_with_external_role_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_user_role_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_user_role$Unique App+Alias] UNIQUE NONCLUSTERED 
(
	[app_oid] ASC,
	[alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_user_role_external](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[name] [nvarchar](50) NOT NULL,
	[alias] [nvarchar](50) NOT NULL,
	[user_role_oid] [binary](16) NULL,
	[authenticator_id] [nvarchar](100) NOT NULL,
 CONSTRAINT [PK_exf_user_role_external_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_user_role_external$Alias unique per authenticator] UNIQUE NONCLUSTERED 
(
	[authenticator_id] ASC,
	[alias] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_user_role_users](
	[oid] [binary](16) NOT NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
	[user_role_oid] [binary](16) NOT NULL,
	[user_oid] [binary](16) NOT NULL,
 CONSTRAINT [PK_exf_user_role_users_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [exf_user_role_users$Role unique per user] UNIQUE NONCLUSTERED 
(
	[user_oid] ASC,
	[user_role_oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY];

CREATE TABLE [dbo].[exf_uxon_preset](
	[oid] [binary](16) NOT NULL,
	[app_oid] [binary](16) NULL,
	[name] [nvarchar](250) NOT NULL,
	[description] [nvarchar](max) NULL,
	[uxon] [nvarchar](max) NOT NULL,
	[wrap_path_in_preset] [nvarchar](255) NULL,
	[prototype] [nvarchar](200) NULL,
	[uxon_schema] [nvarchar](100) NULL,
	[created_on] [datetime2](0) NOT NULL,
	[modified_on] [datetime2](0) NOT NULL,
	[created_by_user_oid] [binary](16) NULL,
	[modified_by_user_oid] [binary](16) NULL,
 CONSTRAINT [PK_exf_uxon_preset_oid] PRIMARY KEY CLUSTERED 
(
	[oid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

ALTER TABLE [dbo].[exf_app] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_app] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [attribute_formatter];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (0x30000000000000000000000000000000) FOR [data_type_oid];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [default_display_order];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [default_sorter_order];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [default_sorter_dir];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((0)) FOR [object_label_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((0)) FOR [object_uid_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((1)) FOR [attribute_readable_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((1)) FOR [attribute_writable_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((0)) FOR [attribute_hidden_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((1)) FOR [attribute_editable_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((0)) FOR [attribute_required_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((0)) FOR [attribute_system_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((1)) FOR [attribute_sortable_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((1)) FOR [attribute_filterable_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT ((1)) FOR [attribute_aggregatable_flag];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [related_object_oid];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [related_object_special_key_attribute_oid];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (N'') FOR [relation_cardinality];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [copy_with_related_object];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [delete_with_related_object];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [attribute_short_description];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (NULL) FOR [default_aggregate_function];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (N',') FOR [value_list_delimiter];

ALTER TABLE [dbo].[exf_attribute] ADD  DEFAULT (N'D') FOR [attribute_type];

ALTER TABLE [dbo].[exf_attribute_compound] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_attribute_compound] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_attribute_compound] ADD  DEFAULT (N'') FOR [value_prefix];

ALTER TABLE [dbo].[exf_attribute_compound] ADD  DEFAULT (N'') FOR [value_suffix];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT (NULL) FOR [descr];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT (N'P') FOR [default_effect_in_app];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT (NULL) FOR [default_effect_local];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT (NULL) FOR [combining_algorithm_local];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT ((0)) FOR [disabled_flag];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT ((0)) FOR [target_user_role_applicable];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT ((0)) FOR [target_page_group_applicable];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT ((0)) FOR [target_facade_applicable];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT ((0)) FOR [target_object_applicable];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT ((0)) FOR [target_action_applicable];

ALTER TABLE [dbo].[exf_auth_point] ADD  DEFAULT (N'') FOR [docs_path];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (N'') FOR [name];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (N'') FOR [descr];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT ((0)) FOR [disabled_flag];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [target_page_group_oid];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [target_user_role_oid];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [target_object_oid];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [target_object_action_oid];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [target_action_class_path];

ALTER TABLE [dbo].[exf_auth_policy] ADD  DEFAULT (NULL) FOR [target_facade_class_path];

ALTER TABLE [dbo].[exf_data_connection] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_data_connection] ADD  DEFAULT ((0)) FOR [read_only_flag];

ALTER TABLE [dbo].[exf_data_connection] ADD  DEFAULT (NULL) FOR [filter_context_uxon];

ALTER TABLE [dbo].[exf_data_connection] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_data_connection] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_data_connection_credentials] ADD  DEFAULT ((1)) FOR [private];

ALTER TABLE [dbo].[exf_data_connection_credentials] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_data_connection_credentials] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT (NULL) FOR [custom_connection_oid];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT (NULL) FOR [default_connection_oid];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT (NULL) FOR [custom_query_builder];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT (NULL) FOR [base_object_oid];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT ((1)) FOR [readable_flag];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT ((1)) FOR [writable_flag];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_data_source] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_data_type] ADD  DEFAULT (NULL) FOR [validation_error_oid];

ALTER TABLE [dbo].[exf_data_type] ADD  DEFAULT (NULL) FOR [short_description];

ALTER TABLE [dbo].[exf_data_type] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_data_type] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_message] ADD  DEFAULT (NULL) FOR [hint];

ALTER TABLE [dbo].[exf_message] ADD  DEFAULT (NULL) FOR [docs_path];

ALTER TABLE [dbo].[exf_message] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_message] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT ((1)) FOR [readable_flag];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT ((1)) FOR [writable_flag];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT (NULL) FOR [data_source_oid];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT ((1)) FOR [inherit_data_source_base_object];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT (NULL) FOR [parent_object_oid];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT (NULL) FOR [short_description];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT (NULL) FOR [docs_path];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_object] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_object_action] ADD  DEFAULT (NULL) FOR [name];

ALTER TABLE [dbo].[exf_object_action] ADD  DEFAULT (NULL) FOR [docs_path];

ALTER TABLE [dbo].[exf_object_action] ADD  DEFAULT ((0)) FOR [use_in_object_basket_flag];

ALTER TABLE [dbo].[exf_object_action] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_object_action] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_object_behaviors] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_object_behaviors] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [page_template_oid];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [alias];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [description];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [intro];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [parent_oid];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT ((0)) FOR [menu_index];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT ((1)) FOR [menu_visible];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [default_menu_parent_alias];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [default_menu_parent_oid];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [default_menu_index];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT (NULL) FOR [replace_page_oid];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT ((1)) FOR [auto_update_with_app];

ALTER TABLE [dbo].[exf_page] ADD  DEFAULT ((0)) FOR [published];

ALTER TABLE [dbo].[exf_page_group] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_page_group] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_page_group] ADD  DEFAULT (NULL) FOR [descr];

ALTER TABLE [dbo].[exf_page_group] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_page_group_pages] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_page_group_pages] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_page_template] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_page_template] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_page_template] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_page_template] ADD  DEFAULT (NULL) FOR [description];

ALTER TABLE [dbo].[exf_user] ADD  DEFAULT (NULL) FOR [first_name];

ALTER TABLE [dbo].[exf_user] ADD  DEFAULT (NULL) FOR [last_name];

ALTER TABLE [dbo].[exf_user] ADD  DEFAULT (NULL) FOR [password];

ALTER TABLE [dbo].[exf_user] ADD  DEFAULT (NULL) FOR [email];

ALTER TABLE [dbo].[exf_user] ADD  DEFAULT ((0)) FOR [disabled_flag];

ALTER TABLE [dbo].[exf_user] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_user] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_user_authenticator] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_user_authenticator] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_user_authenticator] ADD  DEFAULT (N'') FOR [authenticator_username];

ALTER TABLE [dbo].[exf_user_authenticator] ADD  DEFAULT ((0)) FOR [disabled_flag];

ALTER TABLE [dbo].[exf_user_authenticator] ADD  DEFAULT (NULL) FOR [last_authenticated_on];

ALTER TABLE [dbo].[exf_user_credentials] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_user_credentials] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_user_role] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_user_role] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_user_role] ADD  DEFAULT (NULL) FOR [descr];

ALTER TABLE [dbo].[exf_user_role] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_user_role] ADD  DEFAULT (NULL) FOR [sync_with_external_role_oid];

ALTER TABLE [dbo].[exf_user_role_external] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_user_role_external] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_user_role_external] ADD  DEFAULT (NULL) FOR [user_role_oid];

ALTER TABLE [dbo].[exf_user_role_users] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_user_role_users] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];

ALTER TABLE [dbo].[exf_uxon_preset] ADD  DEFAULT (NULL) FOR [app_oid];

ALTER TABLE [dbo].[exf_uxon_preset] ADD  DEFAULT (NULL) FOR [wrap_path_in_preset];

ALTER TABLE [dbo].[exf_uxon_preset] ADD  DEFAULT (NULL) FOR [prototype];

ALTER TABLE [dbo].[exf_uxon_preset] ADD  DEFAULT (NULL) FOR [uxon_schema];

ALTER TABLE [dbo].[exf_uxon_preset] ADD  DEFAULT (NULL) FOR [created_by_user_oid];

ALTER TABLE [dbo].[exf_uxon_preset] ADD  DEFAULT (NULL) FOR [modified_by_user_oid];