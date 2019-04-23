UPDATE exf_data_connection SET data_connector= REPLACE(data_connector,'exface/SqlDataConnector/DataConnectors/MySQL.php','exface/Core/DataConnectors/MySqlConnector.php');
UPDATE exf_data_connection SET data_connector= REPLACE(data_connector,'exface/SqlDataConnector/DataConnectors/OracleSQL.php','exface/Core/DataConnectors/OracleSqlConnector.php');
UPDATE exf_data_connection SET data_connector= REPLACE(data_connector,'exface/SqlDataConnector/DataConnectors/MsSQL.php','exface/Core/DataConnectors/MsSqlConnector.php');
UPDATE exf_data_connection SET data_connector= REPLACE(data_connector,'exface/SqlDataConnector/DataConnectors/SapHanaSqlConnector.php','exface/Core/DataConnectors/SapHanaSqlConnector.php');

UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/SqlDataConnector/QueryBuilders/MySQL.php','exface/Core/QueryBuilders/MySqlBuilder.php');
UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/SqlDataConnector/QueryBuilders/MsSQL.php','exface/Core/QueryBuilders/MsSqlBuilder.php');
UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/SqlDataConnector/QueryBuilders/OracleSQL.php','exface/Core/QueryBuilders/OracleSqlBuilder.php');
UPDATE exf_data_source SET default_query_builder= REPLACE(default_query_builder,'exface/SqlDataConnector/QueryBuilders/SapHanaSqlBuilder.php','exface/Core/QueryBuilders/SapHanaSqlBuilder.php');

UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/SqlDataConnector/QueryBuilders/MySQL.php','exface/Core/QueryBuilders/MySqlBuilder.php');
UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/SqlDataConnector/QueryBuilders/MsSQL.php','exface/Core/QueryBuilders/MsSqlBuilder.php');
UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/SqlDataConnector/QueryBuilders/OracleSQL.php','exface/Core/QueryBuilders/OracleSqlBuilder.php');
UPDATE exf_data_source SET custom_query_builder= REPLACE(custom_query_builder,'exface/SqlDataConnector/QueryBuilders/SapHanaSqlBuilder.php','exface/Core/QueryBuilders/SapHanaSqlBuilder.php');