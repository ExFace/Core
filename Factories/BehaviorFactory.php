<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\Selectors\BehaviorSelector;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class BehaviorFactory extends AbstractSelectableComponentFactory
{
    public static function createFromSelector(SelectorInterface $selector, array $constructorArguments = null)
    {
        if (! $selector instanceof BehaviorSelectorInterface) {
            throw new InvalidArgumentException('BehaviorFactory can only create behaviors from BehaviorSelectorInterface selectors, but got ' . get_class($selector) . ' "' . $selector->toString() . '"');
        }
        
        if ($selector->isUid()) {
            return static::createFromModel($selector->getWorkbench(), $selector->toString());
        } else {
            return parent::createFromSelector($selector, $constructorArguments);
        }
    }
    
    /**
     * 
     * @param BehaviorSelectorInterface $selector
     * @param MetaObjectInterface $object
     * @param AppSelectorInterface|string $appSelectorOrString
     * 
     * @return BehaviorInterface
     */
    public static function createForObject(BehaviorSelectorInterface $selector, MetaObjectInterface $object, $appSelectorOrString = null) : BehaviorInterface
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
        $instance = static::createForObject($selector, $object);
        $instance->importUxonObject($uxon);
        return $instance;
    }

    /**
     * @param WorkbenchInterface $workbench
     * @param string $uid
     * @return BehaviorInterface
     */
    public static function createFromModel(WorkbenchInterface $workbench, string $uid) : BehaviorInterface
    {
        $sheet = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.OBJECT_BEHAVIOR');
        $sheet->getColumns()->addMultiple([
            'OBJECT'
        ]);
        $sheet->getFilters()->addConditionFromAttribute($sheet->getMetaObject()->getUidAttribute(), $uid, ComparatorDataType::EQUALS);
        $sheet->dataRead();
        if ($sheet->isEmpty()) {
            throw new InvalidArgumentException('No behavior found for UID "' . $uid . '"');
        }
        $objUid = $sheet->getCellValue('OBJECT', 0);
        $obj = MetaObjectFactory::createFromString($workbench, $objUid);
        return $obj->getBehaviors()->getByUid($uid);
    }
}