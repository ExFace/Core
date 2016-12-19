<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Exceptions\BadMethodCallException;

class DataConnectionConfigurationError extends BadMethodCallException implements ErrorExceptionInterface, DataConnectorExceptionInterface {
	
	use DataConnectorExceptionTrait;
	
}
?>