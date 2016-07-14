<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\AttributeList;

abstract class AttributeListFactory {
	
	public static function create_for_object(Object &$parent_object){
		$exface = $parent_object->get_workbench();
		$list = new AttributeList($exface, $parent_object);
		return $list;
	}
	
}
?>