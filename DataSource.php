<?php namespace exface\Core;

use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Model\Model;

class DataSource implements DataSourceInterface {
	private $model;
	private $data_connector;
	private $connection_id;
	private $query_builder;
	private $data_source_id;
	private $connection_config = array();
	private $read_only = false;
	
	function __construct(Model &$model){
		$this->model = $model;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::get_model()
	 */
	public function get_model() {
		return $this->model;
	}
	
	public function exface(){
		return $this->get_model()->exface();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::get_connection()
	 */
	public function get_connection() {
		return $this->exface()->data()->get_data_connection($this->get_id(), $this->get_connection_id());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::get_id()
	 */
	public function get_id() {
	  return $this->data_source_id;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::set_id()
	 */
	public function set_id($value) {
	  $this->data_source_id = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::get_data_connector_alias()
	 */
	public function get_data_connector_alias() {
	  return $this->data_connector;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::set_data_connector_alias()
	 */
	public function set_data_connector_alias($value) {
	  $this->data_connector = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::get_connection_id()
	 */
	public function get_connection_id() {
	  return $this->connection_id;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::set_connection_id()
	 */
	public function set_connection_id($value) {
	  $this->connection_id = intval($value);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::get_query_builder_alias()
	 */
	public function get_query_builder_alias() {
	  return $this->query_builder;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::set_query_builder_alias()
	 */
	public function set_query_builder_alias($value) {
		$this->query_builder = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::get_connection_config()
	 */
	public function get_connection_config() {
	  return is_array($this->connection_config) ? $this->connection_config : array();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::set_connection_config()
	 */
	public function set_connection_config($value) {
	  $this->connection_config = $value;
	} 
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::is_read_only()
	 */
	public function is_read_only() {
		return $this->read_only;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSources\DataSourceInterface::set_read_only()
	 */
	public function set_read_only($value) {
		$this->read_only = $value;
		return $this;
	}  
}
?>