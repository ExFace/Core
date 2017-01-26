<?php namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * This simple data connector merely returns the given query right back while being fully compilant to all data connector specs.
 * 
 * It does not have any configuration and actually does nothing. This connector is usefull for data sources, where the query or
 * the query builder can do everything themselves without needing a connection manager, credentials, or anything else.
 * 
 * @author Andrej Kabachnik
 *
 */
class TransparentConnector extends AbstractDataConnectorWithoutTransactions {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_query()
	 * @return \SplFileInfo[]
	 */
	protected function perform_query(DataQueryInterface $query) {
		return $query;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_connect()
	 */
	protected function perform_connect() {
		return;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_disconnect()
	 */
	protected function perform_disconnect() {
		return;
	}
	 
}
?>