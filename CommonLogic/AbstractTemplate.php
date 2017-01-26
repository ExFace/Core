<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Factories\AppFactory;

abstract class AbstractTemplate implements TemplateInterface {
	private $exface = null;
	private $app = null;
	private $alias = '';
	private $name_resolver = null;
	private $response = '';
	
	public final function __construct(\exface\Core\CommonLogic\Workbench $exface){
		$this->exface = $exface;
		$this->alias = substr(get_class($this), (strrpos(get_class($this), DIRECTORY_SEPARATOR)+1));
		$this->init();
	}
	
	protected function init(){
		
	}
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\NameResolverInterfacer
	 */
	public function get_name_resolver() {
		if (is_null($this->name_resolver)){
			$this->name_resolver = NameResolver::create_from_string(get_class($this), NameResolver::OBJECT_TYPE_TEMPLATE, $this->exface);
		}
		return $this->name_resolver;
	}
	
	/**
	 * 
	 * @param NameResolverInterface $value
	 * @return \exface\Core\CommonLogic\AbstractTemplate
	 */
	public function set_name_resolver(NameResolverInterface $value) {
		$this->name_resolver = $value;
		return $this;
	}  
	
	public function get_namespace(){
		return $this->get_name_resolver()->get_namespace();
	}
	
	public function get_alias_with_namespace(){
		return $this->get_name_resolver()->get_alias_with_namespace();
	}
	
	public function get_alias(){
		return $this->alias;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 * @return Workbench
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	abstract function draw(\exface\Core\Widgets\AbstractWidget $widget);
	
	/**
	 * Generates the declaration of the JavaScript sources
	 * @return string
	 */
	abstract function draw_headers(\exface\Core\Widgets\AbstractWidget $widget);
	
	/**
	 * Processes the current HTTP request, assuming it was made from a UI using this template
	 * @param string $page_id
	 * @param string $widget_id
	 * @param string $action_alias
	 * @param boolean $disable_error_handling
	 * @return string
	 */
	abstract function process_request($page_id=NULL, $widget_id=NULL, $action_alias=NULL, $disable_error_handling=false);  
	
	public function is($template_alias){
		if (strcasecmp($this->get_alias(), $template_alias) == 0){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TemplateInterface::get_response()
	 */
	public function get_response() {
		return $this->response;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TemplateInterface::set_response()
	 */
	public function set_response($value) {
		$this->response = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TemplateInterface::get_app()
	 */
	public function get_app() {
		if (is_null($this->app)){
			$this->app = AppFactory::create_from_alias($this->get_name_resolver()->get_alias_with_namespace(), $this->exface);
		}
		return $this->app;
	}
	
	public function set_app(AppInterface $value) {
		$this->app = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\TemplateInterface::get_config()
	 */
	public function get_config(){
		return $this->get_app()->get_config();
	}
  
}
?>