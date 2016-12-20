<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Exceptions\UnexpectedValueException;

class DataConnectionConfigurationError extends UnexpectedValueException implements ErrorExceptionInterface, DataConnectorExceptionInterface {
	
	use DataConnectorExceptionTrait;
	
}
?>