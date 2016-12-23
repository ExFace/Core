<?php namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\ConfigurationInterface;

Interface ConfigurationExceptionInterface {
	
	/**
	 * 
	 * @param ConfigurationInterface $configuration
	 * @param string $message
	 * @param string $code
	 * @param \Throwable $previous
	 */
	public function __construct (ConfigurationInterface $configuration, $message, $code = null, $previous = null);
	
	/**
	 * 
	 * @return ConfigurationInterface
	 */
	public function get_configuration();
	
	/**
	 * 
	 * @param ConfigurationInterface $sheet
	 * @return ConfigurationExceptionInterface
	 */
	public function set_configuration(ConfigurationInterface $configuration);
	
}
?>