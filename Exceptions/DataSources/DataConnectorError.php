<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;

class DataConnectorError extends RuntimeException implements ErrorExceptionInterface, DataConnectorExceptionInterface {
	
	use DataConnectorExceptionTrait;
	
	/**
	 *
	 * @param DataConnectionInterface $connector
	 * @param string $message
	 * @param string $code
	 * @param \Throwable $previous
	 */
	public function __construct (DataConnectionInterface $connector, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_connector($connector);
	}
	
}
?>