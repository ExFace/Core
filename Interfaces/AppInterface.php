<?php namespace exface\Core\Interfaces;

use exface\exface;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Actions\ActionInterface;

interface AppInterface extends ExfaceClassInterface, AliasInterface {
	
	public function __construct(exface &$exface);
	
	/**
	 * Returns an action object
	 * @param unknown $action_alias
	 * @return ActionInterface
	 */
	public function get_action($action_alias, AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null);
	
	/**
	 * Returns the directory path to the app folder relative to exface/apps
	 * @return string;
	 */
	public function get_directory();
	
	/**
	 * Return the applications vendor (first part of the namespace)
	 * @return string
	 */
	public function get_vendor();
	
	/**
	 * Returns a single configuration value specified by the given code
	 * @param string $code
	 * @return multitype
	 */
	public function get_configuration_value($code);
	
	public function get_uid();
	
}
?>