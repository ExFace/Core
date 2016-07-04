<?php namespace exface\Core\CommonLogic;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Exceptions\TemplateError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Factories\TemplateFactory;

class UiManager implements UiManagerInterface {
	private $widget_id_forbidden_chars_regex = '[^A-Za-z0-9_\.]';
	private $widget_ids_registered = array();
	private $loaded_templates = array();
	private $loaded_pages = array();
	private $pages = array();
	private $exface = null;
	private $base_template = null;
	
	function __construct(\exface\Core\CommonLogic\Workbench &$exface){
		$this->exface = $exface;
	}
	
	/**
	 * Returns a template instance for a given template alias. If no alias given, returns the current template.
	 * @param string $template
	 * @return AbstractTemplate
	 */
	function get_template($template=null){
		if (!$template) return $this->get_template_from_request();
		
		if (!$instance = $this->loaded_templates[$template]){			
			$instance = TemplateFactory::create_from_string($template, $this->exface);
			$this->loaded_templates[$template] = $instance;
		}
				
		return $instance;
	}
	
	/**
	 * Output the final UI code for a given widget
	 * IDEA Remove this method from the UI in favor of template::draw() after template handling has been moved to the actions
	 * @param AbstractWidget $widget
	 * @param TemplateInterface ui_template to use when drawing 
	 * @return string
	 */
	function draw(WidgetInterface $widget, TemplateInterface $template = null){
		if (!$widget) throw new TemplateError('Cannot draw empty widget!');
		if (is_null($template)) $template = $this->get_template_from_request();
		return $template->draw($widget);
	}
	
	/**
	 * Output document headers, needed for the widget. 
	 * This could be JS-Includes, stylesheets - anything, that needs to be placed in the
	 * resulting document separately from the renderen widget itself.
	 * IDEA Remove this method from the UI in favor of template::draw_headers() after template handling has been moved to the actions
	 * @param WidgetInterface $widget
	 * @param TemplateInterface ui_template to use when drawing
	 * @return string
	 */
	function draw_headers(WidgetInterface $widget, TemplateInterface $template = null){
		if (!$widget) throw new TemplateError('Cannot draw empty widget!');
		if (is_null($template)) $template = $this->get_template_from_request();
		return $template->draw_headers($widget);
	}
	
	/**
	 * Returns an ExFace widget from a given resource by id
	 * Caching is used to store widgets from already loaded pages
	 * @param string $widget_id
	 * @param string $page_id
	 * @return WidgetInterface
	 */
	function get_widget($widget_id, $page_id){
		$page = $this->get_page($page_id);
		if ($widget_id){
			return $page->get_widget($widget_id);
		} else {
			return $page->get_widget_root();
		}
	}
	
	public function exface(){
		return $this->exface;
	}
	
	/**
	 * 
	 * @param unknown $page_id
	 * @return UiPageInterface
	 */
	public function get_page($page_id){
		if (!$this->pages[$page_id]){ 
			$this->pages[$page_id] = UiPageFactory::create_from_cms_page($this, $page_id);
		}
		return $this->pages[$page_id];
	}
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\UiPageInterface
	 */
	public function get_page_current(){
		return $this->get_page($this->exface()->cms()->get_page_id());
	}
	
	public function get_template_from_request() {
		if (is_null($this->base_template)){
			$this->base_template = $this->get_template($this->exface()->get_config_value('default_ui_template'));
		}
		return $this->get_template($this->base_template);
	}
	
	public function set_base_template_alias($qualified_alias) {
		$this->base_template = $qualified_alias;
		return $this;
	}  
}

?>