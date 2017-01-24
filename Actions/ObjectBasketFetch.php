<?php namespace exface\Core\Actions;

use exface\Core\CommonLogic\Model\Object;

/**
 * Fetches meta object instances stored in the object basket of the specified context_scope (by default, the window scope)
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketFetch extends ObjectBasketAdd {
	
	protected function perform(){
		$this->set_result($this->get_favorites_json());
	}
	
	protected function get_favorites_json(){
		$result = array();
		foreach ($this->get_context()->get_favorites_all() as $fav_list){
			$result[] = array(
					'object_id' => $fav_list->get_meta_object()->get_id(),
					'object_name' => $fav_list->get_meta_object()->get_name(),
					'object_actions' => $this->build_json_actions($fav_list->get_meta_object()),
					'instances' => $fav_list->export_uxon_object()
			);
			
		}
		return json_encode($result);
	}
	
	protected function build_json_actions(Object $object){
		$result = array();
		foreach($object->get_actions()->get_used_in_object_basket() as $a){
			$result[] = array(
				'name' => $a->get_name(),
				'alias' => $a->get_alias_with_namespace()
			);
		}
		return $result;
	}

}
?>