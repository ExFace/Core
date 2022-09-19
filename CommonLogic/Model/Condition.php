<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
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
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\InvalidArgumentException;

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
        if ($this->ignoreEmptyValues === true && $this->getDataType()->isValueEmpty($value)) {
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
            return EXF_COMPARATOR_IS;
        }
        
        $value = $this->getValue();
        $expression_string = $this->getExpression()->toString();
        
        // Determine the comparator if it is not given directly.
        // It can be derived from the value or set to a default value
        if (strpos($value, '!==') === 0) {
            $comparator = EXF_COMPARATOR_EQUALS_NOT;
            $value = substr($value, 3);
        } elseif (strpos($value, '==') === 0) {
            $comparator = EXF_COMPARATOR_EQUALS;
            $value = substr($value, 2);
        } elseif (strpos($value, '>=') === 0) {
            $comparator = EXF_COMPARATOR_GREATER_THAN_OR_EQUALS;
            $value = substr($value, 2);
        } elseif (strpos($value, '>') === 0) {
            $comparator = EXF_COMPARATOR_GREATER_THAN;
            $value = substr($value, 1);
        } elseif (strpos($value, '[') === 0) {
            $comparator = EXF_COMPARATOR_IN;
            if (substr(trim($value), - 1) != ']') {
                $value = substr($value, 1);
            }
        } elseif (strpos($value, '<=') === 0) {
            $comparator = EXF_COMPARATOR_LESS_THAN_OR_EQUALS;
            $value = substr($value, 2);
        } elseif (strpos($value, '<') === 0) {
            $comparator = EXF_COMPARATOR_LESS_THAN;
            $value = substr($value, 1);
        } elseif (strpos($value, '!=') === 0) {
            $comparator = EXF_COMPARATOR_IS_NOT;
            $value = substr($value, 2);
        } elseif (strpos($value, '=') === 0) {
            $comparator = EXF_COMPARATOR_IS;
            $value = substr($value, 1);
        } elseif (strpos($value, '![') === 0) {
            $comparator = EXF_COMPARATOR_NOT_IN;
            if (substr(trim($value), - 1) == ']') {
                $value = substr(trim($value), 2, - 1);
            } else {
                $value = substr($value, 2);
            }
        } else {
            $comparator = EXF_COMPARATOR_IS;
        }
        
        if ($value !== null) {
            $this->setValue($value);
        }
        
        // Take care of values with delimited lists
        if (substr($value, 0, 1) == '[' && substr($value, - 1) == ']') {
            // a value enclosed in [] is actually a IN-statement
            $value = trim($value, "[]");
            $comparator = EXF_COMPARATOR_IN;
        } elseif (strpos($expression_string, EXF_LIST_SEPARATOR) === false
            && $base_object->hasAttribute($expression_string)
            && $base_object->getAttribute($expression_string)->getDataType() instanceof NumberDataType
            && strpos($value, $base_object->getAttribute($expression_string)->getValueListDelimiter()) !== false) {
                // if a numeric attribute has a value with commas, it is actually an IN-statement
                $comparator = EXF_COMPARATOR_IN;
        } 
        
        return $comparator;
    }

    /**
     * The comparison operator used in this condition
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
        $validated = false;
        foreach (get_defined_constants(true)['user'] as $constant => $comparator) {
            if (substr($constant, 0, 15) === 'EXF_COMPARATOR_') {
                if (strcasecmp($value, $comparator) === 0) {
                    $validated = true;
                    $value = $comparator;
                    break;
                }
            }
        }
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
        return $uxon;
    }

    /**
     * Imports data from UXON objects like {"object_alias": "...", "expression": "...", "value": "...", "comparator": "..."}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()            
     */
    public function importUxonObject(UxonObject $uxon)
    {
        if (! $this->isEmpty()) {
            throw new UxonParserError($uxon, 'Cannot import UXON description into a non-empty condition (' . $this->toString() . ')!');
        }
        if ($uxon->hasProperty('expression')) {
            $expression = $uxon->getProperty('expression');
        } elseif ($uxon->hasProperty('attribute_alias')) {
            $expression = $uxon->getProperty('attribute_alias');
        }
        if (! $objAlias = $uxon->getProperty('object_alias')) {
            throw new UxonParserError($uxon, 'Invalid UXON condition syntax: Missing object alias!');
        }
        try {
            $this->setExpression($this->exface->model()->parseExpression($expression, $this->exface->model()->getObject($objAlias)));
            if ($uxon->hasProperty('comparator') && $comp = $uxon->getProperty('comparator')) {
                $this->setComparator($comp);
            }
            if (null !== $ignoreEmpty = $uxon->getProperty('ignore_empty_values')) {
                $this->setIgnoreEmptyValues($ignoreEmpty);
            }
            if ($uxon->hasProperty('value')){
                $value = $uxon->getProperty('value');
                // Apply th evalue only if it is not empty or ignore_empty_values is off
                if ($this->ignoreEmptyValues !== true || ($value !== null && $value !== '')) { 
                    if ($value instanceof UxonObject) {
                        if (! $comp || $comp === EXF_COMPARATOR_IS) {
                            $comp = EXF_COMPARATOR_IN;
                        }
                        
                        if (! $comp || $comp === EXF_COMPARATOR_IS_NOT) {
                            $comp = EXF_COMPARATOR_NOT_IN;
                        }
                        if ($this->getExpression()->isMetaAttribute()) {
                            $glue = $this->getExpression()->getAttribute()->getValueListDelimiter();
                        } else {
                            $glue = EXF_LIST_SEPARATOR;
                        }
                        $value = implode($glue, $value->toArray());
                        
                        if ($comp !== EXF_COMPARATOR_IN && $comp !== EXF_COMPARATOR_NOT_IN) {
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
            case ComparatorDataType::IN:
            case ComparatorDataType::NOT_IN:
                $resposeOnFound = $comparator === ComparatorDataType::IN ? true : false;
                if ($rightVal === null) {
                    return ! $resposeOnFound;
                }
                $rightParts = is_array($rightVal) ? $rightVal : explode($listDelimiter, $rightVal);
                foreach ($rightParts as $part) {
                    if ($this->compare($leftVal, ComparatorDataType::EQUALS, $part)) {
                        return $resposeOnFound;
                    }
                }
                return ! $resposeOnFound;
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
}