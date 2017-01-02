<?php namespace exface\Core\Exceptions\Configuration;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\ConfigurationExceptionInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\OutOfRangeException;

class ConfigOptionNotFoundError extends OutOfRangeException implements ConfigurationExceptionInterface, ErrorExceptionInterface {
	
	use ConfigurationExceptionTrait;
	
	public function __construct (ConfigurationInterface $configuration, $message, $code = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_configuration($configuration);
	}
	
}