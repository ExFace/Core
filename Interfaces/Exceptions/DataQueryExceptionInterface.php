<?php namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSources\DataQueryInterface;

interface DataQueryExceptionInterface extends ExceptionInterface {
	
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
