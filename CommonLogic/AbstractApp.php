<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;

abstract class AbstractApp implements AppInterface {
	private $exface = null;
	private $uid = null;
	private $vendor = null;
	private $alias = null;
	private $alias_with_namespace = '';
	private $directory = '';
	private $configuration_data_sheet = null;
	private $name_resolver =  null;
	private $config_folder = 'Config';
	private $config_uxon = null;
	
	/**
	 * 
	 * @param \exface\Core\CommonLogic\Workbench $exface
	 * @deprecated use AppFactory instead!
	 */
	public function __construct(\exface\Core\CommonLogic\Workbench &$exface){
		$this->exface = $exface;
		// Create an alias from the class (e.g. "exface.core" from "exface\Core\Core\CoreApp")
		$this->alias_with_namespace = str_replace(NameResolver::CLASS_NAMESPACE_SEPARATOR, NameResolver::NAMESPACE_SEPARATOR, substr(get_class($this), 0, strrpos(get_class($this), NameResolver::CLASS_NAMESPACE_SEPARATOR)));
		$this->init();
	}
	
	/**
	 * This ist the startup-method for apps. Anything put here will be run right after the app is instantiated. By default it does not do anything!
	 * This method is handy to initialize some dependencies, variables, etc. 
	 * @return void
	 */
	protected function init(){
		
	}
	
	/**
	 * Returns an action object
	 * @param string $action_alias
	 * @return ActionInterface
	 */
	public function get_action($action_alias, \exface\Core\Widgets\AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null){
		if (!$action_alias) return false;
		$exface = $this->get_workbench();
		$action = ActionFactory::create_from_string($exface, $this->get_alias_with_namespace() . NameResolver::NAMESPACE_SEPARATOR . $action_alias, $called_by_widget);
		if ($uxon_description instanceof \stdClass){
			$action->import_uxon_object($uxon_description);
		}
		return $action;
	}
	
	public function get_alias_with_namespace(){
		return $this->alias_with_namespace;
	}
	
	public function get_alias(){
		if (is_null($this->alias)){
			$this->alias = str_replace($this->get_vendor() . DIRECTORY_SEPARATOR, '', $this->get_alias_with_namespace());
		}
		return $this->alias;
	}
	
	/**
	 * Returns the directory path to the app folder relative to exface/apps
	 * @return string;
	 */
	public function get_directory(){
		if (!$this->directory){
			$this->directory = str_replace($this->get_workbench()->get_config_value('namespace_separator'), DIRECTORY_SEPARATOR, $this->get_alias_with_namespace());
		}
		return $this->directory;
	}
	
	public function get_namespace(){
		return substr($this->get_alias_with_namespace(), 0, mb_strripos($this->get_alias_with_namespace(), NameResolver::NAMESPACE_SEPARATOR));
	}
	
	public function get_class_namespace(){
		return str_replace($this->get_workbench()->get_config_value('namespace_separator'), '\\', $this->get_alias_with_namespace());
	}
	
	/**
	 * Return the applications vendor (first part of the namespace)
	 * @return string
	 */
	public function get_vendor(){
		if (is_null($this->vendor)){
			$this->vendor = explode(NameResolver::NAMESPACE_SEPARATOR, $this->get_alias_with_namespace())[0];
		}
		return $this->vendor;
	}
	
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * Returns a UXON object with the current configuration options for this app. Options defined on different levels
	 * (user, installation, etc.) are already merged at this point.
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	public function get_config_uxon(){
		$config_path = $this->get_workbench()->filemanager()->get_path_to_vendor_folder() . DIRECTORY_SEPARATOR . $this->get_directory() . DIRECTORY_SEPARATOR . $this->config_folder;
		if (is_null($this->config_uxon) && is_dir($config_path)){
			foreach (scandir($config_path) as $file){
				if (stripos($file, '.json') !== false || stripos($file, '.uxon') !== false){
					if ($uxon = UxonObject::from_json(file_get_contents($config_path . DIRECTORY_SEPARATOR . $file))){
						$this->config_uxon = $this->config_uxon instanceof UxonObject ? $this->config_uxon->extend($uxon) : $uxon;
					}
				}
			}
		}
		return $this->config_uxon;
	}
	
	/**
	 * Returns a single configuration value specified by the given code
	 * @param string $code
	 * @return multitype
	 */
	public function get_config_value($code){
		return $this->get_config_uxon()->get_property($code);
	}
	
	public function get_uid(){
		if (is_null($this->uid)){
			$ds = $this->get_workbench()->data()->create_data_sheet($this->get_workbench()->model()->get_object('exface.Core.APP'));
			$ds->add_filter_from_string('ALIAS', $this->get_alias_with_namespace());
			$ds->data_read();
			$this->uid = $ds->get_uid_column()->get_cell_value(0);
		}
		return $this->uid;
	}
	
	public function get_name_resolver() {
		return $this->name_resolver;
	}
	
	public function set_name_resolver(NameResolver $value) {
		$this->name_resolver = $value;
		return $this;
	}	
}
?>