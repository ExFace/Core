<?php namespace exface\Core\Interfaces;

interface UiManagerInterface extends ExfaceClassInterface {	
	
	/**
	 * Output the final UI code for a given widget
	 * IDEA Remove this method from the UI in favor of template::draw() after template handling has been moved to the actions
	 * @param AbstractWidget $widget
	 * @param TemplateInterface ui_template to use when drawing 
	 * @return string
	 */
	function draw(WidgetInterface $widget, TemplateInterface $template = null);
	
	/**
	 * Output document headers, needed for the widget. 
	 * This could be JS-Includes, stylesheets - anything, that needs to be placed in the
	 * resulting document separately from the renderen widget itself.
	 * IDEA Remove this method from the UI in favor of template::draw_headers() after template handling has been moved to the actions
	 * @param WidgetInterface $widget
	 * @param TemplateInterface ui_template to use when drawing
	 * @return string
	 */
	function draw_headers(WidgetInterface $widget, TemplateInterface $template = null);
	
	/**
	 * Returns an ExFace widget from a given resource by id
	 * Caching is used to store widgets from already loaded pages
	 * @param string $widget_id
	 * @param string $page_id
	 * @return \exface\Widgets\AbstractWidget
	 */
	public function get_widget($widget_id, $page_id);
	
	/**
	 * @return TemplateInterface
	 */
	public function get_template_from_request();
	
}

?>