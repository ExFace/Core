<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\Selectors\BehaviorSelector;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;

abstract class BehaviorFactory extends AbstractSelectableComponentFactory
{
    /**
     * 
     * @param BehaviorSelectorInterface $selector
     * @param MetaObjectInterface $object
     * @return BehaviorInterface
     */
    public static function create(BehaviorSelectorInterface $selector, MetaObjectInterface $object) : BehaviorInterface
    {
        $instance = static::createFromSelector($selector);
        $instance->setObject($object);
        return $instance;
    }

    /**
     *
     * @param MetaObjectInterface $object            
     * @param string $selectorString            
     * @param UxonObject $uxon            
     * @return BehaviorInterface
     */
    public static function createFromUxon(MetaObjectInterface $object, $selectorString, UxonObject $uxon)
    {
        $selector = new BehaviorSelector($object->getWorkbench(), $selectorString);
        $instance = static::create($selector, $object);
        $instance->importUxonObject($uxon);
        return $instance;
    }
}
?>