<?php namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Actions\ActionInterface;

interface AppInterface extends ExfaceClassInterface, AliasInterface {
	
	public function __construct(Workbench &$exface);
	
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
	 * This method must be performed by package managers after the app had been installed or updated. App developers can
	 * implement custom code here to finalize the installation. When this method is called, you can be sure, that the app
	 * files are already in place and the meta model had already been imported. Thus simple apps would already be functional.
	 * This method only needs to be implemented if the app needs to instantiate some cache, build indexes, copy files 
	 * somewhere, etc. - things the package manager will not do by default. 
	 * The method should return some user readable result statement.
	 * 
	 * @return string 
	 */
	public function install();
	
	/**
	 * This method must be performed by package managers before the app gets uninstalled. App developers can implement custom code 
	 * here to clean up data a prepare the deinstallation. The method should return some user readable result statement.
	 * 
	 * @return string 
	 */
	public function uninstall();
	
}
?>