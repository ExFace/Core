<?php namespace exface\Core\Factories;

use exface\Core\Model\Object;
use exface\Core\Model\AttributeGroup;

abstract class AttributeGroupFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param Object $object
	 * @param string $alias
	 * @return AttributeGroup
	 */
	public static function create_for_object(Object &$object, $alias = null){
		$exface = $object->exface();
		$group = new AttributeGroup($exface, $object);
		$group->set_alias($alias);
		switch ($alias) {
			case AttributeGroup::ALL:
				foreach ($object->get_attributes() as $attr){
					$group->add($attr);
				}
				break;
			case AttributeGroup::VISIBLE:
				foreach ($object->get_attributes() as $attr){
					if (!$attr->is_hidden()){
						$group->add($attr);
					}
				}
				break;
			case AttributeGroup::EDITABLE:
				foreach ($object->get_attributes() as $attr){
					if ($attr->is_editable()){
						$group->add($attr);
					}
				}
				break;
			case AttributeGroup::REQUIRED:
				foreach ($object->get_required_attributes() as $attr){
					$group->add($attr);
				}
				break;
			default:
				// TODO load group from DB
				break;
		}
		return $group;
	}
	
}
?>