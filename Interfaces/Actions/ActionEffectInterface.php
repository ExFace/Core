<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * An action can have one or more effects, each indicating that it modifies a meta object.
 * 
 * Action effects allow the workbench to better understand, what actions do. In particular,
 * they indicate, what data might have changed after an action was performed. 
 * 
 * **NOTE:** an effect on a specific object, does not guarantee, that it will be changed every
 * time the action is performed - it only means, the action **can** modify that object.
 *
 * @author Andrej Kabachnik
 *        
 */
interface ActionEffectInterface extends iCanBeConvertedToUxon
{
    /**
     * Returns the action that causes the effect.
     * 
     * @return ActionInterface
     */
    public function getAction() : ActionInterface;
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getEffectedObject() : MetaObjectInterface;
    
    /**
     * Returns the relation path from the actions object to the effected object.
     * 
     * @return MetaRelationPathInterface|NULL
     */
    public function getRelationPathToEffectedObject() : ?MetaRelationPathInterface;
    
    /**
     * Returns the name of the effect. 
     * 
     * @return string
     */
    public function getName() : string;
}