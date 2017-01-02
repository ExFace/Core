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
	 * @param string $alias
	 * @param \Throwable $previous
	 */
	public function __construct (DataConnectionInterface $connector, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_connector($connector);
	}
	
}
?>