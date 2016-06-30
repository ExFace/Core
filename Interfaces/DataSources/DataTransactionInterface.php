<?php namespace exface\Core\Interfaces\DataSources;

use exface\exface;
use exface\Core\Interfaces\ExfaceClassInterface;

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
	 * @return DataTransactionInterface
	 */
	public function commit();
	
	/**
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
	 * @param DataConnectionInterface $connection
	 * @return DataTransactionInterface
	 */
	public function add_data_connection(DataConnectionInterface $connection);
	
	/**
	 * @return DataConnectionInterface[]
	 */
	public function get_data_connections();
	
}
?>