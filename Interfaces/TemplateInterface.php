<?php namespace exface\Core\Interfaces;

interface TemplateInterface extends ExfaceClassInterface, AliasInterface {
	
	function draw(\exface\Core\Widgets\AbstractWidget $widget);
	/**
	 * Generates the declaration of the JavaScript sources
	 * @return string
	 */
	function draw_headers(\exface\Core\Widgets\AbstractWidget $widget);
	
	/**
	 * @return string
	 */
	public function get_alias();
	
	/**
	 * Processes the current HTTP request, assuming it was made from a UI using this template
	 * @return string
	 */
	public function process_request();
	
	/**
	 * Returns TRUE if this template matches the given template alias and false otherwise (case insensitive!)
	 * @param string $template_alias
	 */
	public function is($template_alias);
	
	/**
	 * @return string
	 */
	public function get_response();
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\Interfaces\TemplateInterface
	 */
	public function set_response($value);
	
	/**
	 * Returns the app, that contains the template
	 * @return AppInterface
	 */
	public function get_app();
}
?>