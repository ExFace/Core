<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * A condition group contains one or more conditions and/or other (nested) condition groups combined by one logical operator,
 * e.g. OR( AND( cond1 = val1, cond2 < val2 ), cond3 = val3 ).
 * 
 * @author Andrej Kabachnik
 *
 */
interface ConditionGroupInterface extends ConditionalExpressionInterface
{
    /**
     * Adds a condition to the group
     *
     * @param ConditionInterface $condition
     * @return ConditionGroupInterface
     */
    public function addCondition(ConditionInterface $condition) : ConditionGroupInterface;
    
    /**
     * Creates a new condition and adds it to this group
     *
     * @param ExpressionInterface $expression
     * @param mixed $value
     * @param string $comparator
     * @return ConditionGroupInterface
     */
    public function addConditionFromExpression(ExpressionInterface $expression, $value = NULL, string $comparator = EXF_COMPARATOR_IS) : ConditionGroupInterface;
    
    /**
     * Creates a new condition and adds it to this condition group.
     *
     * @param string $column_name
     * @param mixed $value
     * @param string $comparator
     * @return ConditionGroupInterface
     */
    public function addConditionsFromString(MetaObjectInterface $base_object, string $expression_string, $value, string $comparator = null) : ConditionGroupInterface;
    
    /**
     * Adds a subgroup to this group.
     *
     * @param ConditionGroupInterface $group
     * @return ConditionGroupInterface
     */
    public function addNestedGroup(ConditionGroupInterface $group) : ConditionGroupInterface;
    
    /**
     * Returns an array of conditions directly contained in this group (not in the subgroups!).
     * Returns an empty array if the group does not have conditions.
     *
     * @return ConditionInterface[]
     */
    public function getConditions() : array;
    
    /**
     * Returns a numeric flat array with all conditions within this condition group and it's nested subgroups.
     *
     * NOTE: This array cannot be used to evaluate the condition group, as all information about operators in
     * nested groups is lost, but this method can be usefull to search for conditions with certain properties
     * (e.g. an attribute, a comparator, etc.)
     *
     * @return ConditionGroupInterface[]
     */
    public function getConditionsRecursive() : array;
    
    /**
     * Returns an array of condition groups directly contained in this group (not in the subgroups!).
     * Returns an empty array if the group does not have subgroups.
     *
     * @return ConditionGroupInterface[]
     */
    public function getNestedGroups() : array;
    
    /**
     * Returns the logical operator of the group.
     * Operators are defined by the EXF_LOGICAL_xxx constants.
     *
     * @return string
     */
    public function getOperator() : string;
    
    /**
     * Returns a condition group with the same conditions, but based on a related object specified by the given relation path.
     *
     * @see ExpressionInterface::rebase()
     *
     * @param string $relation_path_to_new_base_object
     * @param callable $filter_callback
     * @return ConditionGroupInterface
     */
    public function rebase(string $relation_path_to_new_base_object, callable $filter_callback = null) : ConditionGroupInterface;
    
    /**
     * Removes a given condition from this condition group (not from the nested groups!)
     *
     * @param ConditionInterface $condition
     * @return ConditionGroupInterface
     */
    public function removeCondition(ConditionInterface $condition) : ConditionGroupInterface;
    
    /**
     * Removes all conditions and nested groups from this condition group thus resetting it completely
     *
     * @return ConditionGroupInterface
     */
    public function removeAll() : ConditionGroupInterface;
    
    /**
     * Returns the number of conditions in this group.
     * If $recursive is TRUE, conditions in nested condition groups will be counted to,
     * otherwise just the direct conditions of the group will be included.
     *
     * @param bool $recursive
     * @return int
     */
    public function countConditions(bool $recursive = true) : int;
    
    /**
     * Returns the number of nested condition groups in this group.
     * If $recursive is TRUE, condition groups within the nested groups
     * will be counted to, otherwise just the direct subgroups of the group will be included.
     *
     * @param boolean $recursive
     * @return int
     */
    public function countNestedGroups(bool $recursive = true) : int;
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @param int $row_number
     * @return bool
     */
    public function evaluate(DataSheetInterface $data_sheet = null, int $row_number = null) : bool;
}

