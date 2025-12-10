-- Table exf_announcement
CREATE TABLE IF NOT EXISTS exf_announcement (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    communication_template_oid uuid NOT NULL,
    title varchar(100) NOT NULL,
    enabled_flag smallint DEFAULT 1,
    show_from timestamp NOT NULL,
    show_to timestamp,
    message_uxon text,
    message_type varchar(10),
    CONSTRAINT exf_announcement_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_announcement_communication_template_oid_idx ON exf_announcement (communication_template_oid);

-- Table exf_api
CREATE TABLE IF NOT EXISTS exf_api (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    url varchar(256),
    name varchar(128) NOT NULL,
    type varchar(2) NOT NULL,
    facade varchar(128),
    app_oid uuid,
    uxon text,
    last_call_on timestamp,
    last_call_code smallint,
    health_url varchar(256),
    metadata_url varchar(256),
    explorer_url varchar(256),
    description text,
    CONSTRAINT exf_api_pkey PRIMARY KEY (oid)
);

CREATE INDEX fk_api_app_idx ON exf_api (app_oid);

-- Table exf_api_system
CREATE TABLE IF NOT EXISTS exf_api_system (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    api_oid uuid NOT NULL,
    external_system_oid uuid NOT NULL,
    triggered_by varchar(2) NOT NULL,
    data_flow_direction varchar(2) NOT NULL,
    authentication varchar(250),
    interval varchar(20),
    info varchar(500),
    CONSTRAINT exf_api_system_pkey PRIMARY KEY (oid)
);

CREATE INDEX fk_api_system_api_idx ON exf_api_system (api_oid);
CREATE INDEX fk_api_system_external_system_idx ON exf_api_system (external_system_oid);

-- Table exf_app
CREATE TABLE IF NOT EXISTS exf_app (
    oid uuid NOT NULL,
    app_alias varchar(128) NOT NULL,
    app_name varchar(256) NOT NULL,
    default_language_code varchar(10) NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    puplished smallint NOT NULL DEFAULT 0,
    CONSTRAINT exf_app_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_app_app_alias_key UNIQUE (app_alias)
);

-- Table exf_attribute
CREATE TABLE IF NOT EXISTS exf_attribute (
    oid uuid NOT NULL,
    attribute_alias varchar(100) NOT NULL,
    attribute_name varchar(200) NOT NULL,
    object_oid uuid NOT NULL,
    data text NOT NULL,
    data_properties text,
    attribute_formatter text,
    data_type_oid uuid NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
    default_display_order int,
    default_sorter_order int,
    default_sorter_dir varchar(4),
    object_label_flag smallint NOT NULL DEFAULT 0,
    object_uid_flag smallint NOT NULL DEFAULT 0,
    attribute_readable_flag smallint NOT NULL DEFAULT 1,
    attribute_writable_flag smallint NOT NULL DEFAULT 1,
    attribute_hidden_flag smallint NOT NULL DEFAULT 0,
    attribute_editable_flag smallint NOT NULL DEFAULT 1,
    attribute_copyable_flag smallint NOT NULL DEFAULT 1,
    attribute_required_flag smallint NOT NULL DEFAULT 0,
    attribute_system_flag smallint NOT NULL DEFAULT 0,
    attribute_sortable_flag smallint NOT NULL DEFAULT 1,
    attribute_filterable_flag smallint NOT NULL DEFAULT 1,
    attribute_aggregatable_flag smallint NOT NULL DEFAULT 1,
    default_value text,
    fixed_value text,
    related_object_oid uuid,
    related_object_special_key_attribute_oid uuid,
    relation_cardinality varchar(2) NOT NULL DEFAULT '',
    copy_with_related_object smallint,
    delete_with_related_object smallint,
    attribute_short_description varchar(400),
    default_editor_uxon text,
    default_display_uxon text,
    custom_data_type_uxon text,
    comments text,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    default_aggregate_function varchar(50),
    value_list_delimiter varchar(3) NOT NULL DEFAULT ',',
    attribute_type varchar(1) NOT NULL DEFAULT 'D',
    CONSTRAINT exf_attribute_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_attribute_alias_unique_per_object UNIQUE (object_oid, attribute_alias)
);

CREATE INDEX exf_attribute_object_oid_idx ON exf_attribute (object_oid);
CREATE INDEX exf_attribute_related_object_oid_idx ON exf_attribute (related_object_oid);

-- Table exf_attribute_compound
CREATE TABLE IF NOT EXISTS exf_attribute_compound (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    attribute_oid uuid NOT NULL,
    compound_attribute_oid uuid NOT NULL,
    sequence_index int NOT NULL,
    value_prefix varchar(10) DEFAULT '',
    value_suffix varchar(10) DEFAULT '',
    CONSTRAINT exf_attribute_compound_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_attribute_compound_seq_unique UNIQUE (compound_attribute_oid, sequence_index),
    CONSTRAINT exf_attribute_compound_attr_once UNIQUE (compound_attribute_oid, attribute_oid)
);

-- Table exf_attribute_group
CREATE TABLE IF NOT EXISTS exf_attribute_group (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    object_oid uuid NOT NULL,
    name varchar(50) NOT NULL,
    alias varchar(50) NOT NULL,
    app_oid uuid NOT NULL,
    description varchar(200),
    CONSTRAINT exf_attribute_group_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_attribute_group_uq_alias_per_object UNIQUE (object_oid, alias)
);

CREATE INDEX exf_attribute_group_app_oid_idx ON exf_attribute_group (app_oid);

-- Table exf_attribute_group_attributes
CREATE TABLE IF NOT EXISTS exf_attribute_group_attributes (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    attribute_oid uuid NOT NULL,
    attribute_group_oid uuid NOT NULL,
    pos smallint NOT NULL,
    CONSTRAINT exf_attribute_group_attributes_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_attribute_group_attributes_unique UNIQUE (attribute_oid, attribute_group_oid)
);

CREATE INDEX exf_attribute_group_attributes_read_idx ON exf_attribute_group_attributes (attribute_group_oid, pos);

-- Table exf_auth_point
CREATE TABLE IF NOT EXISTS exf_auth_point (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(50) NOT NULL,
    class varchar(200) NOT NULL,
    descr varchar(200),
    app_oid uuid NOT NULL,
    default_effect_in_app char(1) NOT NULL DEFAULT 'P',
    default_effect_local char(1),
    combining_algorithm_in_app varchar(30) NOT NULL,
    combining_algorithm_local varchar(30),
    disabled_flag smallint NOT NULL DEFAULT 0,
    policy_prototype_class varchar(200) NOT NULL,
    target_user_role_applicable smallint NOT NULL DEFAULT 0,
    target_page_group_applicable smallint NOT NULL DEFAULT 0,
    target_facade_applicable smallint NOT NULL DEFAULT 0,
    target_object_applicable smallint NOT NULL DEFAULT 0,
    target_action_applicable smallint NOT NULL DEFAULT 0,
    target_app_applicable smallint NOT NULL DEFAULT 0,
    docs_path varchar(200) NOT NULL DEFAULT '',
    CONSTRAINT exf_auth_point_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_auth_point_class_unique UNIQUE (class)
);

-- Table exf_auth_policy
CREATE TABLE IF NOT EXISTS exf_auth_policy (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(100) DEFAULT '',
    descr varchar(200) DEFAULT '',
    effect char(1) NOT NULL,
    disabled_flag smallint NOT NULL DEFAULT 0,
    app_oid uuid,
    auth_point_oid uuid NOT NULL,
    target_page_group_oid uuid,
    target_user_role_oid uuid,
    target_object_oid uuid,
    target_object_action_oid uuid,
    target_action_class_path varchar(255),
    target_facade_class_path varchar(255),
    target_app_oid uuid,
    condition_uxon text,
    CONSTRAINT exf_auth_policy_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_auth_policy_model_loader_idx ON exf_auth_policy (auth_point_oid, disabled_flag, target_user_role_oid);

-- Table exf_communication_channel
CREATE TABLE IF NOT EXISTS exf_communication_channel (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(50) NOT NULL,
    alias varchar(100) NOT NULL,
    descr varchar(200),
    app_oid uuid,
    data_connection_default_oid uuid,
    message_prototype varchar(200) NOT NULL,
    message_default_uxon text,
    mute_flag_default smallint NOT NULL DEFAULT 0,
    CONSTRAINT exf_communication_channel_pkey PRIMARY KEY (oid)
);

-- Table exf_communication_template
CREATE TABLE IF NOT EXISTS exf_communication_template (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(100) NOT NULL,
    alias varchar(100) NOT NULL,
    app_oid uuid,
    communication_channel_oid uuid NOT NULL,
    message_uxon text NOT NULL,
    object_oid uuid,
    CONSTRAINT exf_communication_template_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_communication_template_app_alias UNIQUE (alias, app_oid)
);

-- Table exf_customizing
CREATE TABLE IF NOT EXISTS exf_customizing (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    table_name varchar(50) NOT NULL,
    row_oid uuid NOT NULL,
    column_name varchar(50) NOT NULL,
    value varchar(200) NOT NULL,
    CONSTRAINT exf_customizing_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_customizing_ref_table_cell UNIQUE (row_oid, column_name)
);

-- Table exf_data_connection
CREATE TABLE IF NOT EXISTS exf_data_connection (
    oid uuid NOT NULL,
    alias varchar(128) NOT NULL,
    app_oid uuid,
    name varchar(64) NOT NULL,
    data_connector varchar(128) NOT NULL,
    data_connector_config text,
    time_zone varchar(50),
    read_only_flag smallint NOT NULL DEFAULT 0,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_data_connection_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_data_connection_alias_unique UNIQUE (alias, app_oid)
);

-- Table exf_data_connection_credentials
CREATE TABLE IF NOT EXISTS exf_data_connection_credentials (
    oid uuid NOT NULL,
    data_connection_oid uuid NOT NULL,
    name varchar(200) NOT NULL,
    data_connector_config text,
    private smallint NOT NULL DEFAULT 1,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_data_connection_credentials_pkey PRIMARY KEY (oid)
);

-- Table exf_data_source
CREATE TABLE IF NOT EXISTS exf_data_source (
    oid uuid NOT NULL,
    name varchar(32) NOT NULL,
    alias varchar(32) NOT NULL,
    app_oid uuid,
    custom_connection_oid uuid,
    default_connection_oid uuid,
    custom_query_builder varchar(128),
    default_query_builder varchar(128) NOT NULL,
    base_object_oid uuid,
    readable_flag smallint NOT NULL DEFAULT 1,
    writable_flag smallint NOT NULL DEFAULT 1,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_data_source_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_data_source_alias_unique UNIQUE (app_oid, alias)
);

-- Table exf_data_type
CREATE TABLE IF NOT EXISTS exf_data_type (
    oid uuid NOT NULL,
    data_type_alias varchar(50) NOT NULL,
    app_oid uuid NOT NULL,
    name varchar(64) NOT NULL,
    prototype varchar(128) NOT NULL,
    config_uxon text,
    default_editor_uxon text,
    default_display_uxon text,
    validation_error_oid uuid,
    short_description varchar(250),
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_data_type_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_data_type_alias_unique UNIQUE (app_oid, data_type_alias)
);

-- Table exf_external_system
CREATE TABLE IF NOT EXISTS exf_external_system (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    name varchar(128) NOT NULL,
    app_oid uuid,
    ip_mask varchar(40),
    CONSTRAINT exf_external_system_pkey PRIMARY KEY (oid)
);

CREATE INDEX fk_external_system_app_idx ON exf_external_system (app_oid);

-- Table exf_message
CREATE TABLE IF NOT EXISTS exf_message (
    oid uuid NOT NULL,
    app_oid uuid NOT NULL,
    code varchar(16) NOT NULL,
    title varchar(250) NOT NULL,
    hint varchar(200),
    description text,
    type varchar(10) NOT NULL,
    docs_path varchar(200),
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_message_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_message_code_unique UNIQUE (code)
);

-- Table exf_monitor_action
CREATE TABLE IF NOT EXISTS exf_monitor_action (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    action_name varchar(200) NOT NULL,
    widget_name varchar(200),
    time timestamp NOT NULL,
    date date NOT NULL,
    action_alias varchar(100),
    duration_ms int,
    object_oid uuid,
    page_oid uuid,
    user_oid uuid,
    facade_alias varchar(100),
    CONSTRAINT exf_monitor_action_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_monitor_action_date_user_page_idx ON exf_monitor_action (date, user_oid, page_oid, time);

-- Table exf_monitor_error
CREATE TABLE IF NOT EXISTS exf_monitor_error (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    log_id varchar(10) NOT NULL,
    request_id varchar(50) DEFAULT '',
    error_level varchar(20) NOT NULL,
    error_widget text NOT NULL,
    message text NOT NULL,
    date date NOT NULL,
    status int NOT NULL,
    user_oid uuid,
    action_oid uuid,
    comment text,
    ticket_no varchar(20),
    CONSTRAINT exf_monitor_error_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_monitor_error_date_user_status_idx ON exf_monitor_error (date, user_oid, status);
CREATE INDEX exf_monitor_error_logid_idx ON exf_monitor_error (log_id);

-- Table exf_mutation
CREATE TABLE IF NOT EXISTS exf_mutation (
     oid uuid NOT NULL,
     created_on timestamp NOT NULL,
     modified_on timestamp NOT NULL,
     created_by_user_oid uuid NOT NULL,
     modified_by_user_oid uuid NOT NULL,
     name varchar(128) NOT NULL,
    description text,
    enabled_flag smallint NOT NULL DEFAULT 1,
    mutation_set_oid uuid NOT NULL,
    mutation_type_oid uuid NOT NULL,
    config_base_object_oid uuid NOT NULL,
    config_uxon text,
    targets_json text,
    CONSTRAINT exf_mutation_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_mutation_name_unique_per_set UNIQUE (name, mutation_set_oid)
);

CREATE INDEX fk_mutation_mutation_set_idx ON exf_mutation (mutation_set_oid);
CREATE INDEX fk_mutation_set_mutation_type_idx ON exf_mutation (mutation_type_oid);

-- Table exf_mutation_set
CREATE TABLE IF NOT EXISTS exf_mutation_set (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    app_oid uuid NOT NULL,
    name varchar(128) NOT NULL,
    description text,
    enabled_flag smallint NOT NULL DEFAULT 1,
    CONSTRAINT exf_mutation_set_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_mutation_set_name_unique UNIQUE (name, app_oid)
);

CREATE INDEX fk_mutation_set_app_idx ON exf_mutation_set (app_oid);

-- Table exf_mutation_target
CREATE TABLE IF NOT EXISTS exf_mutation_target (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    app_oid uuid NOT NULL,
    name varchar(128) NOT NULL,
    description text,
    object_oid uuid NOT NULL,
    CONSTRAINT exf_mutation_target_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_mutation_target_name_unique UNIQUE (name, app_oid)
);

CREATE INDEX fk_mutation_target_app_idx ON exf_mutation_target (app_oid);

-- Table exf_mutation_type
CREATE TABLE IF NOT EXISTS exf_mutation_type (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    app_oid uuid NOT NULL,
    name varchar(128) NOT NULL,
    description text,
    mutation_point_file varchar(200) NOT NULL,
    mutation_prototype_file varchar(200) NOT NULL,
    mutation_target_oid uuid NOT NULL,
    CONSTRAINT exf_mutation_type_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_mutation_type_name_unique UNIQUE (name, app_oid)
);

CREATE INDEX fk_mutation_type_app_idx ON exf_mutation_type (app_oid);
CREATE INDEX fk_mutation_type_target_idx ON exf_mutation_type (mutation_target_oid);

-- Table exf_notification
CREATE TABLE IF NOT EXISTS exf_notification (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    user_oid uuid NOT NULL,
    title varchar(200) NOT NULL,
    icon varchar(50),
    widget_uxon text NOT NULL,
    read_on timestamp,
    hide_from_inbox smallint NOT NULL DEFAULT 0,
    folder varchar(100),
    sent_by varchar(100) NOT NULL,
    sent_on timestamp NOT NULL,
    reference varchar(200),
    CONSTRAINT exf_notification_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_notification_user_read_hide_sent_idx ON exf_notification (user_oid, read_on, hide_from_inbox, sent_on);
CREATE INDEX exf_notification_announcement_search_idx ON exf_notification (user_oid, reference);

-- Table exf_object
CREATE TABLE IF NOT EXISTS exf_object (
    oid uuid NOT NULL,
    app_oid uuid NOT NULL,
    object_name varchar(200) NOT NULL,
    object_alias varchar(100) NOT NULL,
    data_address text,
    data_address_properties text,
    readable_flag smallint NOT NULL DEFAULT 1,
    writable_flag smallint NOT NULL DEFAULT 1,
    data_source_oid uuid,
    inherit_data_source_base_object smallint NOT NULL DEFAULT 1,
    parent_object_oid uuid,
    short_description varchar(400),
    docs_path varchar(200),
    default_editor_uxon text,
    comments text,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_object_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_object_alias_app_oid_unique UNIQUE (object_alias, app_oid)
);

CREATE INDEX exf_object_alias_idx ON exf_object (object_alias);
CREATE INDEX exf_object_app_oid_idx ON exf_object (app_oid);
CREATE INDEX exf_object_parent_object_oid_idx ON exf_object (parent_object_oid);
CREATE INDEX exf_object_data_source_oid_idx ON exf_object (data_source_oid);

-- Table exf_object_action
CREATE TABLE IF NOT EXISTS exf_object_action (
    oid uuid NOT NULL,
    object_oid uuid NOT NULL,
    action varchar(128) NOT NULL,
    alias varchar(128) NOT NULL,
    name varchar(128),
    short_description text,
    docs_path varchar(200),
    config_uxon text,
    action_app_oid uuid NOT NULL,
    use_in_object_basket_flag smallint NOT NULL DEFAULT 0,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_object_action_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_object_action_alias_unique UNIQUE (action_app_oid, alias)
);

CREATE INDEX exf_object_action_object_oid_idx ON exf_object_action (object_oid);

-- Table exf_object_behaviors
CREATE TABLE IF NOT EXISTS exf_object_behaviors (
    oid uuid NOT NULL,
    object_oid uuid NOT NULL,
    name varchar(100) NOT NULL,
    behavior varchar(256) NOT NULL,
    behavior_app_oid uuid NOT NULL,
    config_uxon text,
    description text,
    priority int,
    disabled_flag smallint NOT NULL DEFAULT 0,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_object_behaviors_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_object_behaviors_ix_object ON exf_object_behaviors (object_oid);

-- Table exf_page
CREATE TABLE IF NOT EXISTS exf_page (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    app_oid uuid,
    page_template_oid uuid,
    name varchar(50) NOT NULL,
    alias varchar(100),
    description varchar(200),
    intro varchar(200),
    content text,
    parent_oid uuid,
    menu_index int NOT NULL DEFAULT 0,
    menu_visible smallint NOT NULL DEFAULT 1,
    menu_home smallint NOT NULL DEFAULT 0,
    default_menu_parent_alias varchar(100),
    default_menu_parent_oid uuid,
    default_menu_index int,
    replace_page_oid uuid,
    auto_update_with_app smallint NOT NULL DEFAULT 1,
    published smallint NOT NULL DEFAULT 0,
    icon text,
    icon_set varchar(100),
    CONSTRAINT exf_page_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_page_alias_unique UNIQUE (alias)
);

CREATE INDEX exf_page_menu_parent_index_visible_idx ON exf_page (parent_oid, menu_index, menu_visible);

-- Table exf_page_group
CREATE TABLE IF NOT EXISTS exf_page_group (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(50) NOT NULL,
    descr varchar(200),
    app_oid uuid,
    CONSTRAINT exf_page_group_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_page_group_name_unique UNIQUE (name, app_oid)
);

-- Table exf_page_group_pages
CREATE TABLE IF NOT EXISTS exf_page_group_pages (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    page_oid uuid NOT NULL,
    page_group_oid uuid NOT NULL,
    app_oid uuid,
    CONSTRAINT exf_page_group_pages_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_page_group_pages_unique UNIQUE (page_oid, page_group_oid)
);

-- Table exf_page_template
CREATE TABLE IF NOT EXISTS exf_page_template (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    app_oid uuid,
    name varchar(50) NOT NULL,
    description varchar(200),
    facade_filepath varchar(100) NOT NULL,
    facade_uxon text,
    CONSTRAINT exf_page_template_pkey PRIMARY KEY (oid)
);

-- Table exf_permalink
CREATE TABLE IF NOT EXISTS exf_permalink (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    app_oid uuid,
    object_oid uuid,
    name varchar(50) NOT NULL,
    alias varchar(100),
    description varchar(200),
    prototype_file varchar(200),
    config_uxon text,
    CONSTRAINT exf_permalink_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_permalink_alias_unique UNIQUE (alias)
);

-- Table exf_permalink_slug
CREATE TABLE IF NOT EXISTS exf_permalink_slug (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    permalink_oid uuid NOT NULL,
    slug varchar(200) NOT NULL,
    data_uxon text NOT NULL,
    CONSTRAINT exf_permalink_slug_pkey PRIMARY KEY (oid),
    CONSTRAINT uq_exf_permalink_slug_slug UNIQUE (permalink_oid, slug)
);

-- Table exf_proxy_route
CREATE TABLE IF NOT EXISTS exf_proxy_route (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(100) NOT NULL,
    alias varchar(100) NOT NULL,
    app_oid uuid,
    description text,
    route_url varchar(400) NOT NULL,
    route_regex_flag smallint NOT NULL DEFAULT 0,
    destination_url varchar(400) NOT NULL,
    destination_connection uuid,
    handler_class varchar(400),
    handler_uxon text,
    CONSTRAINT exf_proxy_route_pkey PRIMARY KEY (oid)
);

-- Table exf_pwa
CREATE TABLE IF NOT EXISTS exf_pwa (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(100) NOT NULL,
    description varchar(400),
    icon_uri varchar(100),
    start_page_oid uuid NOT NULL,
    page_template_oid uuid NOT NULL,
    alias varchar(100) NOT NULL,
    app_oid uuid,
    url varchar(100) NOT NULL,
    active_flag smallint NOT NULL DEFAULT 1,
    installable_flag smallint NOT NULL DEFAULT 1,
    available_offline_flag smallint NOT NULL DEFAULT 1,
    available_offline_help_flag smallint NOT NULL DEFAULT 0,
    available_offline_unpublished_flag smallint NOT NULL DEFAULT 0,
    generated_on timestamp,
    regenerate_after timestamp,
    version_no varchar(10),
    version_build varchar(20),
    CONSTRAINT exf_pwa_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_pwa_app_alias_unique UNIQUE (alias, app_oid)
);

-- Table exf_pwa_action
CREATE TABLE IF NOT EXISTS exf_pwa_action (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    pwa_oid uuid NOT NULL,
    description varchar(400) NOT NULL,
    action_alias varchar(100) NOT NULL,
    object_oid uuid,
    object_action_oid uuid,
    offline_strategy_in_facade varchar(20) NOT NULL,
    offline_strategy_in_model varchar(20),
    page_oid uuid NOT NULL,
    trigger_widget_id text NOT NULL,
    trigger_widget_hash varchar(32) GENERATED ALWAYS AS (md5(trigger_widget_id)) STORED,
    trigger_widget_type varchar(100) NOT NULL,
    pwa_dataset_oid uuid,
    CONSTRAINT exf_pwa_action_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_pwa_action_unique UNIQUE (pwa_oid, action_alias, page_oid, trigger_widget_hash)
);

-- Table exf_pwa_build
CREATE TABLE IF NOT EXISTS exf_pwa_build (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    pwa_oid uuid NOT NULL,
    filename varchar(100) NOT NULL,
    size int NOT NULL,
    content text NOT NULL,
    mimetype varchar(100) NOT NULL,
    username varchar(100),
    version varchar(50),
    CONSTRAINT exf_pwa_build_pkey PRIMARY KEY (oid)
);

-- Table exf_pwa_dataset
CREATE TABLE IF NOT EXISTS exf_pwa_dataset (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    pwa_oid uuid NOT NULL,
    object_oid uuid NOT NULL,
    description varchar(400) NOT NULL,
    data_sheet_uxon text NOT NULL,
    user_defined_flag smallint NOT NULL DEFAULT 1,
    rows_at_generation_time int,
    offline_strategy_in_model varchar(20),
    data_set_uxon text NOT NULL,
    incremental_flag smallint NOT NULL,
    incremental_columns int,
    columns int,
    CONSTRAINT exf_pwa_dataset_pkey PRIMARY KEY (oid)
);

-- Table exf_pwa_route
CREATE TABLE IF NOT EXISTS exf_pwa_route (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    pwa_oid uuid NOT NULL,
    pwa_action_oid uuid,
    url text NOT NULL,
    url_hash varchar(32) GENERATED ALWAYS AS (md5(url)) STORED,
    description varchar(400) NOT NULL,
    user_defined_flag smallint NOT NULL DEFAULT 1,
    CONSTRAINT exf_pwa_route_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_pwa_route_url_hash_unique UNIQUE (pwa_oid, url_hash)
);

-- Table exf_queue
CREATE TABLE IF NOT EXISTS exf_queue (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    alias varchar(50) NOT NULL,
    name varchar(50) NOT NULL,
    prototype_class varchar(200) NOT NULL,
    description varchar(400) NOT NULL DEFAULT '',
    app_oid uuid,
    allow_multi_queue_handling smallint NOT NULL DEFAULT 0,
    config_uxon text,
    CONSTRAINT exf_queue_pkey PRIMARY KEY (oid)
);

-- Table exf_queued_task
CREATE TABLE IF NOT EXISTS exf_queued_task (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    producer varchar(50) NOT NULL,
    message_id varchar(50),
    channel varchar(50),
    task_assigned_on timestamp NOT NULL,
    task_uxon text NOT NULL,
    owner_oid uuid NOT NULL,
    status int NOT NULL,
    topics varchar(500),
    user_agent varchar(500),
    result_code int,
    result text,
    error_message text,
    error_logid varchar(20),
    parent_item_oid uuid,
    queue_oid uuid,
    processed_on timestamp,
    duration_ms numeric(10,2),
    scheduler_oid uuid,
    action_alias varchar(100),
    object_alias varchar(100),
    CONSTRAINT exf_queued_task_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_queued_task_find_duplicates_idx ON exf_queued_task (message_id, producer, queue_oid, status);
CREATE INDEX exf_queued_task_scheduler_idx ON exf_queued_task (scheduler_oid, created_on);
CREATE INDEX exf_queued_task_initial_views_idx ON exf_queued_task (created_on, task_assigned_on, owner_oid, queue_oid);

-- Table exf_scheduler
CREATE TABLE IF NOT EXISTS exf_scheduler (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    name varchar(50) NOT NULL,
    schedule varchar(50) NOT NULL,
    description varchar(400),
    action_uxon text,
    task_uxon text,
    app_oid uuid,
    queue_topics varchar(50) NOT NULL,
    first_run timestamp NOT NULL,
    last_run timestamp,
    CONSTRAINT exf_scheduler_pkey PRIMARY KEY (oid)
);

-- Table exf_support_log
CREATE TABLE IF NOT EXISTS exf_support_log (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    applied_on timestamp NOT NULL,
    title varchar(100) NOT NULL,
    description text,
    ticket_no varchar(50),
    CONSTRAINT exf_support_log_pkey PRIMARY KEY (oid)
);

-- Table exf_user
CREATE TABLE IF NOT EXISTS exf_user (
    oid uuid NOT NULL,
    first_name varchar(64),
    last_name varchar(64),
    username varchar(60) NOT NULL,
    password varchar(300),
    locale varchar(20) NOT NULL,
    email varchar(100),
    disabled_communication_flag smallint NOT NULL DEFAULT 0,
    comments text,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    company varchar(200),
    position varchar(200),
    disable_date timestamp,
    CONSTRAINT exf_user_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_user_username_unique UNIQUE (username)
);

-- Table exf_user_api_key
CREATE TABLE IF NOT EXISTS exf_user_api_key (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    user_oid uuid NOT NULL,
    key_hash varchar(300) NOT NULL,
    name varchar(100) NOT NULL,
    expires timestamp,
    CONSTRAINT exf_user_api_key_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_user_api_key_key_unique UNIQUE (key_hash)
);

-- Table exf_user_authenticator
CREATE TABLE IF NOT EXISTS exf_user_authenticator (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    authenticator_id varchar(100) NOT NULL,
    user_oid uuid NOT NULL,
    authenticator_username varchar(100) NOT NULL DEFAULT '',
    disabled_flag int NOT NULL DEFAULT 0,
    last_authenticated_on timestamp,
    properties_uxon text,
    CONSTRAINT exf_user_authenticator_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_user_authenticator_username_unique UNIQUE (authenticator_username, authenticator_id)
);

-- Table exf_user_credentials
CREATE TABLE IF NOT EXISTS exf_user_credentials (
    oid uuid NOT NULL,
    user_oid uuid NOT NULL,
    data_connection_credentials_oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_user_credentials_pkey PRIMARY KEY (oid)
);

-- Table exf_user_role
CREATE TABLE IF NOT EXISTS exf_user_role (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(50) NOT NULL,
    alias varchar(50) NOT NULL,
    descr varchar(200),
    app_oid uuid,
    sync_with_external_role_oid uuid,
    start_page_oid uuid,
    CONSTRAINT exf_user_role_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_user_role_unique_app_alias UNIQUE (app_oid, alias)
);

-- Table exf_user_role_external
CREATE TABLE IF NOT EXISTS exf_user_role_external (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    name varchar(50) NOT NULL,
    alias varchar(50) NOT NULL,
    user_role_oid uuid,
    authenticator_id varchar(100) NOT NULL,
    active_flag smallint NOT NULL DEFAULT 1,
    keep_manual_assignments_flag smallint,
    CONSTRAINT exf_user_role_external_pkey PRIMARY KEY (oid)
);

-- Table exf_user_role_users
CREATE TABLE IF NOT EXISTS exf_user_role_users (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    user_role_oid uuid NOT NULL,
    user_oid uuid NOT NULL,
    authenticator_id varchar(100),
    CONSTRAINT exf_user_role_users_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_user_role_users_unique UNIQUE (user_oid, user_role_oid)
);

-- Table exf_uxon_preset
CREATE TABLE IF NOT EXISTS exf_uxon_preset (
    oid uuid NOT NULL,
    app_oid uuid,
    name varchar(250) NOT NULL,
    description text,
    uxon text NOT NULL,
    wrap_path_in_preset varchar(255),
    prototype varchar(200),
    uxon_schema varchar(100),
    thumbnail varchar(250),
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    CONSTRAINT exf_uxon_preset_pkey PRIMARY KEY (oid)
);

CREATE INDEX exf_uxon_preset_prototype_idx ON exf_uxon_preset (prototype);

-- Table exf_uxon_snippet
CREATE TABLE IF NOT EXISTS exf_uxon_snippet (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid,
    modified_by_user_oid uuid,
    object_oid uuid,
    name varchar(128) NOT NULL,
    app_oid uuid NOT NULL,
    alias varchar(128) NOT NULL,
    description text,
    uxon text NOT NULL,
    uxon_schema varchar(200),
    prototype varchar(200) NOT NULL,
    CONSTRAINT exf_uxon_snippet_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_uxon_snippet_alias_unique UNIQUE (app_oid, alias)
);

CREATE INDEX exf_uxon_snippet_object_oid_idx ON exf_uxon_snippet (object_oid);

-- Table exf_widget_setup
CREATE TABLE IF NOT EXISTS exf_widget_setup (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    name varchar(100) NOT NULL,
    description varchar(200),
    app_oid uuid,
    page_oid uuid NOT NULL,
    object_oid uuid,
    widget_id varchar(2000) NOT NULL,
    prototype_file varchar(200) NOT NULL,
    setup_uxon text NOT NULL,
    private_for_user_oid uuid,
    CONSTRAINT exf_widget_setup_pkey PRIMARY KEY (oid)
);

CREATE INDEX fk_widget_setup_app_idx ON exf_widget_setup (app_oid);
CREATE INDEX fk_widget_setup_page_idx ON exf_widget_setup (page_oid);
CREATE INDEX fk_widget_setup_user_idx ON exf_widget_setup (private_for_user_oid);
CREATE INDEX exf_widget_setup_ix_page_user ON exf_widget_setup (page_oid, private_for_user_oid);

-- Table exf_widget_setup_user
CREATE TABLE IF NOT EXISTS exf_widget_setup_user (
    oid uuid NOT NULL,
    created_on timestamp NOT NULL,
    modified_on timestamp NOT NULL,
    created_by_user_oid uuid NOT NULL,
    modified_by_user_oid uuid NOT NULL,
    user_oid uuid NOT NULL,
    widget_setup_oid uuid NOT NULL,
    favorite_flag smallint NOT NULL DEFAULT 0,
    default_setup_flag smallint NOT NULL DEFAULT 0,
    CONSTRAINT exf_widget_setup_user_pkey PRIMARY KEY (oid),
    CONSTRAINT exf_widget_setup_user_unique UNIQUE (user_oid, widget_setup_oid)
);

CREATE INDEX fk_widget_setup_user_setup_idx ON exf_widget_setup_user (widget_setup_oid);


ALTER TABLE exf_announcement
    ADD CONSTRAINT exf_announcement_ibfk_1
        FOREIGN KEY (communication_template_oid)
            REFERENCES exf_communication_template (oid) ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE exf_attribute_group
    ADD CONSTRAINT exf_attribute_group_ibfk_2
        FOREIGN KEY (app_oid) 
            REFERENCES exf_app (oid) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE exf_mutation_set
    ADD CONSTRAINT fk_mutation_set_app
        FOREIGN KEY (app_oid) 
            REFERENCES exf_app (oid) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE exf_mutation_target
    ADD CONSTRAINT fk_mutation_target_app
        FOREIGN KEY (app_oid) 
            REFERENCES exf_app (oid) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE exf_mutation_type
    ADD CONSTRAINT fk_mutation_type_app
        FOREIGN KEY (app_oid) 
            REFERENCES exf_app (oid) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_mutation_type_target 
        FOREIGN KEY (mutation_target_oid) 
            REFERENCES exf_mutation_target (oid) ON DELETE RESTRICT ON UPDATE RESTRICT;