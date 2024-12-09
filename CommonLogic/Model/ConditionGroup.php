<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Model\ExpressionRebaseImpossibleError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * Groups multiple conditions and/or condition groups using a logical operator like AND, OR, etc.
 * 
 * Condition groups can be expressed in UXON:
 * 
 * ```
 *  {
 *      "operator": "AND",
 *      "conditions": [
 *          {"expression": "...", "comparator": "==", "value": "..."}
 *      ],
 *      "nested_groups": [
 *          {"operator": "OR", "conditions": []}
 *      ]
 *  }
 *  
 * ```
 * 
 * You can give the condition group a `base_object_alias` or `ignore_empty_values` as default values
 * for the respective properties of its conditions.
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
    
    private $baseObject = null;
    
    private $baseObjectSelector = null;
    
    private $ignoreEmptyValues = null;

    /**
     * @deprecated use ConditionGroupFactory instead!
     * @param \exface\Core\CommonLogic\Workbench $exface
     * @param string $operator
     */
    public function __construct(\exface\Core\CommonLogic\Workbench $exface, string $operator = EXF_LOGICAL_AND, MetaObjectInterface $baseObject = null, bool $ignoreEmptyValues = false)
    {
        $this->exface = $exface;
        $this->ignoreEmptyValues = $ignoreEmptyValues;
        $this->setOperator($operator);
        if ($baseObject !== null) {
            $this->setBaseObject($baseObject);
        }
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
    public function addConditionFromExpression(ExpressionInterface $expression, $value = NULL, string $comparator = EXF_COMPARATOR_IS, bool $ignoreEmptyValue = null) : ConditionGroupInterface
    {
        if (! is_null($value) && $value !== '') {
            $condition = ConditionFactory::createFromExpression($this->exface, $expression, $value, $comparator, $ignoreEmptyValue ?? $this->ignoreEmptyValues);
            $this->addCondition($condition);
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addNestedAND()
     */
    public function addNestedAND() : ConditionGroupInterface
    {
        $grp = new self($this->getWorkbench(), EXF_LOGICAL_AND, $this->baseObject, $this->ignoreEmptyValues);
        $this->addNestedGroup($grp);
        return $grp;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addNestedOR()
     */
    public function addNestedOR() : ConditionGroupInterface
    {
        $grp = new self($this->getWorkbench(), EXF_LOGICAL_OR, $this->baseObject, $this->ignoreEmptyValues);
        $this->addNestedGroup($grp);
        return $grp;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::withOR()
     */
    public function withOR(ConditionalExpressionInterface $conditionOrGroup) : ConditionGroupInterface
    {
        return $this->with(EXF_LOGICAL_OR, $conditionOrGroup);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::withAND()
     */
    public function withAND(ConditionalExpressionInterface $conditionOrGroup) : ConditionGroupInterface
    {
        return $this->with(EXF_LOGICAL_AND, $conditionOrGroup);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::with()
     */
    public function with(string $operator, ConditionalExpressionInterface $conditionOrGroup) : ConditionGroupInterface
    {
        if ($operator !== EXF_LOGICAL_AND && $operator !== EXF_LOGICAL_OR) {
            throw new InvalidArgumentException('Invalid operator "' . $operator . '" for ConditionalExpression::with() - only AND/OR supported!');
        }
        
        // TODO check if the base object matches!
        
        switch (true) {
            // If adding a condition and the operator matches, just add the condition
            case $this->getOperator() === $operator && $conditionOrGroup instanceof ConditionInterface:
                $grp = $this->copy()->addCondition($conditionOrGroup);
                break;
            // If adding a condition group and the operator matches, see if the added group has the same
            // operator too. If so, just add its conditions and nested groups to a copy of this group,
            // thus avoiding a useless nesting level. Otherwise add a nested gorup regularly
            case $this->getOperator() === $operator && $conditionOrGroup instanceof ConditionGroupInterface:
                $grp = $this->copy();
                if ($conditionOrGroup->getOperator() === $operator) {
                    foreach ($conditionOrGroup->getConditions() as $cond) {
                        $grp->addCondition($cond);
                    }
                    foreach ($conditionOrGroup->getNestedGroups() as $nestedGrp) {
                        $grp->addNestedGroup($nestedGrp);
                    }
                } else {
                    $grp->addNestedGroup($conditionOrGroup);
                }
                break;
            // If adding and AND to an OR or vice versa, create a new group with the $operator containing
            // both: this group and the added one
            default:
                $grp = ConditionGroupFactory::createEmpty($this->getWorkbench(), $operator, $this->getBaseObject(), $this->ignoreEmptyValues);
                $grp = $grp->with($conditionOrGroup);
                if (! $this->isEmpty()) {
                    $grp->addNestedGroup($this);
                }
                break;
        }
        return $grp;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addNestedGroupFromString()
     */
    public function addNestedGroupFromString(string $operator, bool $ignoreEmptyValues = null) : ConditionGroupInterface
    {
        $grp = ConditionGroupFactory::createEmpty($this->getWorkbench(), $operator, $this->getBaseObject(), $ignoreEmptyValues ?? $this->ignoreEmptyValues);
        $this->addNestedGroup($grp);
        return $grp;
    }

    /**
     * Conditions in this group
     * 
     * @uxon-property conditions
     * @uxon-type \exface\Core\CommonLogic\Model\Condition[]
     * @uxon-template [{"expression": "", "comparator": "==", "value": ""}]
     * 
     * {@inheritdoc}
     * @see ConditionGroupInterface::getConditions()
     */
    public function getConditions(callable $filter = null) : array
    {
        if ($filter !== null){
            return array_values(array_filter($this->conditions, $filter));
        }
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
     * Further (nested) condition groups inside this group
     * 
     * @uxon-property nested_groups
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup[]
     * @uxon-template [{"operator": "","conditions": [{"expression": "", "comparator": "==", "value": ""}]}]
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
     * Logical operator of the group: AND, OR, etc.
     * 
     * Operators are defined by the EXF_LOGICAL_xxx constants.
     * 
     * @uxon-property operator
     * @uxon-type [AND,OR,XOR]
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
    public function rebase(string $relation_path_to_new_base_object, callable $conditionFilterCallback = null) : ConditionGroupInterface
    {
        // Do nothing, if the relation path is empty (nothing to rebase...)
        if (! $relation_path_to_new_base_object) {
            return $this;
        }
        
        if ($this->hasBaseObject() === true) {
            $result = ConditionGroupFactory::createEmpty($this->exface, $this->getOperator(), $this->getBaseObject()->getRelatedObject($relation_path_to_new_base_object), $this->ignoreEmptyValues);
        } else {
            $result = ConditionGroupFactory::createEmpty($this->exface, $this->getOperator(), null, $this->ignoreEmptyValues);
        }
        foreach ($this->getConditions() as $condition) {
            // Remove conditions not matching the filter
            if (! is_null($conditionFilterCallback) && call_user_func($conditionFilterCallback, $condition, $relation_path_to_new_base_object) === false) {
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
            $new_condition = ConditionFactory::createFromExpression($this->exface, $new_expression, $condition->getValue(), $condition->getComparator(), $condition->willIgnoreEmptyValues());
            $result->addCondition($new_condition);
        }
        
        foreach ($this->getNestedGroups() as $group) {
            $result->addNestedGroup($group->rebase($relation_path_to_new_base_object, $conditionFilterCallback));
        }
        
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::rebaseWithMatchingAttributesOnly()
     */
    public function rebaseWithMatchingAttributesOnly(MetaObjectInterface $newObject) : ConditionGroupInterface
    {
        $transformer = function(ConditionInterface $condition) use ($newObject){
            if ($condition->getExpression()->isMetaAttribute() && $newObject->hasAttribute($condition->getAttributeAlias())) {
                $uxon = $condition->exportUxonObject();
                $uxon->setProperty('object_alias', $newObject->getAliasWithNamespace());
                return ConditionFactory::createFromUxon($this->getWorkbench(), $uxon);
            } else {
                return null;
            }
        };
        return $this->rebaseCustom($newObject, $transformer);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::rebaseCustom()
     */
    public function rebaseCustom(MetaObjectInterface $newObject, callable $conditionTransformer) : ConditionGroupInterface
    {
        $newGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), $this->getOperator(), $newObject, $this->ignoreEmptyValues);
        foreach ($this->getConditions() as $cond) {
            $newCond = $conditionTransformer($cond);
            if ($newCond !== null) {
                if ($newCond->getExpression()->getMetaObject() !== $newObject) {
                    throw new UnexpectedValueException('Failed to rebase condition "' . $cond->__toString() . '" on object ' . $newObject->__toString() . ': the result is not based on the expected object!');
                }
                $newGroup->addCondition($newCond);
            }
        }
        foreach ($this->getNestedGroups() as $nestedGrp) {
            $newNestedGrp = $nestedGrp->rebaseCustom($newObject, $conditionTransformer);
            if (! $newNestedGrp->isEmpty()) {
                $newGroup->addNestedGroup($newNestedGrp);
            }
        }
        return $newGroup;
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
     * @deprecated use __toString()
     * @return string
     */
    public function toString() : string
    {
        return $this->__toString();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionalExpressionInterface::__toString()
     */
    public function __toString() : string
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
        if ($this->hasBaseObject() === true) {
            $uxon->setProperty('base_object_alias', $this->getBaseObjectSelector());
        }
        if ($this->ignoreEmptyValues === true) {
            $uxon->setProperty('ignore_empty_values', true);
        }
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
        try {
            $this->setOperator($uxon->getProperty('operator') ?? EXF_LOGICAL_AND);
            if (null !== $ignoreEmpty = BooleanDataType::cast($uxon->getProperty('ignore_empty_values'))) {
                $this->setIgnoreEmptyValues($ignoreEmpty);
            }
            if ($uxon->hasProperty('base_object_alias')) {
                $this->setBaseObjectAlias($uxon->getProperty('base_object_alias'));
            }
            if ($uxon->hasProperty('conditions')) {
                foreach ($uxon->getProperty('conditions') as $prop) {
                    if ($prop->hasProperty('object_alias') === false && $this->hasBaseObject() === true) {
                        $prop->setProperty('object_alias', $this->getBaseObjectSelector());
                    }
                    if ($prop->hasProperty('ignore_empty_values') === false) {
                        $prop->setProperty('ignore_empty_values', $this->ignoreEmptyValues);
                    }
                    $this->addCondition(ConditionFactory::createFromUxon($this->exface, $prop));
                }
            }
            if ($uxon->hasProperty('nested_groups')) {
                foreach ($uxon->getProperty('nested_groups') as $prop) {
                    // Put the base object selector into the UXON instead of passing the object to the
                    // factory to avoid loading the object if it was not loaded yet.
                    if ($prop->hasProperty('base_object_alias') === false && $this->hasBaseObject() === true) {
                        $prop->setProperty('base_object_alias', $this->getBaseObjectSelector());
                    }
                    if ($prop->hasProperty('ignore_empty_values') === false) {
                        $prop->setProperty('ignore_empty_values', $this->ignoreEmptyValues);
                    }
                    $this->addNestedGroup(ConditionGroupFactory::createFromUxon($this->exface, $prop));
                }
            }
        } catch (UxonParserError $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UxonParserError($uxon, 'Cannot create condition group from UXON: ' . $e->getMessage(), null, $e);   
        }
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionalExpressionInterface::isEmpty()
     */
    public function isEmpty(bool $checkValues = false) : bool
    {
        if (empty($this->getConditions()) === true && empty($this->getNestedGroups()) === true) {
            return true;
        } 
        
        if ($checkValues === true) {
            foreach ($this->getConditions() as $cond) {
                if ($cond->isEmpty() === false) {
                    return false;
                }
            }
            foreach ($this->getNestedGroups() as $group) {
                if ($group->isEmpty(true) === false) {
                    return false;
                }
            }
            return true;
        }
        
        return false;
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
    public function copy() : self
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
    
    /**
     * An array of conditions (comparison predicates) for this group.
     * 
     * @param array $conditions
     * @return ConditionGroupInterface
     */
    protected function addConditions(array $conditions) : ConditionGroupInterface
    {
        foreach ($conditions as $cond) {
            $this->addCondition($cond);
        }
        return $this;
    }
    
    /**
     * An array of further condition groups to be included in addition to regular conditions.
     * 
     * @param array $conditionGroups
     * @return ConditionGroupInterface
     */
    protected function addNestedGroups(array $conditionGroups) : ConditionGroupInterface
    {
        foreach ($conditionGroups as $group) {
            $this->addNestedGroup($group);
        }
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getBaseObject() : ?MetaObjectInterface
    {
        if ($this->baseObject === null && $this->baseObjectSelector !== null) {
            $this->baseObject = $this->getWorkbench()->model()->getObject($this->baseObjectSelector);
        }
        return $this->baseObject;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getBaseObjectSelector() : ?string
    {
        return $this->baseObjectSelector ?? ($this->baseObject !== null ? $this->baseObject->getAliasWithNamespace() : null);
    }
    
    /**
     * All expressions within this condition group will be resolved based on this object.
     * 
     * @uxon-property base_object_alias
     * @uxon-type metamodel:object
     * 
     * @param string $value
     * @return ConditionGroup
     */
    protected function setBaseObjectAlias(string $aliasWithNamespaceOrUid) : ConditionGroup
    {
        $this->baseObjectSelector = $aliasWithNamespaceOrUid;
        $this->baseObject = null;
        return $this;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return ConditionGroup
     */
    protected function setBaseObject(MetaObjectInterface $object) : ConditionGroup
    {
        $this->baseObject = $object;
        $this->baseObjectSelector = null;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasBaseObject() : bool
    {
        return $this->baseObject !== null || $this->baseObjectSelector !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::evaluate()
     */
    public function evaluate(DataSheetInterface $data_sheet = null, int $row_number = null) : bool
    {
        $op = $this->getOperator();
        $results = [];
        $evals = array_merge($this->getConditions(), $this->getNestedGroups());
        foreach ($evals as $conditionOrGroup) {
            $result = $conditionOrGroup->evaluate($data_sheet, $row_number);
            switch (true) {
                case $op === EXF_LOGICAL_AND && $result === false: return false;
                case $op === EXF_LOGICAL_OR && $result === true: return true;
                default:
                    $results[] = $result;
            }
        }
        
        switch ($op) {
            case EXF_LOGICAL_AND: return in_array(false, $results, true) === false;
            case EXF_LOGICAL_OR: return false;
            case EXF_LOGICAL_XOR: count(array_filter(function(bool $val){return $val === true;})) === 1;
            default: 
                throw new RuntimeException('Unsupported logical operator "' . $op . '" in condition group "' . $this->toString() . '"!');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addConditionFromString()
     */
    public function addConditionFromString(string $expression_string, $value, string $comparator = null, bool $ignoreEmptyValue = null) : ConditionGroupInterface
    {
        $base_object = $this->getBaseObject();
        if ($base_object === null) {
            throw new InvalidArgumentException('Cannot create conditional expression from "' . $expression_string .  '": cannot determine base meta object!');
        }
        
        $value = trim($value);
        
        // A special feature for string condition is the possibility to specify a comma separated list of attributes in one element
        // of the filters array, wich means that at least one of the attributes should match the value
        // IDEA move this logic to the condition, so it can be used generally
        $expression_strings = explode(EXF_LIST_SEPARATOR, $expression_string);
        if (count($expression_strings) > 1) {
            $group = ConditionGroupFactory::createEmpty($this->exface, EXF_LOGICAL_OR, $base_object, $ignoreEmptyValue ?? $this->ignoreEmptyValues);
            foreach ($expression_strings as $f) {
                $group->addCondition(ConditionFactory::createFromExpressionString($base_object, $f, $value, $comparator, $ignoreEmptyValue ?? $this->ignoreEmptyValues));
            }
            $this->addNestedGroup($group);
        } elseif ((!is_null($value) && $value !== '') || !$ignoreEmptyValue) {
            $this->addCondition(ConditionFactory::createFromExpressionString($base_object, $expression_string, $value, $comparator, $ignoreEmptyValue ?? $this->ignoreEmptyValues));
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addConditionForAttributeIsNull()
     */
    public function addConditionForAttributeIsNull($attributeOrAlias) : ConditionGroupInterface
    {
        if ($attributeOrAlias instanceof MetaAttributeInterface) {
            $this->addConditionFromAttribute($attributeOrAlias, EXF_LOGICAL_NULL, ComparatorDataType::EQUALS);
        } else {
            $this->addConditionFromString($attributeOrAlias, EXF_LOGICAL_NULL, ComparatorDataType::EQUALS);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addConditionForAttributeIsNotNull()
     */
    public function addConditionForAttributeIsNotNull($attributeOrAlias) : ConditionGroupInterface
    {
        if ($attributeOrAlias instanceof MetaAttributeInterface) {
            $this->addConditionFromAttribute($attributeOrAlias, EXF_LOGICAL_NULL, ComparatorDataType::EQUALS_NOT);
        } else {
            $this->addConditionFromString($attributeOrAlias, EXF_LOGICAL_NULL, ComparatorDataType::EQUALS_NOT);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addConditionFromAttribute()
     */
    public function addConditionFromAttribute(MetaAttributeInterface $attribute, $value, string $comparator = null, bool $ignoreEmptyValue = null) : ConditionGroupInterface
    {
        $this->addCondition(ConditionFactory::createFromAttribute($attribute, $value, $comparator, $ignoreEmptyValue ?? $this->ignoreEmptyValues));
        return $this;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addConditionFromValueArray()
    */
    public function addConditionFromValueArray($expressionOrString, $value_list, bool $ignoreEmptyValue = null) : ConditionGroupInterface
    {
        if ($expressionOrString instanceof ExpressionInterface) {
            $expr = $expressionOrString;
        } else {
            $expr = ExpressionFactory::createFromString($this->getWorkbench(), $expressionOrString, $this->getBaseObject());
        }
        if (is_array($value_list) === true) {
            if ($expr->isMetaAttribute() === true){
                $delimiter = $expr->getAttribute()->getValueListDelimiter();
            } else {
                $delimiter = EXF_LIST_SEPARATOR;
            }
            $value = implode($delimiter, $value_list);
        } else {
            $value = $value_list;
        }
        $this->addConditionFromExpression($expr, $value, EXF_COMPARATOR_IN, $ignoreEmptyValue);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::addConditionFromColumnValues()
     */
    public function addConditionFromColumnValues(DataColumnInterface $column, bool $ignoreEmptyValue = null) : ConditionGroupInterface
    {
        $values = implode(($column->getAttribute() ? $column->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR), array_unique($column->getValues(false)));
        $this->addConditionFromString($column->getExpressionObj()->toString(), $values, EXF_COMPARATOR_IN, $ignoreEmptyValue);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionGroupInterface::replaceCondition()
     */
    public function replaceCondition(ConditionInterface $conditionToReplace, ConditionInterface $replaceWith, bool $recursive = true) : ConditionGroupInterface
    {
        foreach ($this->getConditions() as $cond) {
            if ($cond === $conditionToReplace) {
                $this->removeCondition($conditionToReplace);
                $this->addCondition($replaceWith);
                return $this;
            }
        }
        if ($recursive === true) {
            foreach ($this->getNestedGroups() as $grp) {
                $grp->replaceCondition($conditionToReplace, $replaceWith);
            }
        }
        return $this;
    }
    
    /**
     * Set to TRUE to treat the condition as empty (having no value) if an empty value is set.
     * 
     * @uxon-property ignore_empty_values
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return ConditionGroupInterface
     */
    protected function setIgnoreEmptyValues(bool $trueOrFalse) : ConditionGroupInterface
    {
        $this->ignoreEmptyValues = $trueOrFalse;
        return $this;
    }
}