<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\exface;
use exface\Core\Model\Model;
use exface\Core\Interfaces\ExfaceClassInterface;

interface DataSourceInterface extends ExfaceClassInterface {
	
	/**
	 * @return DataConnectionInterface
	 */
	public function get_connection();
	
	/**
	 * @return string
	 */
	public function get_id();
	
	/**
	 * 
	 * @param string $value
	 */
	public function set_id($value);
	
	/**
	 * @return string
	 */
	public function get_data_connector_alias();
	
	/**
	 * 
	 * @param string $value
	 */
	public function set_data_connector_alias($value);
	
	/**
	 * @return string
	 */
	public function get_connection_id();
	
	/**
	 * @param string $value
	 */
	public function set_connection_id($value);
	
	/**
	 * @return string
	 */
	public function get_query_builder_alias();
	
	/**
	 * @param string $value
	 */
	public function set_query_builder_alias($value);
	
	/**
	 * Returns an assotiative array with configuration options for this connections (e.g. [user => user_value, password => password_value, ...]
	 * @return array
	 */
	public function get_connection_config();
	
	/**
	 * 
	 * @param string $value
	 */
	public function set_connection_config($value);
	
	/**
	 * Returns TRUE, if the data source or it's connection is marked as read only or FALSE otherwise.
	 * @return boolean
	 */
	public function is_read_only();
	
	/**
	 * Set to TRUE to mark this data source as read only.
	 * @param boolean $value
	 * @return DataSourceInterface
	 */
	public function set_read_only($value);
	
	/**
	 * @return Model
	 */
	public function get_model();
	
}
?>