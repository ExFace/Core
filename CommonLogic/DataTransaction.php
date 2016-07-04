<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Exceptions\DataTransactionError;
use exface\Core\Exceptions\DataConnectionError;

class DataTransaction implements DataTransactionInterface {
	private $data_manager = null;
	private $connections = array();
	private $is_started = false;
	private $is_committed = false;
	private $is_rolled_back = false;
	
	public function __construct(DataManagerInterface $manager){
		$this->data_manager = $manager;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::get_data_manager()
	 */
	public function get_data_manager(){
		return $this->data_manager;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::start()
	 */
	public function start(){
		$this->is_started = true;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::commit()
	 */
	public function commit(){
		if ($this->is_rolled_back()){
			throw new DataTransactionError('Cannot commit a transaction, that has already been rolled back!');
		}
		
		foreach ($this->get_data_connections() as $connection){
			try {
				$connection->transaction_commit();
				$this->is_committed = true;
			} catch (DataConnectionError $e){
				$this->rollback();
			}
		}
		
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::rollback()
	 */
	public function rollback(){
		if ($this->is_committed()){
			throw new DataTransactionError('Cannot roll back a transaction, that has already been committed!');
		}
		
		foreach ($this->get_data_connections() as $connection){
			try {
				$connection->transaction_rollback();
				$this->is_rolled_back = true;
			} catch (DataConnectionError $e){
				throw new DataTransactionError('Cannot rollback transaction for "' . $connection->get_alias_with_namespace() . '":' . $e->getMessage());
			}
			
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::is_started()
	 */
	public function is_started(){
		return $this->is_started;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::is_rolled_back()
	 */
	public function is_rolled_back(){
		return $this->is_rolled_back;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::is_committed()
	 */
	public function is_committed(){
		return $this->is_committed;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::add_data_connection()
	 */
	public function add_data_connection(DataConnectionInterface $connection){
		if (!$this->is_started()){
			$this->start();
		}
		
		// Start a transaction in the data connection
		foreach ($this->get_data_connections() as $connection){
			if ($connection->transaction_is_started()){
				throw new DataTransactionError('Cannot start new transaction for "' . $connection->get_alias_with_namespace() . '": a transaction is already open!');
			} else {
				try {
					$connection->transaction_start();
				} catch (DataConnectionError $e){
					throw new DataTransactionError('Cannot start new transaction for "' . $connection->get_alias_with_namespace() . '":' . $e->getMessage());
				}
			}
		}
		
		$this->connections[] = $connection;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::get_data_connections()
	 */
	public function get_data_connections(){
		return $this->connections;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::exface()
	 */
	public function exface(){
		return $this->get_data_manager()->exface();
	}
	
}