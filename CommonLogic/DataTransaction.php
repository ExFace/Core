<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Exceptions\DataTransactionError;
use exface\Core\Exceptions\DataConnectionError;
use exface\Core\Exceptions\DataSources\DataTransactionCommitError;
use exface\Core\Exceptions\DataSources\DataTransactionRollbackError;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSources\DataTransactionStartError;

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
			throw new DataTransactionCommitError('Cannot commit a transaction, that has already been rolled back!', '6T5VIIA');
		}
		
		foreach ($this->get_data_connections() as $connection){
			try {
				$connection->transaction_commit();
				$this->is_committed = true;
			} catch (ErrorExceptionInterface $e){
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
			throw new DataTransactionRollbackError('Cannot roll back a transaction, that has already been committed!', '6T5VIT8');
		}
		
		foreach ($this->get_data_connections() as $connection){
			try {
				$connection->transaction_rollback();
				$this->is_rolled_back = true;
			} catch (ErrorExceptionInterface $e){
				throw new DataTransactionRollbackError('Cannot rollback transaction for "' . $connection->get_alias_with_namespace() . '":' . $e->getMessage());
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
		
		// See if the connection is already registered in this transaction
		foreach ($this->get_data_connections() as $existing_connection){
			if ($existing_connection == $connection){
				
			} else {
				$existing_connection = null;
			}
		}
		
		// If this is a new connection, start a transaction there and add it to this DataTransaction.
		// Otherwise make sure, there is a transaction started in the existing connection.
		if (!$existing_connection){
			try {
				$connection->transaction_start();
			} catch (ErrorExceptionInterface $e){
				throw new DataTransactionStartError('Cannot start new transaction for "' . $connection->get_alias_with_namespace() . '":' . $e->getMessage());
			}
			$this->connections[] = $connection;
		} elseif (!$existing_connection->transaction_is_started()){
			try {
				$existing_connection->transaction_start();
			} catch (ErrorExceptionInterface $e){
				throw new DataTransactionStartError('Cannot start new transaction for "' . $connection->get_alias_with_namespace() . '":' . $e->getMessage());
			}
		}
		
		
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
	public function get_workbench(){
		return $this->get_data_manager()->get_workbench();
	}
	
}