<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\DataTransactionExceptionInterface;

/**
 * Exception thrown in case of errors in the internal cross-datasourc data transaction. 
 * 
 * It is the base class for more specific errors like
 * @see DataTransactionStartError
 * @see DataTransactionCommitError
 * @see DataTransactionRollbackError
 *
 * @author Andrej Kabachnik
 *
 */
class DataTransactionError extends RuntimeException implements ErrorExceptionInterface, DataTransactionExceptionInterface {
	
}
?>