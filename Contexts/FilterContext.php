<?php
namespace exface\Core\Contexts;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\Contexts\ContextLoadError;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;

class FilterContext extends AbstractContext
{

    private $conditions_by_object = null;
    
    private $updatedObjectIds = [];
    
    private $conditionsUxon = null;

    /**
     * Returns an array with all conditions from the current context
     * 
     * TODO Modify to look for possible related objects and rebase() their conditions!
     * Currently we only look for conitions based on direct attributes of the object given.
     *
     * @param MetaObjectInterface $object            
     * @return Condition[] 
     */
    public function getConditions(MetaObjectInterface $object = NULL)
    {
        if ($this->conditions_by_object === null || ($object !== null && $this->conditions_by_object[$object->getId()] === null)) {
            $uxon = $this->conditionsUxon;
            if ($uxon === null || $uxon->isEmpty() === true) {
                return [];
            }
            
            if ($object !== null && $uxon->hasProperty($object->getId()) === false) {
                return [];
            }
            
            if ($object !== null) {
                foreach($this->getObjectIds($object) as $object_id) {
                    $objectConditions = $this->getConditionsFromUxon($uxon, $object_id)[$object_id];
                    if (empty($objectConditions) === false) {
                        $this->conditions_by_object[$object_id] = $objectConditions;
                    }
                }
            } else {
                $this->conditions_by_object = $this->getConditionsFromUxon($uxon);
            }
        }
        
        $array = [];
        if ($object !== null) {
            // Get object ids of the given object and all its parents
            $ids = $this->getObjectIds($object);
            // Look for filter conditions for these objects
            foreach ($ids as $object_id) {
                if (is_array($this->conditions_by_object[$object_id])) {
                    // If the condition was created based on another object, we need to rebase it.
                    // FIXME The current version only supports inherited attributes, for which the rebase(relation_path_to_new_base_object) does not work,
                    // as there is no relation path. So this is a temporal work-around with a manuel rebase. There are different long-term solutions possible.
                    // Apart from that the condition produced here references the object asked for ($object) while it's attribute references the object inherited
                    // from. The question, if inherited attributes should retain the parent object is still open - see object::extendFromObjectId()
                    // 1) Ensure, there is a relation to the parent object and thus a relation path to rebase. However, the rebase() should not actually change
                    // The alias of the attribute or it's relation path if the parent object does not have it's own data address
                    // 2) Create an alternative rebase() method, that would work with objects. This would probably be harder to understand.
                    if ($object_id !== $object->getId()) {
                        foreach ($this->conditions_by_object[$object_id] as $condition) {
                            $exface = $this->getWorkbench();
                            $new_expresseion = ExpressionFactory::createFromString($exface, $condition->getExpression()->toString(), $object);
                            $condition = ConditionFactory::createFromExpression($exface, $new_expresseion, $condition->getValue(), $condition->getComparator());
                            $array[] = $condition;
                        }
                    } else {
                        $array = array_merge($array, $this->conditions_by_object[$object_id]);
                    }
                }
            }
        } else {
            foreach ($this->conditions_by_object as $object_id => $conditions) {
                foreach ($conditions as $condition) {
                    $array[] = $condition;
                }
            }
        }
        return $array;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return string[]
     */
    private function getObjectIds(MetaObjectInterface $object) : array
    {
        return array_merge(array($object->getId()), $object->getParentObjectsIds());
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @param string $filterObjectId
     * @return ConditionalExpressionInterface[][]
     */
    private function getConditionsFromUxon(UxonObject $uxon, string $filterObjectId = null) : array
    {
        $conditions = [];
        if ($uxon !== null && $uxon->isEmpty() === false) {
            $exface = $this->getWorkbench();
            foreach ($uxon->getPropertiesAll() as $oId => $uxonConditions) {
                if ($filterObjectId !== null && $oId !== $filterObjectId) {
                    continue;
                }
                foreach ($uxonConditions as $uxonCondition) {
                    try {
                        $conditions[$oId][] = ConditionFactory::createFromUxon($exface, $uxonCondition);
                    } catch (\Throwable $e) {
                        // ignore context that cannot be instantiated!
                    }
                }
            }
        }
        return $conditions;
    }

    /**
     * Adds a condition to the current context
     *
     * @param Condition $condition            
     * @return \exface\Core\Contexts\FilterContext
     */
    public function addCondition(Condition $condition)
    {
        $objectId = $condition->getExpression()->getMetaObject()->getId();
        if (($this->conditions_by_object === null || $this->conditions_by_object[$objectId] === null) && $this->isEmpty() === false) {
            $this->conditions_by_object[$objectId] = $this->getConditionsFromUxon($this->conditionsUxon, $objectId)[$objectId];
        }
        $this->conditions_by_object[$objectId][$condition->getExpression()->toString()] = $condition;
        $this->updatedObjectIds[] = $objectId;
        return $this;
    }

    /**
     * Removes a given condition from the current context
     *
     * @param Condition $condition            
     * @return \exface\Core\Contexts\FilterContext
     */
    public function removeCondition(Condition $condition)
    {
        $object = $condition->getExpression()->getMetaObject();
        if (empty($this->getConditions($object)) === false) {
            unset($this->conditions_by_object[$object->getId()][$condition->getExpression()->toString()]);
            $this->updatedObjectIds[] = $object->getId();
        }
        return $this;
    }

    /**
     * Removes all conditions based on a certain attribute
     *
     * @param MetaAttributeInterface $attribute            
     * @return \exface\Core\Contexts\FilterContext
     */
    public function removeConditionsForAttribute(MetaAttributeInterface $attribute)
    {
        $objectConditions = $this->getConditions($attribute->getObject());
        if (empty($objectConditions) === false) {
            foreach ($objectConditions as $condition) {
                if ($condition->getAttributeAlias() === $attribute->getAliasWithRelationPath()) {
                    $this->removeCondition($condition);
                }
            }
        }
        return $this;
    }
    
    /**
     * Removes all conditions set for the given object
     *
     * @param MetaObjectInterface $object
     * @return \exface\Core\Contexts\FilterContext
     */
    public function removeConditionsForObject(MetaObjectInterface $object)
    {
        unset($this->conditions_by_object[$object->getId()]);
        if ($this->conditionsUxon !== null) {
            $this->conditionsUxon->unsetProperty($object->getId());
        }
        $this->updatedObjectIds[$object->getId()];
        return $this;
    }

    /**
     * Clears all conditions from this context
     *
     * @return \exface\Core\Contexts\FilterContext
     */
    public function removeAllConditions()
    {
        if (empty($this->conditions_by_object) === false) {
            $this->updatedObjectIds = array_keys($this->conditions_by_object);
        } elseif ($this->conditionsUxon !== null) {
            $this->updatedObjectIds = array_keys($this->conditionsUxon->toArray());
        }
        $this->conditions_by_object = array();
        $this->conditionsUxon = new UxonObject();
        return $this;
    }

    /**
     * Returns an array with UXON objects for each condition in the context
     *
     * @return UxonObject
     */
    public function exportUxonObject()
    {
        $updatedConditions = [];
        // If there are updated object conditions, we know, that $this->conditions_by_object was populated.
        foreach ($this->updatedObjectIds as $objectId) {
            $updatedConditions[$objectId] = $this->conditions_by_object[$objectId];                
        }
        // Re-read current session data
        $this->getScope()->reloadContext($this);
        
        $uxon = $this->conditionsUxon ?? (new UxonObject());
        foreach ($updatedConditions as $objectId => $conditions) {
            $uxon->unsetProperty($objectId);
            foreach ($conditions as $condition) {
                $uxon->appendToProperty($objectId, $condition->exportUxonObject());
            }
        }
        if ($uxon->isEmpty() === false) {
            return (new UxonObject())->setProperty('conditions', $uxon);
        } else {
            return new UxonObject();
        }
    }

    /**
     * Loads an array of conditions in UXON representation into the context
     *
     * @param UxonObject $uxon            
     * @throws ContextLoadError
     * @return \exface\Core\Contexts\FilterContext
     */
    public function importUxonObject(UxonObject $uxon)
    {
        $this->conditionsUxon = $uxon->getProperty('conditions');
        $this->conditions_by_object = null;
        return $this;
    }

    public function isEmpty()
    {
        return empty($this->conditions_by_object) === true && ($this->conditionsUxon === null || $this->conditionsUxon->isEmpty()) ? true : false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon()
    {
        return 'filter';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getName()
     */
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.FILTER.NAME');
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasChanges() : bool
    {
        return empty($this->updatedObjectIds) === false;
    }
}
?>