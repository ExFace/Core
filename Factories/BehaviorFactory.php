<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\Selectors\BehaviorSelector;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

abstract class BehaviorFactory extends AbstractSelectableComponentFactory
{
    /**
     * 
     * @param BehaviorSelectorInterface $selector
     * @param MetaObjectInterface $object
     * @param AppSelectorInterface|string $appSelectorOrString
     * 
     * @return BehaviorInterface
     */
    public static function create(BehaviorSelectorInterface $selector, MetaObjectInterface $object, $appSelectorOrString = null) : BehaviorInterface
    {
        $instance = static::createFromSelector($selector);
        $instance->setObject($object);
        if ($appSelectorOrString !== null) {
            $instance->setAppSelector($appSelectorOrString);
        }
        return $instance;
    }

    /**
     *
     * @param MetaObjectInterface $object            
     * @param string $behaviorSelectorString   
     * @param AppSelectorInterface|string $appSelectorOrString
     * @param UxonObject $uxon   
     *          
     * @return BehaviorInterface
     */
    public static function createFromUxon(MetaObjectInterface $object, string $behaviorSelectorString, UxonObject $uxon, $appSelectorOrString = null)
    {
        $selector = new BehaviorSelector($object->getWorkbench(), $behaviorSelectorString, $appSelectorOrString);
        $instance = static::create($selector, $object);
        $instance->importUxonObject($uxon);
        return $instance;
    }
}
?>