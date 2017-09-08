<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Model\AttributeList;

abstract class AttributeListFactory
{

    public static function createForObject(MetaObjectInterface $parent_object)
    {
        $exface = $parent_object->getWorkbench();
        $list = new AttributeList($exface, $parent_object);
        return $list;
    }
}
?>