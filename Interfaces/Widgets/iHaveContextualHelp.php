<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveContextualHelp extends WidgetInterface {
	
	/**
	 * 
	 * @return iTriggerAction
	 */
	public function get_help_button();
	
	/**
	 * Fills the given container to build up context-sensitive help for an end-user.
	 *
	 * What exactly belongs into a help container depends on the specific widget type.
	 *
	 * @param iContainOtherWidgets $help_container
	 * @return WidgetInterface
	 */
	public function get_help_widget(iContainOtherWidgets $help_container);
	
	/**
	 * 
	 * @return boolean
	 */
	public function get_hide_help_button();
	
	/**
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Interfaces\Widgets\iHaveContextualHelp
	 */
	public function set_hide_help_button($value);
	  
}