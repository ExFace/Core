<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\ActionEffectInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * An action can have one or more effects, each indicating that it modifies a meta object.
 * 
 * Action effects allow the workbench to better understand, what actions do. In particular,
 * they indicate, what data might have changed after an action was performed. 
 * 
 * **NOTE:** an effect on a specific object, does not guarantee, that it will be changed every
 * time the action is performed - it only means, the action **can** modify that object.
 * 
 * Whether the modification takes place or not depends on the logic of the action, the input
 * data, behaviors of other effect object etc. - in many cases, we can't even really know
 * what exactly happens because actions may trigger logic in external systems, DB-triggers, 
 * etc. 
 * 
 * This is why action effects are part of the action model and can be manually added manually 
 * to tell the workbench, that the action is likely to effect an object even if that is 
 * not obvious.
 * 
 * @author andrej.kabachnik
 *
 */
class ActionEffect implements ActionEffectInterface
{
    use ImportUxonObjectTrait;
    
    private $name = null;
    
    private $action = null;
    
    private $effectedObjectAlias = null;
    
    private $effectedObject = null;
    
    private $effectedObjectRelationPath = null;
    
    /**
     * 
     * @param ActionInterface $action
     * @param string $name
     * @param MetaObjectInterface $effectedObject
     * @param MetaRelationPathInterface $relationPathToEffectedObject
     */
    public function __construct(ActionInterface $action, UxonObject $uxon)
    {
        $this->action = $action;
        $this->importUxonObject($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionEffectInterface::getName()
     */
    public function getName() : string
    {
        if ($this->name === null) {
            if ($this->getAction()->isDefinedInWidget()) {
                $this->name = $this->getAction()->getWidgetDefinedIn()->getCaption();
            }
            if ($this->name === null) {
                $this->name = $this->getAction()->getName();
            }
        }
        return $this->name ?? '';
    }
    
    /**
     * The name of the effect (name of the action by default).
     * 
     * This name will be used whenever action effects are listed - e.g. in offline action
     * queues, etc.
     * 
     * @uxon-property name
     * @uxon-type string
     * 
     * @param string $value
     * @return ActionEffectInterface
     */
    protected function setName(string $value) : ActionEffectInterface
    {
        $this->name = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionEffectInterface::getEffectedObject()
     */
    public function getEffectedObject() : MetaObjectInterface
    {
        if ($this->effectedObject === null) {
            if ($this->effectedObjectAlias !== null) {
                $this->effectedObject = MetaObjectFactory::createFromString($this->getWorkbench(), $this->effectedObjectAlias);
            } elseif ($this->effectedObjectRelationPath !== null) {
                $this->effectedObject = $this->getRelationPathToEffectedObject()->getEndObject();
            } else {
                throw new ActionConfigurationError($this->getAction(), 'Cannot determin meta object for action effect "' . $this->getName() . '": either specify `effected_object` or `relation_path_to_effected_object` in the configuration of the effect!');
            }
        }
        return $this->effectedObject;
    }
    
    /**
     * Full alias of the object effected by the action (with namespace)
     * 
     * @uxon-property effected_object
     * @uxon-type metamodel:object
     * 
     * @param string $selector
     * @return ActionEffectInterface
     */
    protected function setEffectedObject(string $selector) : ActionEffectInterface
    {
        $this->effectedObject = null;
        $this->effectedObjectAlias = $selector;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionEffectInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        return $this->action;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionEffectInterface::getRelationPathToEffectedObject()
     */
    public function getRelationPathToEffectedObject() : ?MetaRelationPathInterface
    {
        return $this->effectedObjectRelationPath === null ? null : RelationPathFactory::createFromString($this->getAction()->getMetaObject(), $this->effectedObjectRelationPath);
    }
    
    /**
     * Relation path from the actions object to the effected object
     * 
     * @uxon-property relation_path_to_effected_object
     * @uxon-type metamodel:relation
     * 
     * @param string $value
     * @return ActionEffectInterface
     */
    protected function setRelationPathToEffectedObject(string $value) : ActionEffectInterface
    {
        $this->effectedObject = null;
        $this->effectedObjectAlias = null;
        $this->effectedObjectRelationPath = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'name' => $this->getName()
        ]);
        if ($this->effectedObjectRelationPath !== null) {
            $uxon->setProperty('relation_path_to_effected_object', $this->effectedObjectRelationPath);
        } else {
            $uxon->setProperty('effected_object', $this->getEffectedObject()->getAliasWithNamespace());
        }
        return $uxon;
    }
    
    /**
     * 
     * @return WorkbenchInterface
     */
    public function getWorkbench() : WorkbenchInterface
    {
        return $this->getAction()->getWorkbench();
    }
}