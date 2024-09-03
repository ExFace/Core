<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\DataSheets\DataColumnInterface;

/**
 * A condition group contains one or more conditions and/or other (nested) condition groups combined by 
 * one logical operator, e.g. `OR( AND( cond1 = val1, cond2 < val2 ), cond3 = val3 )`.
 * 
 * @see ConditionInterface
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
     * @param bool|NULL $ignoreEmptyValue
     * @return ConditionGroupInterface
     */
    public function addConditionFromExpression(ExpressionInterface $expression, $value = NULL, string $comparator = EXF_COMPARATOR_IS, bool $ignoreEmptyValue = null) : ConditionGroupInterface;
    
    /**
     * Creates a new condition and adds it to the filters of this data sheet to the root condition group.
     *
     * @param string $expression_string
     * @param mixed $value
     * @param string $comparator
     * @param bool|NULL $ignoreEmptyValue
     * @return ConditionGroupInterface
     */
    public function addConditionFromString(string $expression_string, $value, string $comparator = null, bool $ignoreEmptyValue = null) : ConditionGroupInterface;
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @param mixed $value
     * @param string $comparator
     * @param bool|NULL $ignoreEmptyValue
     * @return ConditionGroupInterface
     */
    public function addConditionFromAttribute(MetaAttributeInterface $attribute, $value, string $comparator = null, bool $ignoreEmptyValue = null) : ConditionGroupInterface;
    
    /**
     * Adds an filter based on a list of values: the column value must equal one of the values in the list.
     * The list may be an array or a comma separated string
     * FIXME move to ConditionGroup, so it can be used for nested groups too!
     *
     * @param string|ExpressionInterface $expressionString
     * @param string|array $values
     * @param bool|NULL $ignoreEmptyValue
     * @return ConditionGroupInterface
     */
    public function addConditionFromValueArray($expressionOrString, $value_list, bool $ignoreEmptyValue = null) : ConditionGroupInterface;
    
    /**
     *
     * @param DataColumnInterface $column
     * @param bool|NULL $ignoreEmptyValue
     * @return ConditionGroupInterface
     */
    public function addConditionFromColumnValues(DataColumnInterface $column, bool $ignoreEmptyValue = null) : ConditionGroupInterface;
    
    /**
     * Adds a subgroup to this condition group.
     *
     * @param ConditionGroupInterface $group
     * @return ConditionGroupInterface
     */
    public function addNestedGroup(ConditionGroupInterface $group) : ConditionGroupInterface;
    
    /**
     * Adds a subgroup with a given logical operator and returns it for chaining.
     * 
     * @param string $operator
     * @param bool|NULL $ignoreEmptyValues
     * @return ConditionGroupInterface
     */
    public function addNestedGroupFromString(string $operator, bool $ignoreEmptyValues = null) : ConditionGroupInterface;
    
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
     * @return ConditionInterface[]
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
     * @param callable $conditionFilterCallback
     * @return ConditionGroupInterface
     */
    public function rebase(string $relation_path_to_new_base_object, callable $conditionFilterCallback = null) : ConditionGroupInterface;
    
    /**
     * Returns a condition group based on the new object, that only includes conditions, that
     * are applicable to the new object.
     * 
     * @param MetaObjectInterface $newObject
     * @return ConditionGroupInterface
     */
    public function rebaseWithMatchingAttributesOnly(MetaObjectInterface $newObject) : ConditionGroupInterface;
    
    /**
     * Returns a condition group based on the new object applying a custom callback to every 
     * condition to transform it.
     * 
     * The callback must return a new condition based on the $newObject and must 
     * have the following interface: 
     * 
     * ```
     *  function(ConditionInterface $condition) : ConditionInterface
     *  
     * ```
     * 
     * If the callback returns `null`, the condition will be ignored.
     * 
     * @param MetaObjectInterface $newObject
     * @param callable $conditionTransformer
     * @return ConditionGroupInterface
     */
    public function rebaseCustom(MetaObjectInterface $newObject, callable $conditionTransformer) : ConditionGroupInterface;
    
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionalExpressionInterface::isEmpty()
     */
    public function isEmpty(bool $checkValues = false) : bool;
    
    /**
     * 
     * @param ConditionInterface $conditionToReplace
     * @param ConditionInterface $replaceWith
     * @param bool $recursive
     * @return ConditionGroupInterface
     */
    public function replaceCondition(ConditionInterface $conditionToReplace, ConditionInterface $replaceWith, bool $recursive = true) : ConditionGroupInterface;
    
    /**
     * Returns a new condition group combining this one and the given via OR
     * 
     * @see with() for more details
     * 
     * @param ConditionalExpressionInterface $conditionOrGroup
     * @return ConditionGroupInterface
     */
    public function withOR(ConditionalExpressionInterface $conditionOrGroup) : ConditionGroupInterface;
    
    /**
     * Returns a new condition group combining this one and the given via AND
     * 
     * @see with() for more details
     * 
     * @param ConditionalExpressionInterface $conditionOrGroup
     * @return ConditionGroupInterface
     */
    public function withAND(ConditionalExpressionInterface $conditionOrGroup) : ConditionGroupInterface;
    
    /**
     * Returns a new condition group combining this one and the given via the provided operator.
     * 
     * Allows to quickly combine different condition groups: e.g. adding an OR-group to an AND-group, etc.
     * 
     * Examples:
     * - AND(a, b)::withAND(OR(c,d)) --> AND(a, b, OR(c, d))
     * - OR(a, b)::withAND(OR(c, d)) --> AND(OR(a, b), OR(c, d))
     * 
     * @param string $operator
     * @param ConditionalExpressionInterface $conditionOrGroup
     * @return ConditionGroupInterface
     */
    public function with(string $operator, ConditionalExpressionInterface $conditionOrGroup) : ConditionGroupInterface;
}