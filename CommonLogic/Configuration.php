<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\ConfigurationNotFoundError;

class Configuration implements ConfigurationInterface {
	
	private $exface = null;
	private $config_uxon = null;
	
	/**
	 * @deprecated use ConfigurationFactory instead!
	 * @param Workbench $workbench
	 */
	public function __construct(Workbench &$workbench){
		$this->exface = $workbench;
	}
	
	/**
	 * Returns a UXON object with the current configuration options for this app. Options defined on different levels
	 * (user, installation, etc.) are already merged at this point.
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	protected function get_config_uxon(){
		if (is_null($this->config_uxon)){
			$this->config_uxon = new UxonObject();
		}
		return $this->config_uxon;
	}
	
	/**
	 * Overwrites the internal config UXON with the given UXON object
	 * @param UxonObject $uxon
	 * @return \exface\Core\CommonLogic\Configuration
	 */
	protected function set_config_uxon(UxonObject $uxon){
		$this->config_uxon = $uxon;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ConfigurationInterface::get_option()
	 */
	public function get_option($key){
		if (!$this->get_config_uxon()->has_property($key)){
			if ($key_found = $this->get_config_uxon()->find_property_key($key, false)){
				$key = $key_found;
			} else {
				throw new ConfigurationNotFoundError('Required configuration key "' . $key . '"not found in "' . $this->get_config_uxon()->to_json(false) . '"!');
			}
		}
		return $this->get_config_uxon()->get_property($key);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ConfigurationInterface::load_config_file()
	 */
	public function load_config_file($absolute_path){
		if (file_exists($absolute_path) && $uxon = UxonObject::from_json(file_get_contents($absolute_path))){
			$this->load_config_uxon($uxon);
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ConfigurationInterface::load_config_uxon()
	 */
	public function load_config_uxon(UxonObject $uxon){
		$this->set_config_uxon($this->get_config_uxon()->extend($uxon));
		return $this;
	}
	
	public function export_uxon_object(){
		return $this->get_config_uxon();
	}
	
	public function import_uxon_object(UxonObject $uxon){
		return $this->set_config_uxon($uxon);
	}
		
}


?>