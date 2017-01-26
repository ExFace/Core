<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Exceptions\UnexpectedValueException;

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
class DataConnectionConfigurationError extends UnexpectedValueException implements ErrorExceptionInterface, DataConnectorExceptionInterface {
	
	use DataConnectorExceptionTrait;
	
}
?>