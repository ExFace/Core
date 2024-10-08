-- UP

CREATE TABLE dbo.exf_ai_agent (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16),
  modified_by_user_oid binary(16),
  app_oid binary(16),
  alias nvarchar(100) NOT NULL,
  name nvarchar(100) NOT NULL,
  description nvarchar(max),
  prototype_class nvarchar(255) NOT NULL,
  config_uxon nvarchar(max),
  data_connection_default_oid binary(16),
  PRIMARY KEY (oid),
  CONSTRAINT UQ_alias_app_oid UNIQUE (alias,app_oid),
  INDEX IDX_dbo_exf_ai_agent_app (app_oid),
  INDEX IDX_dbo_exf_ai_agent_data_connection_default (data_connection_default_oid),
  CONSTRAINT FK_dbo_exf_ai_agent_app FOREIGN KEY (app_oid) REFERENCES dbo.exf_app (oid),
  CONSTRAINT FK_dbo_exf_ai_agent_data_connection_default FOREIGN KEY (data_connection_default_oid) REFERENCES dbo.exf_data_connection (oid)
) ;


CREATE TABLE dbo.exf_ai_conversation (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16),
  modified_by_user_oid binary(16),
  ai_agent_oid binary(16) NOT NULL,
  user_oid binary(16),
  title nvarchar(100),
  meta_object_oid binary(16),
  context_data_uxon nvarchar(max),
  PRIMARY KEY (oid),
  INDEX IDX_dbo_exf_ai_conversation_agent (ai_agent_oid),
  INDEX IDX_dbo_exf_ai_conversation_user (user_oid),
  CONSTRAINT FK_dbo_exf_ai_conversation_agent FOREIGN KEY (ai_agent_oid) REFERENCES dbo.exf_ai_agent (oid),
  CONSTRAINT FK_dbo_exf_ai_conversation_user FOREIGN KEY (user_oid) REFERENCES dbo.exf_user (oid)
);


CREATE TABLE dbo.exf_ai_message (
  oid binary(16) NOT NULL,
  created_on datetime NOT NULL,
  modified_on datetime NOT NULL,
  created_by_user_oid binary(16),
  modified_by_user_oid binary(16),
  ai_conversation_oid binary(16) NOT NULL,
  role nvarchar(30) NOT NULL,
  message nvarchar(max) NOT NULL,
  data nvarchar(max),
  tokens_prompt int,
  tokens_completion int,
  PRIMARY KEY (oid),
  INDEX IDX_dbo_exf_ai_message_conversation (ai_conversation_oid),
  CONSTRAINT FK_dbo_exf_ai_message_conversation FOREIGN KEY (ai_conversation_oid) REFERENCES dbo.exf_ai_conversation (oid)
) ;

-- DOWN

-- Do not delete tables!