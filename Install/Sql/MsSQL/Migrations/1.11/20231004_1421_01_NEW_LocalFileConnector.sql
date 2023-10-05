-- UP

UPDATE exf_data_connection SET data_connector = 'exface/Core/DataConnectors/LocalFileConnector.php' WHERE data_connector = 'exface/Core/DataConnectors/FileFinderConnector.php';
	
UPDATE exf_data_source SET default_query_builder = 'exface/Core/QueryBuilders/FileBuilder.php' WHERE default_query_builder = 'exface/Core/QueryBuilders/FileFinderBuilder.php';
UPDATE exf_data_source SET custom_query_builder = 'exface/Core/QueryBuilders/FileBuilder.php' WHERE custom_query_builder = 'exface/Core/QueryBuilders/FileFinderBuilder.php';

UPDATE exf_object SET data_address_properties = REPLACE(data_address_properties, 'finder_depth', 'folder_depth');
	
-- DOWN

UPDATE exf_data_connection SET data_connector = 'exface/Core/DataConnectors/FileFinderConnector.php' WHERE data_connector = 'exface/Core/DataConnectors/LocalFileConnector.php';
	
UPDATE exf_data_source SET default_query_builder = 'exface/Core/QueryBuilders/FileFinderBuilder.php' WHERE default_query_builder = 'exface/Core/QueryBuilders/FileBuilder.php';
UPDATE exf_data_source SET custom_query_builder = 'exface/Core/QueryBuilders/FileFinderBuilder.php' WHERE custom_query_builder = 'exface/Core/QueryBuilders/FileBuilder.php';

UPDATE exf_object SET data_address_properties = REPLACE(data_address_properties, 'folder_depth', 'finder_depth');