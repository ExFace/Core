<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Model\ExpressionRebaseImpossibleError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;

/**
 * Default implementation of the ConditionGroupInterface
 * 
 * @see ConditionGroupInterface
 *
 * @author Andrej Kabachnik
 *        
 */
class ConditionGroup implements ConditionGroupInterface
{

    // Properties to be duplicated on copy()
    private $operator = NULL;

    private $conditions = array();

    private $nested_groups = array();

    // Properties NOT to be dublicated on copy()
    private $exface = NULL;

    function __construct(\exface\Core\CommonLogic\Workbench $exface, string $operator = EXF_LOGICAL_AND)
    {
        $this->exface = $exface;
        $this->setOperator($operator);
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::addCondition()
     */
    public function addCondition(ConditionInterface $condition) : ConditionGroupInterface
    {
        // TODO check, if the same condition already exists. There is no need to allow duplicate conditions in the same group!
        $this->conditions[] = $condition;
        return $this;
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::addConditionFromExpression()
     */
    public function addConditionFromExpression(ExpressionInterface $expression, $value = NULL, string $comparator = EXF_COMPARATOR_IS) : ConditionGroupInterface
    {
        if (! is_null($value) && $value !== '') {
            $condition = ConditionFactory::createFromExpression($this->exface, $expression, $value, $comparator);
            $this->addCondition($condition);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionGroupInterface::addCondition()
     */
    public function addConditionsFromString(MetaObjectInterface $base_object, string $expression_string, $value, string $comparator = null) : ConditionGroupInterface
    {
        $value = trim($value);
                
        // A special feature for string condition is the possibility to specify a comma separated list of attributes in one element
        // of the filters array, wich means that at least one of the attributes should match the value
        // IDEA move this logic to the condition, so it can be used generally
        $expression_strings = explode(EXF_LIST_SEPARATOR, $expression_string);
        if (count($expression_strings) > 1) {
            $group = ConditionGroupFactory::createEmpty($this->exface, EXF_LOGICAL_OR);
            foreach ($expression_strings as $f) {
                $group->addCondition(ConditionFactory::createFromExpressionString($base_object, $f, $value, $comparator));
            }
            $this->addNestedGroup($group);
        } elseif (! is_null($value) && $value !== '') {
            $this->addCondition(ConditionFactory::createFromExpressionString($base_object, $expression_string, $value, $comparator));
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::addNestedGroup()
     */
    public function addNestedGroup(ConditionGroupInterface $group) : ConditionGroupInterface
    {
        $this->nested_groups[] = $group;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionGroupInterface::getConditions()
     */
    public function getConditions() : array
    {
        return $this->conditions;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionGroupInterface::getConditionsRecursive()
     */
    public function getConditionsRecursive() : array
    {
        $result = $this->getConditions();
        foreach ($this->getNestedGroups() as $group) {
            $result = array_merge($result, $group->getConditionsRecursive());
        }
        return $result;
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::getNestedGroups()
     */
    public function getNestedGroups() : array
    {
        return $this->nested_groups;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionGroupInterface::addCondition()
     */
    public function getOperator() : string
    {
        return $this->operator;
    }

    /**
     * Sets the logical operator of the group.
     * Operators are defined by the EXF_LOGICAL_xxx constants.
     *
     * @param string $value
     * @return ConditionGroupInterface            
     */
    protected function setOperator(string $value) : ConditionGroupInterface
    {
        // TODO Check, if the group operator is one of the allowed logical operators
        if ($value) {
            $this->operator = $value;
        }
        return $this;
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::rebase()
     */
    public function rebase(string $relation_path_to_new_base_object, callable $filter_callback = null) : ConditionGroupInterface
    {
        // Do nothing, if the relation path is empty (nothing to rebase...)
        if (! $relation_path_to_new_base_object)
            return $this;
        
        $result = ConditionGroupFactory::createEmpty($this->exface, $this->getOperator());
        foreach ($this->getConditions() as $condition) {
            // Remove conditions not matching the filter
            if (! is_null($filter_callback) && call_user_func($filter_callback, $condition, $relation_path_to_new_base_object) === false) {
                continue;
            }
            // Remove conditions not matching the path if required by user
            /*
            if ($remove_conditions_not_matching_the_path && $condition->getExpression()->isMetaAttribute()) {
                if (stripos($condition->getExpression()->toString(), $relation_path_to_new_base_object) !== 0) {
                    continue;
                }
            }*/
            
            // Rebase the expression behind the condition and create a new condition from it
            try {
                $new_expression = $condition->getExpression()->rebase($relation_path_to_new_base_object);
            } catch (ExpressionRebaseImpossibleError $e) {
                // Silently omit conditions, that cannot be rebased
                continue;
            }
            $new_condition = ConditionFactory::createFromExpression($this->exface, $new_expression, $condition->getValue(), $condition->getComparator());
            $result->addCondition($new_condition);
        }
        
        foreach ($this->getNestedGroups() as $group) {
            $result->addNestedGroup($group->rebase($relation_path_to_new_base_object));
        }
        
        return $result;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionalExpressionInterface::toString()
     */
    public function toString() : string
    {
        $result = '';
        foreach ($this->getConditions() as $cond) {
            $result .= ($result ? ' ' . $this->getOperator() . ' ' : '') . $cond->toString();
        }
        foreach ($this->getNestedGroups() as $group) {
            $result .= ($result ? ' ' . $this->getOperator() . ' ' : '') . '( ' . $group->toString() . ' )';
        }
        return $result;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('operator', $this->getOperator());
        foreach ($this->getConditions() as $cond) {
            $uxon->appendToProperty('conditions', $cond->exportUxonObject());
        }
        foreach ($this->getNestedGroups() as $group) {
            $uxon->appendToProperty('nested_groups', $group->exportUxonObject());
        }
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        $this->setOperator($uxon->getProperty('operator'));
        if ($uxon->hasProperty('conditions')) {
            foreach ($uxon->getProperty('conditions') as $cond) {
                $this->addCondition(ConditionFactory::createFromUxon($this->exface, $cond));
            }
        }
        if ($uxon->hasProperty('nested_groups')) {
            foreach ($uxon->getProperty('nested_groups') as $group) {
                $this->addNestedGroup(ConditionGroupFactory::createFromUxon($this->exface, $group));
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionalExpressionInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        if (count($this->getConditions()) == 0 && count($this->getNestedGroups()) == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::removeCondition()
     */
    public function removeCondition(ConditionInterface $condition) : ConditionGroupInterface
    {
        foreach ($this->getConditions() as $i => $cond) {
            if ($cond == $condition) {
                unset($this->conditions[$i]);
            }
        }
        return $this;
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::removeAll()
     */
    public function removeAll() : ConditionGroupInterface
    {
        $this->conditions = array();
        $this->nested_groups = array();
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     */
    public function copy()
    {
        $exface = $this->getWorkbench();
        $copy = ConditionGroupFactory::createFromUxon($exface, $this->exportUxonObject());
        return $copy;
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::countConditions()
     */
    public function countConditions(bool $recursive = true) : int
    {
        $result = count($this->getConditions());
        if ($recursive) {
            foreach ($this->getNestedGroups() as $group) {
                $result += $group->countConditions(true);
            }
        }
        return $result;
    }

    /**
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::countNestedGroups()
     */
    public function countNestedGroups(bool $recursive = true) : int
    {
        $result = count($this->getNestedGroups());
        if ($recursive) {
            foreach ($this->getNestedGroups() as $group) {
                $result += $group->countNestedGroups(true);
            }
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see ConditionalExpressionInterface::toConditionGroup()
     */
    public function toConditionGroup(): ConditionGroupInterface
    {
        return $this;
    }
}
?>