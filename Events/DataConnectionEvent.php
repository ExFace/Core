<?php namespace exface\Core\Events;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\NameResolver;

/**
 * Action sheet event names consist of the alias of the connector followed by "DataConnection" and the respective event type:
 * e.g. exface.sqlDataConnector.DataConnectors.MySQL.DataConnection.Before, etc.
 * @author aka
 *
 */
class DataConnectionEvent extends ExFaceEvent {
	private $data_connection = null;
	private $current_query = null;
	
	/**
	 * @return DataConnectionInterface
	 */
	public function get_data_connection() {
		return $this->data_connection;
	}
	
	/**
	 * 
	 * @param DataConnectionInterface $connection
	 */
	public function set_data_connection(DataConnectionInterface &$connection) {
		$this->data_connection = $connection;
		return $this;
	} 
	
	/**
	 * @return string
	 */
	public function get_current_query() {
		return $this->current_query;
	}
	
	/**
	 * 
	 * @param string $value
	 */
	public function set_current_query($value) {
		$this->current_query = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Events\ExFaceEvent::get_namespace()
	 */
	public function get_namespace(){
		return $this->get_data_connection()->get_alias_with_namespace() . NameResolver::NAMESPACE_SEPARATOR . 'DataConnection';
	}
  
}