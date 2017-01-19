<?php namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Contexts\Types\DataContext;

interface AppInterface extends ExfaceClassInterface, AliasInterface {
	
	public function __construct(Workbench $exface);
	
	/**
	 * Returns an action object
	 * @param unknown $action_alias
	 * @return ActionInterface
	 */
	public function get_action($action_alias, AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null);
	
	/**
	 * Returns the path to the app's folder relative to the vendor folder
	 * @return string
	 */
	public function get_directory();
	
	/**
	 * Returns the absolute path to the app's folder
	 * @return string
	 */
	public function get_directory_absolute_path();
	
	/**
	 * Return the applications vendor (first part of the namespace)
	 * @return string
	 */
	public function get_vendor();
	
	/**
	 * Returns the configuration object of this app. At this point, the configuration is already fully compiled and contains
	 * all options from all definition levels: defaults, installation config, user config, etc.
	 * 
	 * @return ConfigurationInterface
	 */
	public function get_config();
	
	/**
	 * Returns the unique identifier of this app. It is a UUID by default.
	 * @return string
	 */
	public function get_uid();
	
	/**
	 * Returns an array with data variables stored for this app in the given context scope 
	 * @param string $scope
	 * @return DataContext
	 */
	public function get_context_data($scope);
	
	/**
	 * Returns the value of the given variable stored in the given context scope for this app. If no scope is specified,
	 * the default data scope of this app will be used - @see get_context_data_default_scope()
	 * @param string $variable_name
	 * @param string $scope
	 * @return mixed
	 */
	public function get_context_variable($variable_name, $scope = null);
	
	/**
	 * Sets the value of the given context variable in the specified scope. If no scope specified, the default data
	 * scope of this app will be used - @see get_context_data_default_scope()
	 * 
	 * @param string $variable_name
	 * @param mixed $value
	 * @param string $scope
	 * @return DataContext
	 */
	public function set_context_variable($variable_name, $value, $scope = null);
	
	/**
	 * Removes the given variable from the context of this app in the given scope. If no scope specified, the default data
	 * scope of this app will be used - @see get_context_data_default_scope().
	 * 
	 * @param string $variable_name
	 * @param string $scope
	 * @return DataContext
	 */
	public function unset_context_variable($variable_name, $scope = null);
	
	/**
	 * Returns the alias of the default context scope to be used when saving context data for this app.
	 * If not explicitly specified by set_context_data_default_scope() the window scope will be used.
	 * @return string
	 */
	public function get_context_data_default_scope();
	
	/**
	 * Sets the default context scope to be used when saving context data for this app.
	 * @param string $value
	 * @return AppInterface
	 */
	public function set_context_data_default_scope($scope_alias);
	
	/**
	 *
	 * @param InstallerInterface $injected_installer
	 * @return AppInstallerInterface
	 */
	public function get_installer(InstallerInterface $injected_installer = null);
	
}
?>