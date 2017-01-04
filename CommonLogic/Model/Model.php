<?php
namespace exface\Core\CommonLogic\Model;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\DataSources\ModelLoaderInterface;

class Model {
	/** @var \exface\Core\CommonLogic\Workbench */
	private $exface;
	/** @var \exface\Core\CommonLogic\Model\Object[] [ id => object ] */
	private $loaded_objects = array();
	/** @var array [ namespace => [ object_alias => object_id ] ] */
	private $object_library = array();
	private $default_namespace;
	private $model_loader;
	
	function __construct(\exface\Core\CommonLogic\Workbench &$exface){
		$this->exface = $exface;
	}
	
	/**
	 * Fetch object meta data from model by object_id (numeric)
	 * 
	 * @param int $obj object id
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	function get_object_by_id($object_id){
		// first look in the cache
		// if nothing found, load the object and save it to cache for future
		if (!$obj = $this->get_object_from_cache($object_id)){
			$obj = new \exface\Core\CommonLogic\Model\Object($this);
			$obj->set_id($object_id);
			$this->get_model_loader()->load_object($obj);
			$this->cache_object($obj);
		}
		return $obj;
	}
	
	/**
	 * Fetch object meta data from model by alias (e.g. EXFACE.ATTRIBUTE, where EXFACE is the namespace and ATTRIBUTE - the object_alias)
	 *
	 * @param string $alias_including_app
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	function get_object_by_alias($object_alias, $namespace = null){
		if (!$namespace) $namespace = $this->get_default_namespace();
		if (!$obj = $this->get_object_from_cache($this->get_object_id_from_alias($object_alias, $namespace))){
			$obj = new \exface\Core\CommonLogic\Model\Object($this);
			$obj->set_alias($object_alias);
			$obj->set_namespace($namespace);
			$obj = $this->get_model_loader()->load_object($obj);
			$this->cache_object($obj);
		}
		return $obj;
	}
	
	/**
	 * Fetch object meta data from model. This genera method accepts both alias and id.
	 * Since full aliases always contain a dot, an alias is always a string. Thus, all 
	 * numeric parameters are treated as ids.
	 * 
	 * @param int $obj object id
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function get_object($id_or_alias){
		// If the given identifier looks like a UUID, try using it as object id. If this fails, try using it as alias anyway.
		if (strpos($id_or_alias, '0x') === 0 && strlen($id_or_alias) == 34){
			try	{
				$object = $this->get_object_by_id($id_or_alias);
			} catch (\exface\Core\Exceptions\metaModelObjectNotFoundException $e){
				$object = null;
			}
		} 
		
		if (!$object){
			$object = $this->get_object_by_alias($this->get_object_alias_from_qualified_alias($id_or_alias), $this->get_namespace_from_qualified_alias($id_or_alias));
		}
		
		return $object;
	}
	
	private function get_object_id_from_alias($object_alias, $namespace){
		if ($id = $this->object_library[$namespace][$object_alias]){
			return $id;
		} else {
			return false;
		}
	}
	
	/**
	 * Checks if the object is loaded already and returns the cached version. Returns false if the object is not in the cache.
	 * @param int $object_id
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	private function get_object_from_cache($object_id){
		if ($obj = $this->loaded_objects[$object_id]){
			return $obj;
		} else {
			return false;
		}
	}
	
	/**
	 * Adds the object to the model cache. Also sets the default namespace, if it is the first object loaded.
	 * @param \exface\Core\CommonLogic\Model\Object $obj
	 * @return boolean
	 */
	private function cache_object(\exface\Core\CommonLogic\Model\Object $obj){
		$this->loaded_objects[$obj->get_id()] = $obj;
		$this->object_library[$obj->get_namespace()][$obj->get_alias()] = $obj->get_id();
		if (!$this->get_default_namespace()){
			$this->set_default_namespace($obj->get_namespace());
		}
		return true;
	}
	
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * Returns the object part of a full alias ("CUSTOMER" from "CRM.CUSTOMER")
	 * @param string $qualified_alias_with_app
	 * @return string
	 */
	public function get_object_alias_from_qualified_alias($qualified_alias_with_app){
		if ($sep = strrpos($qualified_alias_with_app, NameResolver::NAMESPACE_SEPARATOR)){
			return substr($qualified_alias_with_app, $sep+1);
		} else {
			return $qualified_alias_with_app;
		}
	}
	
	/**
	 * Returns the app part of a full alias ("CRM" from "CRM.CUSTOMER")
	 * @param string $qualified_alias_with_app
	 * @return string
	 */
	public function get_namespace_from_qualified_alias($qualified_alias_with_app){
		return substr($qualified_alias_with_app, 0, strrpos($qualified_alias_with_app, NameResolver::NAMESPACE_SEPARATOR));
	}
	
	public function get_default_namespace() {
		return $this->default_namespace;
	}
	
	public function set_default_namespace($value) {
		$this->default_namespace = $value;
	}  
	
	/**
	 * TODO Move this method to the ExpressionFactory (need to replace all calls...)
	 * @param string $expression
	 * @param Object $object
	 * @return \exface\Core\CommonLogic\Model\Expression
	 */
	function parse_expression($expression, Object $object = null){
		$expr = ExpressionFactory::create_from_string($this->exface, $expression, $object);
		return $expr;
	}
	
	public function get_model_loader() {
		return $this->model_loader;
	}
	
	public function set_model_loader(ModelLoaderInterface $value) {
		$this->model_loader = $value;
		return $this;
	}  
}
?>