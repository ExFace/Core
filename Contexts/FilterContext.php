<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Contexts\ContextLoadError;
use exface\Core\CommonLogic\Contexts\AbstractContext;

class FilterContext extends AbstractContext
{

    private $conditions_by_object = array();

    /**
     * Returns an array with all conditions from the current context
     *
     * @param object $object            
     * @return Condition[] TODO Modify to look for possible related objects and rebase() their conditions!
     *         Ccurrently we only look for conitions based on direct attributes of the object given.
     */
    public function getConditions(Object $object = NULL)
    {
        $array = array();
        if ($object) {
            // Get object ids of the given object and all its parents
            $ids = array_merge(array(
                $object->getId()
            ), $object->getParentObjectsIds());
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
                    if ($object_id != $object->getId()) {
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
     * Adds a condition to the current context
     *
     * @param Condition $condition            
     * @return \exface\Core\Contexts\FilterContext
     */
    public function addCondition(Condition $condition)
    {
        $this->conditions_by_object[$condition->getExpression()->getMetaObject()->getId()][$condition->getExpression()->toString()] = $condition;
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
        unset($this->conditions_by_object[$condition->getExpression()->getMetaObject()->getId()][$condition->getExpression()->toString()]);
        return $this;
    }

    /**
     * Removes all conditions based on a certain attribute
     *
     * @param attribute $attribute            
     * @return \exface\Core\Contexts\FilterContext
     */
    public function removeConditionsForAttribute(Attribute $attribute)
    {
        if (is_array($this->conditions_by_object[$attribute->getObjectId()])) {
            foreach ($this->conditions_by_object[$attribute->getObjectId()] as $id => $condition) {
                if ($condition->getAttributeAlias() == $attribute->getAliasWithRelationPath()) {
                    unset($this->conditions_by_object[$attribute->getObjectId()][$id]);
                }
            }
        }
        return $this;
    }

    /**
     * Clears all conditions from this context
     *
     * @return \exface\Core\Contexts\FilterContext
     */
    public function removeAllConditions()
    {
        $this->conditions_by_object = array();
        return $this;
    }

    /**
     * Returns an array with UXON objects for each condition in the context
     *
     * @return UxonObject
     */
    public function exportUxonObject()
    {
        $uxon = $this->getWorkbench()->createUxonObject();
        if (! $this->isEmpty()) {
            $uxon->conditions = array();
            foreach ($this->getConditions() as $condition) {
                $uxon->conditions[] = $condition->exportUxonObject();
            }
        }
        return $uxon;
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
        $exface = $this->getWorkbench();
        if (is_array($uxon->conditions)) {
            foreach ($uxon->conditions as $uxon_condition) {
                try {
                    $this->addCondition(ConditionFactory::createFromStdClass($exface, $uxon_condition));
                } catch (ErrorExceptionInterface $e) {
                    // ignore context that cannot be instantiated!
                }
            }
        } elseif (! is_null($uxon->conditions)) {
            throw new ContextLoadError($this, 'Cannot load filter contexts: Expecting an array of UXON objects, ' . gettype($uxon->conditions) . ' given instead!');
        }
        return $this;
    }

    public function isEmpty()
    {
        if (count($this->conditions_by_object) > 0) {
            return false;
        } else {
            return true;
        }
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
}
?>