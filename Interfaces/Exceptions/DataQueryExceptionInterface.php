<?php namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataQueryInterface;

interface DataQueryExceptionInterface extends DataSourceExceptionInterface {
	
	/**
	 * 
	 * @param DataQueryInterface $query
	 * @param string $message
	 * @param string $code
	 * @param string $previous
	 */
	public function __construct (DataQueryInterface $query, $message, $code, $previous = null);
	
	/**
	 * 
	 * @return DataQueryInterface
	 */
	public function get_query();
	
	/**
	 * 
	 * @param DataQueryInterface $query
	 */
	public function set_query(DataQueryInterface $query);
}
