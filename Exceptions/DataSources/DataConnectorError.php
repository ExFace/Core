<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Exceptions\RuntimeException;

class DataConnectorError extends RuntimeException implements ErrorExceptionInterface, DataConnectorExceptionInterface {
	
	use DataConnectorExceptionTrait;
	
}
?>