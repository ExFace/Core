<?php namespace exface\Core\Contexts\Types;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Object;

/**
 * The FavoritesContext provides a unified interface to store links to selected instances of meta objects in any context scope.
 * If used in the WindowScope it can represent "pinned" objects, while in the UserScope it can be used to create favorites for this
 * user.
 * 
 * It is a bit similar to the DataContext, but it is specific to meta object instances.
 * 
 * @author Andrej Kabachnik
 *
 */
class FavoritesContext extends AbstractContext {
	private $favorites = array();
	
	protected function get_object_from_input($meta_object_or_alias_or_id){
		if ($meta_object_or_alias_or_id instanceof Object){
			$object = $meta_object_or_alias_or_id;
		} else {
			$object = $this->get_workbench()->model()->get_object($meta_object_or_alias_or_id);
		}
		return $object;
	}
	
	public function add_instance($meta_object_or_alias_or_id, $uid, $label = null){
		$object = $this->get_object_from_input($meta_object_or_alias_or_id);
		$this->favorites[$object->get_id()][$uid] = array(
				$object->get_uid_alias() => $uid,
				$object->get_label_alias() => $label,
				$this->get_workbench()->get_config()->get_option('OBJECT_LABEL_ALIAS') => $label
				
		);
		return $this;
	}
	
	public function add_instances($meta_object_or_alias_or_id, array $instances){
		$object = $this->get_object_from_input($meta_object_or_alias_or_id);
		foreach ($instances as $instance){
			if (is_array($instance) || $instance instanceof \stdClass){
				$instance = (array) $instance;
				$uid = $instance[$object->get_uid_alias()];
				$label = $instance[$object->get_label_alias()];
			} else {
				$uid = $instance;
			}
			
			$this->add_instance($meta_object_or_alias_or_id, $uid, $label);
		}
		return $this;
	}
	
	/**
	 * Returns a nested array with favorites. The structure is described below. If an object or it's id/alias is given, only the branch for
	 * this object is returned.
	 * Structure of the resulting array:
	 * [
	 * 		object_id1: [ 
	 * 			uid1: [
	 * 				UID_attribute_alias: uid1,
	 * 				LABEL_attribute_alias: label1
	 * 			],
	 * 			uid2: [...]
	 * 		],
	 * 		object_id2: [...]
	 * ]
	 * 
	 * IDEA: Deeply nested arrays are evil. Better to use UXON object or even a dedicated stack of favorites classes.
	 * 
	 * @param string|Object $meta_object_or_alias_or_id
	 * @return array
	 */
	public function get_favorites($meta_object_or_alias_or_id = null){
		if ($meta_object_or_alias_or_id){
			$object_id = $this->get_object_from_input($meta_object_or_alias_or_id)->get_id();
			if (is_array($this->favorites[$object_id])){
				$favs = $this->favorites[$object_id];
			} else {
				$favs = array();
			}
			return array($object_id => $favs);
		} else {
			return $this->favorites;
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
		foreach ($this->get_favorites() as $object_id => $favorites){
			if (is_array($favorites) && count($favorites) > 0) {
				$uxon->set_property($object_id, $favorites);
			}
		}
		return $uxon;
	}
	
}
?>