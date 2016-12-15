<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;
use exface\Core\Exceptions\RuntimeException;

class DataQueryFailedError extends RuntimeException implements ErrorExceptionInterface, DataQueryExceptionInterface {
	
	use DataQueryExceptionTrait;
	
}
?>