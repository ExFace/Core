<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Exceptions\DataConnectionError;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

interface DataConnectionInterface extends ExfaceClassInterface, AliasInterface, iCanBeConvertedToUxon {
	
	/**
	 * Connects to the data source using the configuration array passed to the constructor of the connector
	 * 
	 * @return void
	 */
	public function connect();
	
	/**
	 * Closes the connection to the data source
	 * 
	 * @return void
	 */
	public function disconnect();
	
	/**
	 * Queries the data source using the passed query object (presumably build by a suitable query builder) and returns 
	 * a query object containing the result in addition to the query. The form in which the result is stored depends
	 * on the specific implementation - it must be readable by compatible query builders but apart from that it can be
	 * anything. 
	 * 
	 * @param DataQueryInterface $query_string
	 * @return DataQueryInterface
	 */
	public function query(DataQueryInterface $query);
	
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