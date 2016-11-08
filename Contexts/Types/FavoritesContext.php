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
		$this->favorites[$object->get_id()] = array(
				'UID' => $uid, 
				$this->get_workbench()->get_config()->get_option('OBJECT_LABEL_ALIAS') => $label
				
		);
		return $this;
	}
	
	public function add_instances($meta_object_or_alias_or_id, array $instances){
		foreach ($instances as $instance){
			if (is_array($instance)){
				$uid = $instance['UID'];
				$label = $instance[$this->get_workbench()->get_config()->get_option('OBJECT_LABEL_ALIAS')];
			} else {
				$uid = $instance;
			}
			$this->add_instance($meta_object_or_alias_or_id, $uid, $label);
		}
		return $this;
	}
	
	public function get_favorites($meta_object_or_alias_or_id = null){
		
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
		foreach ($uxon as $object_id => $instances){
			$this->add_instances($object_id, $instances);
		}
	}
	
	/**
	 * The favorites context is exported to the following UXON structure:
	 * {
	 * 		[object_id]:
	 * 		{
	 * 			UID: [uid],
	 * 			LABEL: [label]
	 * 		},
	 * 		[object_id]: ...
	 * }
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Types\AbstractContext::export_uxon_object()
	 */
	public function export_uxon_object(){
		return UxonObject::from_anything($this->get_favorites());
	}
	
}
?>