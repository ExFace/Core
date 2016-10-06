<?php namespace exface\Core\Contexts\Types;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\ContextError;
use exface\Core\Interfaces\AppInterface;

/**
 * The DataContext provides a unified interface to store arbitrary data in any context scope. It's like storing
 * PHP variables in a specific context scope.
 * 
 * To avoid name conflicts between different apps, all data is tagged with a namespace (the apps qualified alias by default)
 * 
 * @author Andrej Kabachnik
 *
 */
class DataContext extends AbstractContext {
	private $variables = array();
	
	/**
	 * Returns the value stored under the given name
	 * @param string $namespace
	 * @param string $variable_name
	 * @return mixed
	 */
	public function get_variable($namespace, $variable_name) {
		return $this->variables[$namespace][$variable_name];
	}
	
	/**
	 * Stores a value under the given name
	 * @param string $namespace
	 * @param string $variable_name
	 * @param mixed $value
	 * @return \exface\Core\Contexts\Types\DataContext
	 */
	public function set_variable($namespace, $variable_name, $value) {
		$this->variables[$namespace][$variable_name] = $value;
		return $this;
	}
	
	/**
	 * Removes the given variable from the data context
	 * @param string $namespace
	 * @param string $variable_name
	 * @return \exface\Core\Contexts\Types\DataContext
	 */
	public function unset_variable($namespace, $variable_name){
		unset($this->variables[$namespace][$variable_name]);
		return $this;
	}
	
	/**
	 * Removes the given variable from the data context
	 * @param AppInterface $app
	 * @param string $variable_name
	 * @return \exface\Core\Contexts\Types\DataContext
	 */
	public function unset_variable_for_app(AppInterface $app, $variable_name){
		unset($this->variables[$app->get_alias_with_namespace()][$variable_name]);
		return $this;
	}
	
	/**
	 * 
	 * @param AppInterface $app
	 * @param string $variable_name
	 * @param mixed $value
	 * @return \exface\Core\Contexts\Types\DataContext
	 */
	public function set_variable_for_app(AppInterface $app, $variable_name, $value){
		return $this->set_variable($app->get_alias_with_namespace(), $variable_name, $value);
	}
	
	/**
	 * 
	 * @param AppInterface $app
	 * @param string $variable_name
	 * @return mixed
	 */
	public function get_variable_for_app(AppInterface $app, $variable_name){
		return $this->get_variable($app->get_alias_with_namespace(), $variable_name);
	}
	
	/**
	 * Returns an array with all variables from the given namespace
	 * @param string $namespace
	 * @return mixed[]
	 */
	public function get_variables_from_namespace($namespace){
		$vars = $this->variables[$namespace];
		if (!is_array($vars)){
			$vars = array();
		}
		return $vars;
	}
	
	/**
	 *
	 * @param AppInterface $app
	 * @return mixed[]
	 */
	public function get_variables_for_app(AppInterface $app){
		return $this->get_variables_from_namespace($app->get_alias_with_namespace());
	}
	
	/**
	 * @return string[]
	 */
	public function get_namespaces_active(){
		return array_keys($this->variables);
	}
	
	/**
	 * The default scope of the data context is the window. Most apps will run in the context of a single window,
	 * so two windows running one app are independant in general.
	 * @see \exface\Core\Contexts\Types\AbstractContext::get_default_scope()
	 */
	public function get_default_scope(){
		return $this->get_workbench()->context()->get_scope_window();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Types\AbstractContext::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		foreach ($uxon as $namespace => $vars){
			if (!$vars || count($vars) <= 0){
				continue;
			}
			
			foreach ($vars as $variable_name => $value){
				$this->import_uxon_for_variable($namespace, $variable_name, $value);
			}
		}
	}
	
	/**
	 * The data context is exported to the following UXON structure:
	 * {
	 * 		namespace1:
	 * 		{
	 * 			var_name1: var_value1,
	 * 			var_name2: var_value2,
	 * 		},
	 * 		namespace2: ...
	 * }
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Types\AbstractContext::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		foreach ($this->get_namespaces_active() as $namespace){
			if (count($this->get_variables_from_namespace($namespace)) <= 0){
				continue;
			}
			
			$namespace_uxon = $this->get_workbench()->create_uxon_object();
			foreach ($this->get_variables_from_namespace($namespace) as $var => $value){
				$namespace_uxon = $this->export_uxon_for_variable($namespace_uxon, $var, $value);
			}
			
			$uxon->set_property($namespace, $namespace_uxon);
		}
		return $uxon;
	}
	
	protected function export_uxon_for_variable(UxonObject $uxon_container, $variable_name, $variable_value){
		if ($variable_value instanceof UxonObject 
			|| (!is_object($variable_value) && !is_array($variable_value))){
			$uxon_container->set_property($variable_name, $variable_value);
		} elseif (is_array($variable_value)) {
			$uxon_container->set_property($variable_name, $this->get_workbench()->create_uxon_object());
			foreach ($variable_value as $var => $value){
				$this->export_uxon_for_variable($uxon_container->get_property($variable_name), $var, $value);
			}
		} else {
			throw new ContextError('Cannot save data context in for "' . $this->get_scope()->get_name() . '": invalid variable value type for "' . get_class($variable_name) . '"!');
		}
		return $uxon_container;
	}
	
	protected function import_uxon_for_variable($namespace, $variable_name, $value){
		if (is_array($value) || $value instanceof \stdClass){
			$this->set_variable($namespace, $variable_name, (array) $value);
		} elseif (!is_object($value)){
			$this->set_variable($namespace, $variable_name, $value);
		} else {
			throw new ContextError('Cannot load context data for "' . $this->get_scope()->get_name() . '": invalid variable value type for "' . get_class($variable_name) . '"!');
		}
	}
}
?>