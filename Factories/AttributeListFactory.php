<?php namespace exface\Core\Factories;

use exface\Core\Model\Object;
use exface\Core\Model\AttributeList;

abstract class AttributeListFactory {
	
	public static function create_for_object(Object &$parent_object){
		$exface = $parent_object->exface();
		$list = new AttributeList($exface, $parent_object);
		return $list;
	}
	
}
?>