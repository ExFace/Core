<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\ExfaceClassInterface;

/**
 * DataTranscactions represent atomic data operations on the level of DataSheets. Since a DataSheet can contain data from multiple
 * sources, one DataTransaction can include multiple DataSource-specific transactions. Thus if you write to two different databases,
 * there will be a transaction per database plus the overall DataTransaction, which you would work with in ExFace. If something goes
 * wrong, rolling back the DataTransaction will lead to rollbacks in all physical transactions.
 * 
 * Since most data sources only allow one transaction to be opened at a time for every connection, a DataTransaction needs to know,
 * what DataConnections are involved in it's operations. Regardless of the nature of those operations, every time a new DataConnection
 * is added, a new physical transaction is started there. Commits and rollbacks are preformed on every assotiated DataConnection. 
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataTransactionInterface extends ExfaceClassInterface {
	
	/**
	 * @return DataManagerInterface
	 */
	public function get_data_manager();
	
	/**
	 * @return DataTransactionInterface
	 */
	public function start();
	
	/**
	 * Physically commits transactions in all data sources assotiated with this transaction
	 * @return DataTransactionInterface
	 */
	public function commit();
	
	/**
	 * Physically rolls back transactions in all data sources assotiated with this transaction
	 * @return DataTransactionInterface
	 */
	public function rollback();
	
	/**
	 * @return boolean
	 */
	public function is_started();
	
	/**
	 * @return boolean
	 */
	public function is_rolled_back();
	
	/**
	 * @return boolean
	 */
	public function is_committed();
	
	/**
	 * Assotiates this transaction with a given connection starting a physical transaction there. Adding the same connection
	 * multiple times is no problem - there will still be only one physical transaction there.
	 * @param DataConnectionInterface $connection
	 * @return DataTransactionInterface
	 */
	public function add_data_connection(DataConnectionInterface $connection);
	
	/**
	 * Returns all connections assotiated with this transaction
	 * @return DataConnectionInterface[]
	 */
	public function get_data_connections();
	
}
?>