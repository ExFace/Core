<?php namespace exface\Core;

use exface\Core\exface;

abstract class AbstractDataConnectorWithoutTransactions extends AbstractDataConnector {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\AbstractDataConnector::transaction_start()
	 */
	public function transaction_start(){
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\AbstractDataConnector::transaction_commit()
	 */
	public function transaction_commit(){
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\AbstractDataConnector::transaction_rollback()
	 */
	public function transaction_rollback(){
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\AbstractDataConnector::transaction_is_started()
	 */
	public function transaction_is_started(){
		return false;
	}
	
}