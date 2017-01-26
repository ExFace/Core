<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if an error which can only be found on runtime occurs.
 * 
 * In Java-world, you have checked and runtime exceptions. Checked exceptions must always be 
 * caught � The Java compiler will not compile code which does not have catch-blocks for any 
 * code that throws checked exceptions. 
 * 
 * Runtime exceptions in Java do not require a catch-block in the calling code. Since PHP does 
 * not have support for checked exceptions, the divide between runtime and other exceptions is 
 * less strict. However, the purpose of a RuntimeException is still similar: It should be throw 
 * in cases where the calling code does not necessarily have the capacity to handle it.
 * 
 * @author Andrej Kabachnik
 *
 */
class RuntimeException extends \RuntimeException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>