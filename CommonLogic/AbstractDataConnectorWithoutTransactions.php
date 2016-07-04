<?php namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Workbench;

abstract class AbstractDataConnectorWithoutTransactions extends AbstractDataConnector {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_start()
	 */
	public function transaction_start(){
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_commit()
	 */
	public function transaction_commit(){
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_rollback()
	 */
	public function transaction_rollback(){
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_is_started()
	 */
	public function transaction_is_started(){
		return false;
	}
	
}