<?php namespace exface\Core\Interfaces;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\UxonObject;

interface ConfigurationInterface extends ExfaceClassInterface, iCanBeConvertedToUxon {
		
	/**
	 * Returns a single configuration value specified by the given code
	 * @param string $key
	 * @return multitype
	 */
	public function get_option($key);
	
	/**
	 * 
	 * @param string $absolute_path
	 * @return ConfigurationInterface
	 */
	public function load_config_file($absolute_path);
	
	/**
	 * 
	 * @param UxonObject $uxon
	 * @return ConfigurationInterface
	 */
	public function load_config_uxon(UxonObject $uxon);
		
}


?>