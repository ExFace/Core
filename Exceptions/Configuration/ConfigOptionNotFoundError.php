<?php namespace exface\Core\Exceptions\Configuration;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\ConfigurationExceptionInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\OutOfRangeException;

/**
 * Exception thrown no value can be found for a requested configuration option.
 * 
 * In general, all configuration options should be defined with their default values in the applications
 * config file, so this exception will normally indicate a bug in the code calling a non-existant config
 * value.
 *
 * @author Andrej Kabachnik
 *
 */
class ConfigOptionNotFoundError extends OutOfRangeException implements ConfigurationExceptionInterface, ErrorExceptionInterface {
	
	use ConfigurationExceptionTrait;
	
	public function __construct (ConfigurationInterface $configuration, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_configuration($configuration);
	}
	
}