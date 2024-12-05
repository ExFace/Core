<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Factories\MetaObjectFactory;

/**
 * A condition is a simple conditional predicate to compare two expressions.
 * 
 * Each condition (e.g. `expression = value` or `date > 01.01.1970`) consists of 
 * - a (left) expression,
 * - a comparator (e.g. =, <, etc.) and 
 * - a (right) value expression
 * 
 * A condition can be expressed in UXON:
 * 
 * ```
 *  {
 *      "object_alias": "my.App.myObject",
 *      "expression": "myAttribute",
 *      "comparator": "=",
 *      "value" = "myValue"
 *  }
 * 
 * ```
 * 
 * Depending on the comparator, the value may be a scalar or an array (for IN-comparators).
 * 
 * ## Handling empty values
 * 
 * Note, that it might by tricky to distinguish between checking if something is empty (i.e. `!== null`) or 
 * an empty condition, that is missing a value on of its sides and should not be evaluated. Normally, setting
 * `"value":""` in UXON will result in an empty check, but you can also explicitly set `ignore_empty_values:true`
 * to treat such conditions as empty and thus ommitted.
 * 
 * @see ConditionInterface
 *
 * @author Andrej Kabachnik
 *        
 */
class Condition implements ConditionInterface
{

    private $exface = null;

    private $expression = null;

    private $value = null;
    
    private $value_set = false;

    private $comparator = null;

    private $data_type = null;
    
    private $ignoreEmptyValues = null;

    private bool $applyToAggregates = true;

    /**
     * @deprecated use ConditionFactory instead!
     * 
     * All parameters except for the workbench are optional in order to be able to create empty conditions
     * - primarily to be filled with values from a UXON object
     * 
     * @param \exface\Core\CommonLogic\Workbench $exface   
     * @param ExpressionInterface $leftExpression
     * @param string $comparator
     * @param string $rightExpression      
     * @param bool $ignoreEmptyValues   
     */
    public function __construct(\exface\Core\CommonLogic\Workbench $exface, ExpressionInterface $leftExpression = null, string $comparator = null, string $rightExpression = null, bool $ignoreEmptyValues = false)
    {
        $this->exface = $exface;
        $this->ignoreEmptyValues = $ignoreEmptyValues;
        
        if ($comparator !== null) {
            $this->setComparator($comparator);
        }
        if ($leftExpression !== null) {
            $this->setExpression($leftExpression);
        }
        if ($rightExpression !== null) {
            $this->setValue($rightExpression);
        }
        if ($comparator !== null) {
            $this->setComparator($comparator);
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionInterface::getExpression()
     */
    public function getExpression() : ExpressionInterface
    {
        return $this->expression;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::getLeftExpression()
     */
    public function getLeftExpression() : ExpressionInterface
    {
        return $this->getExpression();
    }

    /**
     * The left side of the condition.
     * 
     * @uxon-property expression
     * @uxon-type metamodel:expression
     * 
     * @param ExpressionInterface $expression
     * @return ConditionInterface
     */
    protected function setExpression(ExpressionInterface $expression) : ConditionInterface
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionInterface::getValue()
     */
    public function getValue() : ?string
    {
        return $this->value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::getRightExpression()
     */
    public function getRightExpression() : ExpressionInterface
    {
        return ExpressionFactory::createFromString($this->getWorkbench(), $this->value, $this->getLeftExpression()->getMetaObject());
    }

    /**
     * Changes right side of the condition.
     * 
     * @uxon-property value
     * @uxon-type metamodel:expression
     * 
     * @see ConditionInterface::setValue()
     */
    public function setValue(?string $value) : ConditionInterface
    {
        $this->unsetValue();
        if (Expression::detectFormula($value)) {
            $expr = ExpressionFactory::createFromString($this->getWorkbench(), $value, $this->getLeftExpression()->getMetaObject());
            if ($expr->isStatic()) {
                $value = $expr->evaluate();
            } else {
                throw new InvalidArgumentException('Illegal filter value "' . $value . '": only scalar values or static formulas allowed!');
            }
        }
        $cmp = $this->comparator;
        // If the value empty according to its data type, concider this condition not empty
        // only if it should explicitly support empty values AND never for IN comparators
        // (not sure, what exactly IN(nothing) or NOT_IN(nothing) are supposed to mean)
        // only use an explicitly set comparater here and not let the comparater be guessed, as that could guess a wrong comparator
        // when the value is not set yet. For example that happens in a autosuggest action request with an filter parameter containing an IN filter
        // like in a Prefill of an InputComboTable with multi-select
        if ($this->getDataType()->isValueEmpty($value) && ($this->ignoreEmptyValues === true || $cmp === ComparatorDataType::IN || $cmp === ComparatorDataType::NOT_IN)) {
            return $this;
        }
        $this->value_set = true;
        try {
            $value = $this->getDataType()->parse($value);
        } catch (\Throwable $e) {
            throw new RangeException('Illegal filter value "' . $value . '" for attribute "' . $this->getAttributeAlias() . '" of data type "' . $this->getExpression()->getAttribute()->getDataType()->getName() . '": ' . $e->getMessage(), '6T5WBNB', $e);
        }
        $this->value = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::unsetValue()
     */
    public function unsetValue() : ConditionInterface
    {
        $this->value_set = false;
        $this->value = null;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::getComparator()
     */
    public function getComparator() : string
    {
        if (is_null($this->comparator)) {
            $this->comparator = $this->guessComparator();
        }
        return $this->comparator;
    }
    
    /**
     * 
     * @return string
     */
    protected function guessComparator()
    {
        if (!$base_object = $this->getExpression()->getMetaObject()){
            return ComparatorDataType::IS;
        }
        
        $value = $this->getValue();
        $expression_string = $this->getExpression()->toString();
        
        // Determine the comparator if it is not given directly.
        // It can be derived from the value or set to a default value
        switch (true) {
            case $value === null:
            case is_string($value) === false:
            case $value === '':
                $comparator = ComparatorDataType::IS;
                return $comparator;
            case strpos($value, '!==') === 0:
                $comparator = ComparatorDataType::EQUALS_NOT;
                $value = substr($value, 3);
                break;
            case strpos($value, '==') === 0:
                $comparator = ComparatorDataType::EQUALS;
                $value = substr($value, 2);
                break;
            case strpos($value, '>=') === 0:
                $comparator = ComparatorDataType::GREATER_THAN_OR_EQUALS;
                $value = substr($value, 2);
                break;
            case strpos($value, '>') === 0:
                $comparator = ComparatorDataType::GREATER_THAN;
                $value = substr($value, 1);
                break;
            case strpos($value, '[[') === 0:
                $comparator = ComparatorDataType::LIST_SUBSET;
                if (substr(trim($value), - 1) != ']') {
                    $value = substr($value, 1);
                }
                break;
            case strpos($value, '![[') === 0:
                $comparator = ComparatorDataType::LIST_NOT_SUBSET;
                if (substr(trim($value), - 1) == ']') {
                    $value = substr(trim($value), 2, - 1);
                } else {
                    $value = substr($value, 2);
                }
                break;
            case strpos($value, '[') === 0:
                $comparator = ComparatorDataType::IN;
                if (substr(trim($value), - 1) != ']') {
                    $value = substr($value, 1);
                }
                break;
            case strpos($value, '![') === 0:
                $comparator = ComparatorDataType::IN;
                if (substr(trim($value), - 1) == ']') {
                    $value = substr(trim($value), 2, - 1);
                } else {
                    $value = substr($value, 2);
                }
                break;
            case strpos($value, '<=') === 0:
                $comparator = ComparatorDataType::LESS_THAN_OR_EQUALS;
                $value = substr($value, 2);
                break;
            case strpos($value, '<') === 0:
                $comparator = ComparatorDataType::LESS_THAN;
                $value = substr($value, 1);
                break;
            case strpos($value, '!=') === 0:
                $comparator = ComparatorDataType::IS_NOT;
                $value = substr($value, 2);
                break;
            case strpos($value, '=') === 0:
                $comparator = ComparatorDataType::IS;
                $value = substr($value, 1);
                break;
            default:
                $comparator = ComparatorDataType::IS;
                break;
        }
        
        if ($value !== null) {
            $this->setValue($value);
        }
        
        // Take care of values with delimited lists
        if (substr($value, 0, 1) == '[' && substr($value, - 1) == ']') {
            // a value enclosed in [] is actually a IN-statement
            $value = trim($value, "[]");
            $comparator = ComparatorDataType::IN;
        } elseif (
            mb_strpos($expression_string, EXF_LIST_SEPARATOR) === false
            && $base_object->hasAttribute($expression_string)
            && (
                $base_object->getAttribute($expression_string)->getDataType() instanceof NumberDataType
                || $base_object->getAttribute($expression_string)->getDataType() instanceof EnumDataTypeInterface
            )
            && mb_strpos($value, $base_object->getAttribute($expression_string)->getValueListDelimiter()) !== false
        ) {
                // if a numeric attribute has a value with commas, it is actually an IN-statement
                $comparator = ComparatorDataType::IN;
        } 
        
        return $comparator;
    }

    /**
     * The comparison operator used in this condition
     * 
     * ## Scalar (single value) comparators
     * 
     * - `=` - universal comparator similar to SQL's `LIKE` with % on both sides. Can compare different 
     * data types. If the left value is a string, becomes TRUE if it contains the right value. Case 
     * insensitive for strings
     * - `!=` - yields TRUE if `IS` would result in FALSE
     * - `==` - compares two single values of the same type. Case sensitive for stings. Normalizes the 
     * values before comparison though, so the date `-1 == 21.09.2020` will yield TRUE on the 22.09.2020. 
     * - `!==` - the inverse of `EQUALS`
     * - `<` - yields TRUE if the left value is less than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     * - `<=` - yields TRUE if the left value is less than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     * - `>` - yields TRUE if the left value is greater than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     * - `>=` - yields TRUE if the left value is greater than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     * 
     * ## List comparators
     * 
     * - `[` - IN-comparator - compares a value with each item in a list via EQUALS. Becomes true if the left
     * value equals at least on of the values in the list within the right value. The list on the
     * right side must consist of numbers or strings separated by commas or the attribute's value
     * list delimiter if filtering over an attribute. The right side can also be another type of
     * expression (e.g. a formula or widget link), that yields such a list.
     * - `![` - the inverse von `[` . Becomes true if the left value equals none of the values in the 
     * list within the right value. The list on the right side must consist of numbers or strings separated 
     * by commas or the attribute's value list delimiter if filtering over an attribute. The right side can 
     * also be another type of expression (e.g. a formula or widget link), that yields such a list.
     * - `][` - intersection - compares two lists with each other. Becomes TRUE when there is at least 
     * one element, that is present in both lists.
     * - `!][` - the inverse of `][`. Becomes TRUE if no element is part of both lists.
     * - `[[` - subset - compares two lists with each other. Becomes true when all elements of the left list 
     * are in the right list too
     * - `![[` - the inverse of `][`. Becomes true when at least one element of the left list is NOT in 
     * the right list.
     * 
     * ## Range comparators
     * 
     * - `..` - range between two values - e.g. `1 .. 5`
     * 
     * @uxon-property comparator
     * @uxon-type metamodel:comparator
     * @uxon-default =
     * 
     * @param string $value
     * @throws UnexpectedValueException
     * @return ConditionInterface
     */
    protected function setComparator(string $value) : ConditionInterface
    {
        try {
            $this->comparator = static::sanitizeComparator($value);
        } catch (UnexpectedValueException $e){
            throw new UnexpectedValueException('Invalid comparator value in condition "' . $this->getExpression()->toString() . ' ' . $value . ' ' . $this->getValue() . '"!', '6W1SD52', $e);
        }
        
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see ConditionInterface::sanitizeComparator()
     */
    public static function sanitizeComparator(string $value) : string
    {
        $validated = ComparatorDataType::isValidStaticValue($value);
        if (! $validated) {
            throw new UnexpectedValueException('Invalid comparator value "' . $value . '"!', '6W1SD52');
        }
        return $value;
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionInterface::getDataType()
     */
    public function getDataType() : DataTypeInterface
    {
        if ($this->data_type === null) {
            $this->data_type = DataTypeFactory::createBaseDataType($this->exface);
        }
        return $this->data_type;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::getAttributeAlias()
     */
    public function getAttributeAlias()
    {
        if ($this->getExpression()->isMetaAttribute()) {
            return $this->getExpression()->toString();
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see ConditionalExpressionInterface::toString()
     */
    public function toString() : string
    {
        return $this->getExpression()->toString() . ' ' . $this->getComparator() . ' ' . $this->getValue();
    }

    /**
     * 
     * @return string
     */
    public function __toString() : string
    {
        return $this->toString();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('expression', $this->getExpression()->toString());
        $uxon->setProperty('comparator', $this->getComparator());
        if ($this->isEmpty() === false) {
            $uxon->setProperty('value', $this->getValue());
        }
        $uxon->setProperty('object_alias', $this->getExpression()->getMetaObject()->getAliasWithNamespace());
        if ($this->ignoreEmptyValues === true) {
            $uxon->setProperty('ignore_empty_values', $this->ignoreEmptyValues);
        }
        if ($this->applyToAggregates === false) {
            $uxon->setProperty('apply_to_aggregates', $this->applyToAggregates);
        }

        return $uxon;
    }

    /**
     * Imports data from UXON objects in one of the supported formats. 
     * 
     * ## Supported formats
     * 
     * Most commonly used "one-sided" conditions comparing an expression to astatic value:
     * 
     * ```
     *  { 
     *      "expression": "...", 
     *      "value": "...", 
     *      "comparator": "..."
     *      "object_alias": "...",
     *  }
     *  
     * ```
     * 
     * There is also a more generic format comparing two values instead of an expression
     * and a scalar expclicitly. For now, one of the values MUST be an expression and the
     * other one a scalar, but this is planned to change in future!
     * 
     * ```
     *  { 
     *      "value_left": "...", 
     *      "value_right": "...", 
     *      "comparator": "..."
     *      "object_alias": "...",
     *  }
     *  
     * ```
     * 
     * Also the legacy format for one-sided expressions with `attribute_alias` instead of 
     * `expression` is still supported:
     * 
     * ```
     *  { 
     *      "attribute_alias": "...", 
     *      "value": "...", 
     *      "comparator": "..."
     *      "object_alias": "...",
     *  }
     *  
     * ```
     * 
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()            
     */
    public function importUxonObject(UxonObject $uxon)
    {
        if (! $this->isEmpty()) {
            throw new UxonParserError($uxon, 'Cannot import UXON description into a non-empty condition (' . $this->toString() . ')!');
        }
        if (! $objAlias = $uxon->getProperty('object_alias')) {
            throw new UxonParserError($uxon, 'Invalid UXON condition syntax: Missing object alias!');
        }
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), $objAlias);
        $expression = null;
        $expressionStr = null;
        $expressionUnknownAsString = true;
        switch (true) {
            case $uxon->hasProperty('expression') === true:
                $expressionStr = $uxon->getProperty('expression');
                break;
            case $uxon->hasProperty('attribute_alias') === true:
                $expressionStr = $uxon->getProperty('attribute_alias');
                break;
            case $uxon->hasProperty('value_left') === true:
                $val = $uxon->getProperty('value_left');
                $expression = ExpressionFactory::createForObject($obj, $val, $expressionUnknownAsString);
                if ($expression->isMetaAttribute() === true) {
                    $value = $uxon->getProperty('value_right');
                    break;
                }
                // Otherwise continue with the next case
            case $uxon->hasProperty('value_right') === true:
                $val = $uxon->getProperty('value_right');
                $expression = ExpressionFactory::createForObject($obj, $val, $expressionUnknownAsString);
                if ($expression->isMetaAttribute() === true) {
                    $value = $uxon->getProperty('value_left');
                    break;
                }
                // Otherwise continue with the next case
            default:
                throw new UxonParserError($uxon, 'Cannot parse condition UXON: no expression found!');
        }
        try {
            $expression = $expression ?? ExpressionFactory::createForObject($obj, $expressionStr, $expressionUnknownAsString);
            $this->setExpression($expression);
            if ($uxon->hasProperty('comparator') && ($comp = $uxon->getProperty('comparator'))) {
                $this->setComparator($comp);
            }

            if (null !== $ignoreEmpty = BooleanDataType::cast($uxon->getProperty('ignore_empty_values'))) {
                $this->setIgnoreEmptyValues($ignoreEmpty);
            }

            if(null !== $applyToAggregates = BooleanDataType::cast($uxon->getProperty('apply_to_aggregates'))) {
                $this->setApplyToAggregates($applyToAggregates);
            }

            if ($uxon->hasProperty('value') || $value !== null){
                $value = $value ?? $uxon->getProperty('value');
                // Apply th evalue only if it is not empty or ignore_empty_values is off
                if ($this->ignoreEmptyValues !== true || ($value !== null && $value !== '')) { 
                    if ($value instanceof UxonObject) {
                        if (! $comp || $comp === ComparatorDataType::IS) {
                            $comp = ComparatorDataType::IN;
                        }
                        
                        if (! $comp || $comp === ComparatorDataType::IS_NOT) {
                            $comp = ComparatorDataType::NOT_IN;
                        }
                        if ($expression->isMetaAttribute()) {
                            $glue = $expression->getAttribute()->getValueListDelimiter();
                        } else {
                            $glue = EXF_LIST_SEPARATOR;
                        }
                        $value = implode($glue, $value->toArray());
                        
                        if ($comp !== ComparatorDataType::IN && $comp !== ComparatorDataType::NOT_IN) {
                            throw new UnexpectedValueException('Cannot use comparator "' . $comp . '" with a list of values "' . $value . '"!');    
                        }
                    }
                    $this->setValue($value);
                }
            }
        } catch (\Throwable $e) {
            throw new UxonParserError($uxon, 'Cannot create condition from UXON: ' . $e->getMessage(), null, $e);
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
    public function isEmpty() : bool
    {
        return ! $this->value_set;
    }

    /**
     * {@inheritdoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * {@inheritdoc}
     * @see ConditionalExpressionInterface::toConditionGroup()
     */
    public function toConditionGroup() : ConditionGroupInterface
    {
        $conditionGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND);
        $conditionGroup->addCondition($this);
        return $conditionGroup;
    }

    /**
     * 
     * @return \exface\Core\Interfaces\iCanBeConvertedToUxon|\exface\Core\CommonLogic\Model\Condition
     */
    public function copy() : self
    {
        return ConditionFactory::createFromUxon($this->getWorkbench(), $this->exportUxonObject());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::evaluate()
     */
    public function evaluate(DataSheetInterface $data_sheet = null, int $row_number = null) : bool
    {
        if ($this->isEmpty() === true) {
            return true;
        }
        
        if ($data_sheet === null && $row_number !== null) {
            throw new RuntimeException('Cannot evaluate a condition: do data provided!');
        }
        
        $leftVal = $this->getExpression()->evaluate($data_sheet, $row_number);
        $rightVal = $this->getValue(); // Value is already parsed via datatype in setValue()

        $listDelimiter = $this->getExpression()->isMetaAttribute() ? $this->getExpression()->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
        
        // Normalize empty values according to the data type of the expression
        $dataType = $this->getExpression()->getDataType();
        if ($dataType->isValueEmpty($leftVal)) {
            $leftVal = null;
        }
        if ($dataType->isValueEmpty($rightVal)) {
            $rightVal = null;
        }
        
        return $this->compare($leftVal, $this->getComparator(), $rightVal, $listDelimiter);
    }
    
    /**
     * 
     * @param mixed $leftVal
     * @param string $comparator
     * @param mixed $rightVal
     * @param string $listDelimiter
     * 
     * @throws RuntimeException
     * 
     * @return bool
     */
    protected function compare($leftVal, string $comparator, $rightVal, string $listDelimiter = EXF_LIST_SEPARATOR) : bool
    {
        if ($rightVal === EXF_LOGICAL_NULL) {
            $rightVal = null;
        }
        if ($leftVal === EXF_LOGICAL_NULL) {
            $leftVal = null;
        }
        switch ($comparator) {
            case ComparatorDataType::IS:
                return $rightVal === null || mb_stripos(($leftVal ?? ''), ($rightVal ?? '')) !== false;
            case ComparatorDataType::IS_NOT:
                return mb_stripos(($leftVal ?? ''), ($rightVal ?? '')) === false;
            case ComparatorDataType::EQUALS:
                return $leftVal == $rightVal;
            case ComparatorDataType::EQUALS_NOT:
                return $leftVal != $rightVal;
            case ComparatorDataType::GREATER_THAN:
                return $leftVal > $rightVal;
            case ComparatorDataType::LESS_THAN:
                return $leftVal < $rightVal;
            case ComparatorDataType::GREATER_THAN_OR_EQUALS:
                return $leftVal >= $rightVal;
            case ComparatorDataType::LESS_THAN_OR_EQUALS:
                return $leftVal <= $rightVal;
            // IN means the left value is equal to at least one right value
            case ComparatorDataType::IN:
            // NOT IN is the reverse of IN meaning the left value is not equal to any right value
            case ComparatorDataType::NOT_IN:
                $resposeOnFound = $comparator === ComparatorDataType::IN ? true : false;
                // If the right side is empty, there is no way any left side is in it
                if ($rightVal === null) {
                    return ! $resposeOnFound;
                }
                // Make sure the right side is an array
                $rightParts = is_array($rightVal) ? $rightVal : explode($listDelimiter, $rightVal);
                // Compare each right value to the left value via EQUALS
                // If a match is found, return TRUE for IN and FALSE for NOT_IN
                foreach ($rightParts as $part) {
                    // trim the $part value as list read from data source might be
                    // seperated by list delimiter and whitespace
                    if ($this->compare($leftVal, ComparatorDataType::EQUALS, trim($part))) {
                        return $resposeOnFound;
                    }
                }
                return ! $resposeOnFound;
            case ComparatorDataType::LIST_INTERSECTS:
            case ComparatorDataType::LIST_NOT_INTERSECTS:
                $resultOnIntersect = $comparator === ComparatorDataType::LIST_INTERSECTS ? true : false;
                if ($rightVal === null && $leftVal === null) {
                    return $resultOnIntersect;
                }
                if ($rightVal === null || $leftVal === null) {
                    return ! $resultOnIntersect;
                }
                $rightParts = is_array($rightVal) ? $rightVal : explode($listDelimiter, $rightVal);
                $leftParts = is_array($leftVal) ? $leftVal : explode($listDelimiter, $leftVal);
                $rightPartsTrimmed = array_map('trim', $rightParts);
                $leftPartsTrimmed = array_map('trim', $leftParts);
                $intersectArray = array_intersect($rightPartsTrimmed, $leftPartsTrimmed);
                return ! empty($intersectArray) ? $resultOnIntersect : ! $resultOnIntersect;
            case ComparatorDataType::LIST_SUBSET:
            case ComparatorDataType::LIST_NOT_SUBSET:
                $resultOnDiff = $comparator === ComparatorDataType::LIST_SUBSET ? false : true;
                // Empty set is always a subset
                if ($leftVal === null) {
                    return ! $resultOnDiff;
                }
                // Nothing is a subset of empty
                if ($rightVal === null) {
                    return $resultOnDiff;
                }
                $rightParts = is_array($rightVal) ? $rightVal : explode($listDelimiter, $rightVal);
                $leftParts = is_array($leftVal) ? $leftVal : explode($listDelimiter, $leftVal);
                $rightPartsTrimmed = array_map('trim', $rightParts);
                $leftPartsTrimmed = array_map('trim', $leftParts);
                $diffArray = array_diff($leftPartsTrimmed, $rightPartsTrimmed);
                return ! empty($diffArray) ? $resultOnDiff : ! $resultOnDiff;
            default:
                throw new RuntimeException('Invalid comparator "' . $comparator . '" used in condition "' . $this->toString() . '"!');
        }
    }
    
    /**
     * Set to TRUE to treat the condition as empty (having no value) if an empty value is set.
     * 
     * @uxon-property ignore_empty_values
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return ConditionInterface
     */
    protected function setIgnoreEmptyValues(bool $trueOrFalse) : ConditionInterface
    {
        $this->ignoreEmptyValues = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::willIgnoreEmptyValues()
     */
    public function willIgnoreEmptyValues() : bool
    {
        return $this->ignoreEmptyValues;
    }

    /**
     * If set to FALSE this condition will not be applied to aggregated values.
     *
     * This can be used to fine-tune filters for instance.
     * The default value is TRUE.
     *
     * @uxon-property apply_to_aggregates
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return $this
     */
    public function setApplyToAggregates(bool $value) : static
    {
        $this->applyToAggregates = $value;
        return $this;
    }

    /**
     * Returns TRUE if this condition applies to aggregated values.
     *
     * @return bool
     */
    public function appliesToAggregatedValues() : bool
    {
        return $this->applyToAggregates;
    }

    /**
     * Check, whether this condition can be divided into sub-conditions.
     * 
     * @see ComparatorDataType::isExplicit()
     * @return bool
     */
    public function isExplicit() : bool
    {
        if($this->isEmpty()) {
            return true;
        }
        
        $values = $this->getValue();
        if(!is_array($values)) {
            $expression = $this->getExpression();
            $delimiter = $expression->isMetaAttribute() ? $expression->getAttribute()->getValueListDelimiter() : ',';
            $values = explode($delimiter, $values);
        }
        
        return count($values) <= 1 || ComparatorDataType::isExplicit($this->getComparator());
    }

    /**
     * Divide this condition into sub-conditions that are guaranteed to be explicit.
     * 
     * NOTE: If this condition is already explicit, the resulting condition group will contain
     * this condition as its only component.
     * 
     * @see ComparatorDataType::isExplicit()
     * 
     * @param bool $trimValues
     * @return ConditionGroupInterface
     */
    public function makeExplicit(bool $trimValues = true) : ConditionGroupInterface
    {
        $rightSideValues = $this->getValue();
        if(!is_array($rightSideValues)) {
            $expression = $this->getExpression();
            $delimiter = $expression->isMetaAttribute() ? $expression->getAttribute()->getValueListDelimiter() : ',';
            $rightSideValues = explode($delimiter, $rightSideValues);
        }
        
        [$operator, $comparator] = ComparatorDataType::makeExplicit($this->getComparator());
        $conditionGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), $operator, null, $this->ignoreEmptyValues);
        foreach ($rightSideValues as $value) {
            if($trimValues) {
                $value = trim($value);
            }
            
            $condition = ConditionFactory::createEmpty($this->getWorkbench(), $this->ignoreEmptyValues);
            $condition->setExpression($this->getExpression());
            $condition->setValue($value);
            $condition->setComparator($comparator);
            $conditionGroup->addCondition($condition);
        }
        
        return $conditionGroup;
    }
}