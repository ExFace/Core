<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;

class DataQueryFailedError extends \RuntimeException implements ErrorExceptionInterface, DataQueryExceptionInterface {
	
	use DataQueryExceptionTrait;
	
}
?>