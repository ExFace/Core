<?php namespace exface\Core\Contexts\Types;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;

abstract class AbstractContext implements ContextInterface {
	private $exface = NULL;
	private $scope = null;
	private $alias = NULL;
	
	public function __construct(\exface\Core\CommonLogic\Workbench $exface){
		$this->exface = $exface;
	}
	
	/**
	 * Returns the scope of this speicific context
	 * @return AbstractContextScope
	 */
	public function get_scope() {
		return $this->scope;
	}
	
	/**
	 * Sets the scope for this specific context
	 * @param AbstractContextScope $context_scope
	 * @return AbstractContext
	 */
	public function set_scope(ContextScopeInterface $context_scope) {
		$this->scope = $context_scope;
		return $this;
	}  
	
	/**
	 * Returns the default scope for this type of context.
	 * @return \exface\Core\Contexts\Scopes\windowContextScope
	 */
	public function get_default_scope(){
		return $this->get_workbench()->context()->get_scope_window();
	}
	
	/**
	 * @return \exface\Core\CommonLogic\Workbench
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * Returns a serializable UXON object, that represents the current contxt, thus preparing it to be saved in a session, 
	 * cookie, database or whatever is used by a context scope.
	 * What exactly ist to be saved, strongly depends on the context type: an action context needs an acton alias and, perhaps, a data backup,
	 * a filter context needs to save it's filters conditions, etc. In any case, the serialized version should contain enoght
	 * data to restore the context completely afterwards, but also not to much data in order not to consume too much space in
	 * whatever stores the respective context scope.
	 * @return UxonObject
	 */
	public function export_uxon_object(){
		return $this->get_workbench()->create_uxon_object();
	}
	
	/**
	 * Restores a context from it's UXON representation. The input is whatever export_uxon_object() produces for this context type.
	 * @param UxonObject
	 * @return AbstractContext
	 */
	public function import_uxon_object(UxonObject $uxon){
		return $this;
	}
	
	/**
	 * Returns the alias (name) of the context - e.g. "Filter" for the FilterContext, etc.
	 * @return string
	 */
	public function get_alias(){
		if (!$this->alias){
			$this->alias = substr(get_class($this), (strrpos(get_class($this), DIRECTORY_SEPARATOR)+1), -7);	
		}
		return $this->alias;
	}
}
?>