<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Object;

interface ModelLoaderInterface {
	
	/**
	 * Fills the given object with model data (attributes, relations, etc.). 
	 * NOTE: The object must have an id or a qualified alias at this point!
	 * @param Object $object
	 */
	public function load_object(Object $object);
	
	/**
	 * Fills th given data source with model data (query builder, connection configuration, user credentials, etc.)
	 * @param DataSourceInterface $data_source
	 * @param string $data_connection_id_or_alias
	 * @return DataSourceInterface
	 */
	public function load_data_source(DataSourceInterface $data_source, $data_connection_id_or_alias = null);
	
	/**
	 * Returns the data connection, that is used to fetch model data
	 * @return DataConnectionInterface
	 */
	public function get_data_connection();
	
	/**
	 * Sets the data connection to fetch model data from
	 * @param DataConnectionInterface $connection
	 * @return ModelLoaderInterface
	 */
	public function set_data_connection(DataConnectionInterface &$connection);
	
}
?>