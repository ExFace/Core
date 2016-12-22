<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

class DataQueryFailedError extends RuntimeException implements ErrorExceptionInterface, DataQueryExceptionInterface {
	
	use DataQueryExceptionTrait;
	
	public function __construct (DataQueryInterface $query, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_query($query);
	}
	
}
?>