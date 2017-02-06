<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;

/**
 * Exception thrown if the configuration of a data connector is invalid or missing. Normally this indicates, 
 * that the data connector properties in the meta model are incorrect.
 * 
 * This exception should be thrown by setters of data connectors, dealing with configuration options (i.e. username,
 * password, URL to the data source, etc.)
 *
 * @author Andrej Kabachnik
 *
 */
class DataConnectionConfigurationError extends UnexpectedValueException implements DataConnectorExceptionInterface {
	
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