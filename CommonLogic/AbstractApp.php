<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\ConfigurationInterface;

abstract class AbstractApp implements AppInterface {
	const CONFIG_FOLDER_IN_APP = 'Config';
	const CONFIG_FILE_SUFFIX = '.config.json';
	
	private $exface = null;
	private $uid = null;
	private $vendor = null;
	private $alias = null;
	private $alias_with_namespace = '';
	private $directory = '';
	private $name_resolver =  null;
	private $config = null;
	
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
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::get_directory()
	 */
	public function get_directory(){
		if (!$this->directory){
			$this->directory = str_replace(NameResolver::NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $this->get_alias_with_namespace());
		}
		return $this->directory;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::get_directory_absolute_path()
	 */
	public function get_directory_absolute_path(){
		return $this->get_workbench()->filemanager()->get_path_to_vendor_folder() . DIRECTORY_SEPARATOR . $this->get_directory();
	}
	
	public function get_namespace(){
		return substr($this->get_alias_with_namespace(), 0, mb_strripos($this->get_alias_with_namespace(), NameResolver::NAMESPACE_SEPARATOR));
	}
	
	public function get_class_namespace(){
		return str_replace(NameResolver::NAMESPACE_SEPARATOR, '\\', $this->get_alias_with_namespace());
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
	 * @see \exface\Core\Interfaces\AppInterface::get_config()
	 */
	public function get_config(){
		if (is_null($this->config)){
			$this->config = $this->load_config_files();
		}
		return $this->config;
	}
	
	/**
	 * Loads configuration files from the app folder and the installation config folder and merges the respecitve config options
	 * into the given configuration object.
	 * 
	 * This method is handy if an app needs to create some custom base config object and load the config files on that. In this case,
	 * simply overwrite the get_config() method to pass a non-empty $base_config.
	 * 
	 * @param ConfigurationInterface $base_config
	 * @return \exface\Core\Interfaces\ConfigurationInterface
	 */
	protected function load_config_files(ConfigurationInterface $base_config = null){
		$config = !is_null($base_config) ? $base_config : ConfigurationFactory::create_from_app($this);
		
		// Load the default config of the app	
		$config->load_config_file($this->get_config_folder() . DIRECTORY_SEPARATOR . $this->get_config_file_name());
		
		// Load the installation config of the app
		$config->load_config_file($this->get_workbench()->filemanager()->get_path_to_config_folder() . DIRECTORY_SEPARATOR . $this->get_config_file_name());

		// IDEA Enable user specific configurations by looking into config files in the UserData folder here
		
		return $config;		
	}
	
	/**
	 * Returns the file name for configurations of this app. By default it is [vendor].[app_alias].config.json. The app will look for files
	 * with this name in all configuration folders. If your app needs a custom file name, overwrite this method.
	 * @return string
	 */
	protected function get_config_file_name(){
		return $this->get_alias_with_namespace() . static::CONFIG_FILE_SUFFIX;
	}
	
	/**
	 * Returns the absolute path to the config folder of this app. Overwrite this if you want your app configs to be placed somewhere else.
	 * @return string
	 */
	protected function get_config_folder(){
		return $this->get_workbench()->filemanager()->get_path_to_vendor_folder() . DIRECTORY_SEPARATOR . $this->get_directory() . DIRECTORY_SEPARATOR . static::CONFIG_FOLDER_IN_APP;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::get_uid()
	 */
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
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::install()
	 */
	public function install(){
		return '';
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::uninstall()
	 */
	public function uninstall(){
		return '';
	}
}
?>