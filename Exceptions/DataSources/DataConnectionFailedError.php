<?php
namespace exface\Core\Exceptions\DataSources;

/**
 * Exception thrown a data connector cannot establish a connection (e.g. if an SQL-connector fails to connect to the database).
 * 
 * It is advisable to wrap this exception around any data source specific exceptions to enable the plattform, to
 * understand what's going without having to deal with data source specific exception types.
 *
 * @author Andrej Kabachnik
 *
 */
class DataConnectionFailedError extends DataConnectorError {
	
	public static function get_default_alias(){
		return '6T5VG46';
	}
	
}
?>