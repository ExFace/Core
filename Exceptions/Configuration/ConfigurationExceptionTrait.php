<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\ConfigurationInterface;

trait ConfigurationExceptionTrait {
	
	private $configuration = null;
	
	public function __construct (ConfigurationInterface $configuration, $message, $code, $previous = null) {
		parent::__construct($message, $code, $previous);
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