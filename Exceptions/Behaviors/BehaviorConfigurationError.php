<?php namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if a configuration option for a meta object behavior is invalid or missing. 
 * 
 * Behaviors are encouraged to produce this error if the user creates an invalid UXON configuration for the behavior
 * invalid option values are set programmatically.
 *
 * @author Andrej Kabachnik
 *
 */
class BehaviorConfigurationError extends RuntimeException {
	
}
?>