<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Actions\ActionEffectInterface;
use exface\Core\CommonLogic\Actions\ActionEffect;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * Instantiates action effects
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class ActionEffectFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param ActionInterface $action
     * @param UxonObject $uxon
     * @return ActionEffectInterface
     */
    public static function createFromUxon(ActionInterface $action, UxonObject $uxon) : ActionEffectInterface
    {
        return new ActionEffect($action, $uxon);
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param MetaObjectInterface $object
     * @return ActionEffectInterface
     */
    public static function createForEffectedObject(ActionInterface $action, MetaObjectInterface $object, string $effectName = null) : ActionEffectInterface
    {
        $uxon = new UxonObject(['effected_object' => $object->getAliasWithNamespace()]);
        if ($effectName !== null) {
            $uxon->setProperty('name', $effectName);
        }
        return new ActionEffect($action, $uxon);
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param string|MetaObjectSelectorInterface $selectorOrString
     * @param string $effectName
     * @return ActionEffectInterface
     */
    public static function createForEffectedObjectAliasOrUid(ActionInterface $action, $selectorOrString, string $effectName = null) : ActionEffectInterface
    {
        $uxon = new UxonObject(['effected_object' => (string)$selectorOrString]);
        if ($effectName !== null) {
            $uxon->setProperty('name', $effectName);
        }
        return new ActionEffect($action, $uxon);
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param MetaRelationPathInterface $relationPathFromActionObject
     * @param string $effectName
     * @return ActionEffectInterface
     */
    public static function createForEffectedRelation(ActionInterface $action, MetaRelationPathInterface $relationPathFromActionObject, string $effectName = null) : ActionEffectInterface
    {
        $uxon = new UxonObject(['relation_path_to_effected_object' => $relationPathFromActionObject->toString()]);
        if ($effectName !== null) {
            $uxon->setProperty('name', $effectName);
        }
        return new ActionEffect($action, $uxon);
    }
}