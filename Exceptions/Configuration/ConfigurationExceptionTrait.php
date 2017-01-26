<?php namespace exface\Core\Exceptions\Configuration;

use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\ExceptionTrait;

/**
 * This trait enables an exception to output configuration specific debug information.
 *
 * @author Andrej Kabachnik
 *
 */
trait ConfigurationExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $configuration = null;
	
	public function __construct (ConfigurationInterface $configuration, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_configuration($configuration);
	}
	
	public function get_configuration(){
		return $this->configuration;
	}
	
	public function set_configuration(ConfigurationInterface $value){
		$this->configuration = $value;
		return $this;
	}
	
}
?>