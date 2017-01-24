<?php namespace exface\Core\Contexts\Types;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\Contexts\ContextOutOfBoundsError;

/**
 * The ObjectBasketContext provides a unified interface to store links to selected instances of meta objects in any context scope.
 * If used in the WindowScope it can represent "pinned" objects, while in the UserScope it can be used to create favorites for this
 * user.
 * 
 * It is a bit similar to the DataContext, but it is specific to meta object instances.
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketContext extends AbstractContext {
	private $favorites = array();
	
	protected function get_object_from_input($meta_object_or_alias_or_id){
		if ($meta_object_or_alias_or_id instanceof Object){
			$object = $meta_object_or_alias_or_id;
		} else {
			$object = $this->get_workbench()->model()->get_object($meta_object_or_alias_or_id);
		}
		return $object;
	}
	
	public function add_instance($object_id, $uid, $label = null){
		$this->get_favorites_by_object_id($object_id)->add_instance($uid, $label);
		/*$object = $this->get_object_from_input($meta_object_or_alias_or_id);
		$this->favorites[$object->get_id()][$uid] = array(
				$object->get_uid_alias() => $uid,
				$object->get_label_alias() => $label,
				$this->get_workbench()->get_config()->get_option('OBJECT_LABEL_ALIAS') => $label
				
		);*/
		return $this;
	}
	
	public function add_instances($object_id, array $instances){
		$object = $this->get_workbench()->model()->get_object_by_id($object_id);
		foreach ($instances as $instance){
			if (is_array($instance) || $instance instanceof \stdClass){
				$instance = (array) $instance;
				$uid = $instance[$object->get_uid_alias()];
				$label = $instance[$object->get_label_alias()];
			} else {
				$uid = $instance;
			}
			$this->add_instance($object_id, $uid, $label);
		}
		return $this;
	}
	
	/**
	 * @return ObjectBasketInstanceList[]
	 */
	public function get_favorites_all(){
		return $this->favorites;
	}
	
	/**
	 * 
	 * @param string $object_id
	 * @return ObjectBasketInstanceList
	 */
	public function get_favorites_by_object_id($object_id){
		if (!($this->favorites[$object_id] instanceof ObjectBasketInstanceList)){
			$exface = $this->get_workbench();
			$object = $exface->model()->get_object_by_id($object_id);
			$this->favorites[$object_id] = new ObjectBasketInstanceList($exface, $object);
		}
		return $this->favorites[$object_id];
	}
	
	/**
	 * 
	 * @param Object $object
	 * @return ObjectBasketInstanceList
	 */
	public function get_favorites_by_object(Object $object){
		return $this->get_favorites_by_object_id($object->get_id());
	}
	
	/**
	 * 
	 * @param string $alias_with_namespace
	 * @throws ContextOutOfBoundsError
	 * @return ObjectBasketInstanceList
	 */
	public function get_favorites_by_object_alias($alias_with_namespace){
		$object = $this->get_workbench()->model()->get_object_by_alias($alias_with_namespace);
		if ($object){
			return $this->get_favorites_by_object_id($object->get_id());
		} else {
			throw new ContextOutOfBoundsError($this, 'ObjectBasket requested for non-existant object alias "' . $alias_with_namespace . '"!', '6T5E5VY');
		}
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
		foreach ((array) $uxon as $object_id => $instances){
			$this->add_instances($object_id, (array) $instances);
		}
	}
	
	/**
	 * The favorites context is exported to the following UXON structure:
	 * {
	 * 		object_id1: { 
	 * 			uid1: {
	 * 				UID_attribute_alias: uid1,
	 * 				LABEL_attribute_alias: label1
	 * 			},
	 * 			uid2: {...}
	 * 		}
	 * 		object_id2: ...
	 * }
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Types\AbstractContext::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		foreach ($this->get_favorites_all() as $object_id => $favorites){
			if (!$favorites->is_empty()) {
				$uxon->set_property($object_id, $favorites->export_uxon_object());
			}
		}
		return $uxon;
	}
	
	/**
	 * 
	 * @param string $object_id
	 * @return \exface\Core\Contexts\Types\ObjectBasketContext
	 */
	public function remove_instances_for_object_id($object_id){
		unset($this->favorites[$object_id]);
		return $this;
	}
	
}
?>