-- UP

INSERT INTO `exf_data_connection` (`oid`, `alias`, `app_oid`, `name`, `data_connector`, `data_connector_config`, `read_only_flag`, `filter_context_uxon`, `created_on`, `modified_on`, `created_by_user_oid`, `modified_by_user_oid`) VALUES
(0x11ea72c00f0fadeca3480205857feb80, 'METAMODEL_CONNECTION', 0x31000000000000000000000000000000, 'Metamodel Connection', 'exface/Core/DataConnectors/ModelLoaderConnector.php', NULL, 0, NULL, '2020-03-30 19:53:02', '2020-03-30 19:53:02', 0x31000000000000000000000000000000, 0x31000000000000000000000000000000);

UPDATE exf_data_source 
SET 
	custom_connection_oid = NULL, 
	default_connection_oid = 0x11ea72c00f0fadeca3480205857feb80 
WHERE oid = 0x32000000000000000000000000000000;

-- DOWN

DELETE from `exf_data_connection` WHERE `oid` = 0x11ea72c00f0fadeca3480205857feb80;

UPDATE exf_data_source SET 
	default_connection_oid = NULL, 
	custom_connection_oid = (
		SELECT oid FROM exf_data_connection WHERE alias = 'CORE_MODEL_CONNECTION'
	)
WHERE oid = 0x32000000000000000000000000000000;