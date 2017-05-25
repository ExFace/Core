<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\AttributeList;

abstract class AttributeListFactory
{

    public static function createForObject(Object $parent_object)
    {
        $exface = $parent_object->getWorkbench();
        $list = new AttributeList($exface, $parent_object);
        return $list;
    }
}
?>