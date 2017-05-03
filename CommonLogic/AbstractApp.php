<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Actions\ActionNotFoundError;

abstract class AbstractApp implements AppInterface {
	const CONFIG_FOLDER_IN_APP = 'Config';
	const CONFIG_FILE_SUFFIX = 'config';
	const CONFIG_FILE_EXTENSION = '.json';
	const TRANSLATIONS_FOLDER_IN_APP = 'Translations';
	
	private $exface = null;
	private $uid = null;
	private $vendor = null;
	private $alias = null;
	private $alias_with_namespace = '';
	private $directory = '';
	private $name_resolver =  null;
	private $config = null;
	private $context_data_default_scope = null;
	private $translator = null;
	
	/**
	 * 
	 * @param \exface\Core\CommonLogic\Workbench $exface
	 * @deprecated use AppFactory instead!
	 */
	public function __construct(\exface\Core\CommonLogic\Workbench $exface){
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
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::get_action()
	 */
	public function get_action($action_alias, \exface\Core\Widgets\AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null){
		if (!$action_alias){
			throw new ActionNotFoundError('Cannot find action with alias "' . $action_alias . '" in app "' . $this->get_alias_with_namespace . '"!');
		}
		$action = ActionFactory::create_from_string($this->get_workbench(), $this->get_alias_with_namespace() . NameResolver::NAMESPACE_SEPARATOR . $action_alias, $called_by_widget);
		if ($uxon_description instanceof \stdClass){
			$action->import_uxon_object($uxon_description);
		}
		return $action;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AliasInterface::get_alias_with_namespace()
	 */
	public function get_alias_with_namespace(){
		return $this->alias_with_namespace;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AliasInterface::get_alias()
	 */
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

		// Load the user config if there is one
		// IDEA Enable user-configs for the core app too: currently custom configs are not possible for the core app, 
		// because it's config is loaded before the context.
		if ($this->get_workbench()->context()){
			$config->load_config_file($this->get_workbench()->context()->get_scope_user()->get_user_data_folder_absolute_path() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $this->get_config_file_name());
		}
		
		return $config;		
	}
	
	/**
	 * Returns the file name for configurations of this app. By default it is [vendor].[app_alias].[file_suffix].json. 
	 * The app will look for files with this name in all configuration folders. If your app needs a custom file name, overwrite this method.
	 * Using different file suffixes allows the developer to have separate configuration files for app specific purposes. 
	 * @param string $file_suffix
	 * @return string
	 */
	public function get_config_file_name($file_suffix = 'config'){
		if (is_null($file_suffix)){
			$file_suffix = static::CONFIG_FILE_SUFFIX;
		}
		$file_suffix = $file_suffix ? '.' . $file_suffix : '';
		return $this->get_alias_with_namespace() . $file_suffix .  static::CONFIG_FILE_EXTENSION;
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
			$ds = DataSheetFactory::create_from_object_id_or_alias($this->exface, 'exface.Core.APP');
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
	 * @see \exface\Core\Interfaces\AppInterface::get_context_data_default_scope()
	 */
	public function get_context_data_default_scope() {
		if (is_null($this->context_data_default_scope)){
			$this->context_data_default_scope = ContextManagerInterface::CONTEXT_SCOPE_WINDOW;
		}
		return $this->context_data_default_scope;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::set_context_data_default_scope()
	 */
	public function set_context_data_default_scope($scope_alias) {
		$this->context_data_default_scope = $scope_alias;
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::get_context_data()
	 */
	public function get_context_data($scope = null){
		if (is_null($scope)){
			$scope = $this->get_context_data_default_scope();
		}
		return $this->get_workbench()->context()->get_scope($scope)->get_context('Data');
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::get_context_variable()
	 */
	public function get_context_variable($variable_name, $scope = null){
		return $this->get_context_data($scope)->get_variable_for_app($this, $variable_name);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::set_context_variable()
	 */
	public function set_context_variable($variable_name, $value, $scope = null){
		return $this->get_context_data($scope)->set_variable_for_app($this, $variable_name, $value);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::unset_context_variable()
	 */
	public function unset_context_variable($variable_name, $scope = null){
		return $this->get_context_data($scope)->unset_variable_for_app($this, $variable_name);
	}
	
	public function get_translator(){
		if (is_null($this->translator)){
			$translator = new Translation($this);
			$translator->set_locale($this->get_workbench()->context()->get_scope_session()->get_session_locale());
			$translator->set_fallback_locales(array('en_US'));
			$this->translator = $this->load_translation_files($translator);
		}
		return $this->translator;
	}
	
	protected function load_translation_files(TranslationInterface $translator){
		$locales = array_unique(array_merge(array($translator->get_locale()), $translator->get_fallback_locales()));
		
		foreach ($locales as $locale){
			$locale_suffixes = array();
			$locale_suffixes[] = $locale;
			$locale_suffixes[] = explode('_', $locale)[0];
			$locale_suffixes = array_unique($locale_suffixes);
			
			foreach ($locale_suffixes as $suffix){
				$filename = $this->get_alias_with_namespace() . '.' . $suffix . '.json';
				// Load the default translation of the app
				$translator->add_dictionary_from_file($this->get_translations_folder() . DIRECTORY_SEPARATOR . $filename, $locale);
			
				// Load the installation specific translation of the app
				$translator->add_dictionary_from_file($this->get_workbench()->filemanager()->get_path_to_translations_folder() . DIRECTORY_SEPARATOR . $filename, $locale);
			}
		}
	
		return $translator;
	}
	
	protected function get_translations_folder(){
		return $this->get_workbench()->filemanager()->get_path_to_vendor_folder() . DIRECTORY_SEPARATOR . $this->get_directory() . DIRECTORY_SEPARATOR . static::TRANSLATIONS_FOLDER_IN_APP;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::get_installer()
	 * @return AppInstallerContainer
	 */
	public function get_installer(InstallerInterface $injected_installer = null){
		$app_installer = new AppInstallerContainer($this);
		// Add the injected installer
		if ($injected_installer){
			$app_installer->add_installer($injected_installer);
		}
		return $app_installer;
	}
	
	/**
	 * By default a class is conscidered part of an app if it is in the namespace of the app.
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInterface::contains_class()
	 */
	public function contains_class($object_or_class_name){
		if (is_object($object_or_class_name)){
			$class_name = get_class($object_or_class_name);
		} elseif (is_string($object_or_class_name)) {
			$class_name = $object_or_class_name;
		} else {
			throw new InvalidArgumentException('AppInterface::contains_class() expects the argument to be either an object or a string class name: "' . gettype($object_or_class_name) . '" given instead!');
		}
		
		$app_namespace = $this->get_name_resolver()->get_class_namespace();
		$app_namespace = substr($app_namespace, 0, 1) == "\\" ? substr($app_namespace, 1) : $app_namespace;
		$class_name = substr($class_name, 0, 1) == "\\" ? substr($class_name, 1) : $class_name;
		if (substr($class_name, 0, strlen($app_namespace)) == $app_namespace){
			return true;
		}
		return false;
	}
}
?>