<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Exceptions\DataConnectionError;

interface DataConnectionInterface extends ExfaceClassInterface, AliasInterface {
	
	/**
	 * Connects to the data source using the configuration array passed to the constructor of the connector
	 */
	public function connect();
	
	/**
	 * Closes the connection to the data source
	 */
	public function disconnect();
	
	/**
	 * Queries the data source using the passed query (presumably build by a suitable query builder) and returns the result in whatever form, that can be interpreted
	 * by the query builder. The recommended return format is an assotiative array.
	 * @param string $query_string
	 */
	public function query($query_string);
	
	/**
	 * Returns the id of the data row inserted by the last query
	 * @return string
	 */
	public function get_insert_id();
	
	/**
	 * Returns the number of rows affected by the last query
	 * @return int
	 */
	public function get_affected_rows_count();
	
	/**
	 * Returns the last error
	 * @return string
	 */
	public function get_last_error();
	
	/**
	 * TODO replace array by UxonObject
	 * @return array
	 */
	public function get_config_array();
	
	/**
	 * @param string $key
	 * @return mixed
	 */
	public function get_config_value($key);
	
	/**
	 * TODO replace array by UxonObject
	 * @param array $array
	 */
	public function set_config_array(array $array);
	
	/**
	 * Starts a new transaction in the data source.
	 * @throws DataConnectionError if no transaction could be started
	 * @return DataConnectionInterface
	 */
	public function transaction_start();
	
	/**
	 * Commits the current transaction in the data source. Returns TRUE on success and FALSE otherwise.
	 * @throws DataConnectionError if the transaction cannot be committed
	 * @return DataConnectionInterface
	 */
	public function transaction_commit();
	
	/**
	 * Rolls back the current transaction in the data source.
	 * @throws DataConnectionError if the transaction cannot be rolled back
	 * @return DataConnectionInterface
	 */
	public function transaction_rollback();
	
	/**
	 * Returns true if a transaction is currently open
	 * @return boolean
	 */
	public function transaction_is_started();
	
}
?>