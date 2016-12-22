<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\DataTransactionExceptionInterface;

class DataTransactionError extends RuntimeException implements ErrorExceptionInterface, DataTransactionExceptionInterface {
	
}
?>