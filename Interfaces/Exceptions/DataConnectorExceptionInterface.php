<?php namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

interface DataConnectorExceptionInterface extends ExceptionInterface {
	
	/**
	 * 
	 * @return DataConnectionInterface
	 */
	public function get_connector();
	
	/**
	 * 
	 * @param DataQueryInterface $query
	 */
	public function set_connector(DataConnectionInterface $connector);
}
