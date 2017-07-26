<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Model\ExpressionRebaseImpossibleError;
use exface\Core\Interfaces\iCanBeCopied;

/**
 * A condition group contains one or more conditions and/or other (nested) condition groups combined by one logical operator,
 * e.g.
 * OR( AND( cond1 = val1, cond2 < val2 ), cond3 = val3 ).
 *
 * @author Andrej Kabachnik
 *        
 */
class ConditionGroup implements iCanBeConvertedToUxon, iCanBeCopied
{

    // Properties to be duplicated on copy()
    private $operator = NULL;

    private $conditions = array();

    private $nested_groups = array();

    // Properties NOT to be dublicated on copy()
    private $exface = NULL;

    function __construct(\exface\Core\CommonLogic\Workbench $exface, $operator = EXF_LOGICAL_AND)
    {
        $this->exface = $exface;
        $this->setOperator($operator);
    }

    /**
     * Adds a condition to the group
     *
     * @param Condition $condition            
     * @return \exface\Core\CommonLogic\Model\ConditionGroup
     */
    public function addCondition(Condition $condition)
    {
        // TODO check, if the same condition already exists. There is no need to allow duplicate conditions in the same group!
        $this->conditions[] = $condition;
        return $this;
    }

    /**
     * Creates a new condition and adds it to this group
     *
     * @param expression $expression            
     * @param string $value            
     * @param string $comparator            
     * @return \exface\Core\CommonLogic\Model\ConditionGroup
     */
    public function addConditionFromExpression(Expression $expression, $value = NULL, $comparator = EXF_COMPARATOR_IS)
    {
        if (! is_null($value) && $value !== '') {
            $condition = ConditionFactory::createFromExpression($this->exface, $expression, $value, $comparator);
            $this->addCondition($condition);
        }
        return $this;
    }

    /**
     * Creates a new condition and adds it to this condition group.
     * TODO Refactor to use ConditionFactory::createFromString() and process special prefixes and so on there
     *
     * @param string $column_name            
     * @param mixed $value            
     * @param string $comparator            
     * @return ConditionGroup
     */
    function addConditionsFromString(Object $base_object, $expression_string, $value, $comparator = null)
    {
        $value = trim($value);
                
        // A special feature for string condition is the possibility to specify a comma separated list of attributes in one element
        // of the filters array, wich means that at least one of the attributes should match the value
        // IDEA move this logic to the condition, so it can be used generally
        $expression_strings = explode(EXF_LIST_SEPARATOR, $expression_string);
        if (count($expression_strings) > 1) {
            $group = ConditionGroupFactory::createEmpty($this->exface, EXF_LOGICAL_OR);
            foreach ($expression_strings as $f) {
                $group->addCondition(ConditionFactory::createFromString($base_object, $f, $value, $comparator));
            }
            $this->addNestedGroup($group);
        } elseif (! is_null($value) && $value !== '') {
            $this->addCondition(ConditionFactory::createFromString($base_object, $expression_string, $value, $comparator));
        }
        
        return $this;
    }

    /**
     * Adds a subgroup to this group.
     *
     * @param ConditionGroup $group            
     * @return \exface\Core\CommonLogic\Model\ConditionGroup
     */
    public function addNestedGroup(ConditionGroup $group)
    {
        $this->nested_groups[] = $group;
        return $this;
    }

    /**
     * Returns an array of conditions directly contained in this group (not in the subgroups!).
     * Returns an empty array if the group does not have conditions.
     *
     * @return Condition[]
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Returns a numeric flat array with all conditions within this condition group and it's nested subgroups.
     *
     * NOTE: This array cannot be used to evaluate the condition group, as all information about operators in
     * nested groups is lost, but this method can be usefull to search for conditions with certain properties
     * (e.g. an attribute, a comparator, etc.)
     *
     * @return \exface\Core\CommonLogic\Model\Condition[]
     */
    public function getConditionsRecursive()
    {
        $result = $this->getConditions();
        foreach ($this->getNestedGroups() as $group) {
            $result = array_merge($result, $group->getConditionsRecursive());
        }
        return $result;
    }

    /**
     * Returns an array of condition groups directly contained in this group (not in the subgroups!).
     * Returns an empty array if the group does not have subgroups.
     *
     * @return ConditionGroup[]
     */
    public function getNestedGroups()
    {
        return $this->nested_groups;
    }

    /**
     * Returns the logical operator of the group.
     * Operators are defined by the EXF_LOGICAL_xxx constants.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Sets the logical operator of the group.
     * Operators are defined by the EXF_LOGICAL_xxx constants.
     *
     * @param string $value            
     */
    public function setOperator($value)
    {
        // TODO Check, if the group operator is one of the allowed logical operators
        if ($value) {
            $this->operator = $value;
        }
    }

    /**
     * Returns a condition group with the same conditions, but based on a related object specified by the given relation path.
     *
     * @see expression::rebase()
     *
     * @param string $relation_path_to_new_base_object            
     * @return ConditionGroup
     */
    public function rebase($relation_path_to_new_base_object, $remove_conditions_not_matching_the_path = false)
    {
        // Do nothing, if the relation path is empty (nothing to rebase...)
        if (! $relation_path_to_new_base_object)
            return $this;
        
        $result = ConditionGroupFactory::createEmpty($this->exface, $this->getOperator());
        foreach ($this->getConditions() as $condition) {
            // Remove conditions not matching the path if required by user
            if ($remove_conditions_not_matching_the_path && $condition->getExpression()->isMetaAttribute()) {
                if (strpos($condition->getExpression()->toString(), $relation_path_to_new_base_object) !== 0) {
                    continue;
                }
            }
            
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

    public function getWorkbench()
    {
        return $this->exface;
    }

    public function toString()
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

    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->operator = $this->getOperator();
        $uxon->conditions = array();
        $uxon->nested_groups = array();
        foreach ($this->getConditions() as $cond) {
            $uxon->conditions[] = $cond->exportUxonObject();
        }
        foreach ($this->getNestedGroups() as $group) {
            $uxon->nested_groups[] = $group->exportUxonObject();
        }
        return $uxon;
    }

    public function importUxonObject(UxonObject $uxon)
    {
        $this->setOperator($uxon->getProperty('operator'));
        if ($uxon->hasProperty('conditions')) {
            foreach ($uxon->getProperty('conditions') as $cond) {
                $this->addCondition(ConditionFactory::createFromObjectOrArray($this->exface, $cond));
            }
        }
        if ($uxon->hasProperty('nested_groups')) {
            foreach ($uxon->getProperty('nested_groups') as $group) {
                $this->addNestedGroup(ConditionGroupFactory::createFromObjectOrArray($this->exface, $group));
            }
        }
    }

    public function getModel()
    {
        return $this->getWorkbench()->model();
    }

    public function isEmpty()
    {
        if (count($this->getConditions()) == 0 && count($this->getNestedGroups()) == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Removes a given condition from this condition group (not from the nested groups!)
     *
     * @param Condition $condition            
     * @return Condition
     */
    public function removeCondition(Condition $condition)
    {
        foreach ($this->getConditions() as $i => $cond) {
            if ($cond == $condition) {
                unset($this->conditions[$i]);
            }
        }
        return $this;
    }

    /**
     * Removes all conditions and nested groups from this condition group thus resetting it completely
     *
     * @return ConditionGroup
     */
    public function removeAll()
    {
        $this->conditions = array();
        $this->nested_groups = array();
        return $this;
    }

    /**
     *
     * @return ConditionGroup
     */
    public function copy()
    {
        $exface = $this->getWorkbench();
        $copy = ConditionGroupFactory::createFromUxon($exface, $this->exportUxonObject());
        return $copy;
    }

    /**
     * Returns the number of conditions in this group.
     * If $recursive is TRUE, conditions in nested condition groups will be counted to,
     * otherwise just the direct conditions of the group will be included.
     *
     * @param boolean $recursive            
     * @return string
     */
    public function countConditions($recursive = true)
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
     * Returns the number of nested condition groups in this group.
     * If $recursive is TRUE, condition groups within the nested groups
     * will be counted to, otherwise just the direct subgroups of the group will be included.
     *
     * @param boolean $recursive            
     * @return integer
     */
    public function countNestedGroups($recursive = true)
    {
        $result = count($this->getNestedGroups());
        if ($recursive) {
            foreach ($this->getNestedGroups() as $group) {
                $result += $group->countNestedGroups(true);
            }
        }
        return $result;
    }
}
?>