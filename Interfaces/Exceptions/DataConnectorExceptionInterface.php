<?php namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

interface DataConnectorExceptionInterface extends DataSourceExceptionInterface {
	
	/**
	 *
	 * @param DataConnectionInterface $connector
	 * @param string $message
	 * @param string $code
	 * @param \Throwable $previous
	 */
	public function __construct (DataConnectionInterface $connector, $message, $code, $previous = null);
	
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
