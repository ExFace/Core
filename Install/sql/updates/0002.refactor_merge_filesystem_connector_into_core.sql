UPDATE exf_data_connection SET data_connector= REPLACE(data_connector,'exface/FileSystemConnector/DataConnectors/FileContentsConnector.php','exface/Core/DataConnectors/FileContentsConnector.php');
UPDATE exf_data_connection SET data_connector= REPLACE(data_connector,'exface/FileSystemConnector/DataConnectors/FileFinderConnector.php','exface/Core/DataConnectors/FileFinderConnector.php');
UPDATE exf_data_connection SET data_connector= REPLACE(data_connector,'exface/FileSystemConnector/DataConnectors/PhpAnnotationsConnector.php','exface/Core/DataConnectors/PhpAnnotationsConnector.php');

UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/FileSystemConnector/QueryBuilders/CsvBuilder.php','exface/Core/QueryBuilders/CsvBuilder.php');
UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/FileSystemConnector/QueryBuilders/FileContentsBuilder.php','exface/Core/QueryBuilders/FileContentsBuilder.php');
UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/FileSystemConnector/QueryBuilders/FileFinderBuilder.php','exface/Core/QueryBuilders/FileFinderBuilder.php');
UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/FileSystemConnector/QueryBuilders/PhpAnnotationsReader.php','exface/Core/QueryBuilders/PhpAnnotationsReader.php');
UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/FileSystemConnector/QueryBuilders/PhpClassFinderBuilder.php','exface/Core/QueryBuilders/PhpClassFinderBuilder.php');

UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/FileSystemConnector/QueryBuilders/CsvBuilder.php','exface/Core/QueryBuilders/CsvBuilder.php');
UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/FileSystemConnector/QueryBuilders/FileContentsBuilder.php','exface/Core/QueryBuilders/FileContentsBuilder.php');
UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/FileSystemConnector/QueryBuilders/FileFinderBuilder.php','exface/Core/QueryBuilders/FileFinderBuilder.php');
UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/FileSystemConnector/QueryBuilders/PhpAnnotationsReader.php','exface/Core/QueryBuilders/PhpAnnotationsReader.php');
UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/FileSystemConnector/QueryBuilders/PhpClassFinderBuilder.php','exface/Core/QueryBuilders/PhpClassFinderBuilder.php');
