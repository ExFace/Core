<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\RelationDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\Exceptions\LogicException;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * A condition is a simple conditional predicate consisting of a (left) expression,
 * a comparator (e.g. =, <, etc.) and a (right) value expression: e.g. "expr = a" or 
 * "date > 01.01.1970", etc.
 * 
 * Conditions can be combined to condition groups (see CondtionGroupInterface) using 
 * logical operators like AND, OR, etc.
 * 
 * A condition can be expressed in UXON:
 * 
 * ```
 * {
 *  "object_alias": "my.App.myObject",
 *  "left_expression": "MY_ATTRIBUTE",
 *  "comparator": "=",
 *  "right_expression" = "myValue"
 * }
 * 
 * ```
 * 
 * Depending on the comparator, the value may be a scalar or an array (for IN-comparators).
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
    
    private $leftExpr = null;
    
    private $leftExprRaw = null;
    
    private $leftExprIsSet = false;
    
    private $rightExpr = null;
    
    private $rightExprRaw = null;
    
    private $rightExprIsSet = false;

    private $comparator = null;

    private $data_type = null;
    
    private $baseObject = null;
    
    private $baseObjectSelector = null;

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
     */
    public function __construct(\exface\Core\CommonLogic\Workbench $exface, ExpressionInterface $leftExpression = null, string $comparator = null, string $rightExpression = null)
    {
        $this->exface = $exface;
        if ($leftExpression !== null) {
            $this->setLeftExpression($leftExpression);
        }
        if ($rightExpression !== null) {
            $this->setRightExpression($rightExpression);
        }
        if ($comparator !== null) {
            $this->setComparator($comparator);
        }
    }

    /**
     * @deprecated use getLeftExpression() instead!
     * @return ExpressionInterface
     */
    public function getExpression() : ExpressionInterface
    {
        return $this->getLeftExpression();
    }

    /**
     * @deprecated use getRightExpression()->getRawValue() instead!
     * @return string|NULL
     */
    public function getValue() : ?string
    {
        return $this->getRightExpression()->getRawValue();
    }

    /**
     * @deprecated use setRightExpression() instead!
     * 
     * @param string $value
     * @throws RangeException
     * @return ConditionInterface
     */
    protected function setValue(string $value) : ConditionInterface
    {
        $this->rightExprIsSet = true;
        try {
            $value = $this->getDataType()->parse($value);
        } catch (\Throwable $e) {
            throw new RangeException('Illegal filter value "' . $value . '" for attribute "' . $this->getAttributeAlias() . '" of data type "' . $this->getExpression()->getAttribute()->getDataType()->getName() . '": ' . $e->getMessage(), '6T5WBNB', $e);
        }
        $this->rightExpr = ExpressionFactory::createFromString($this->getWorkbench(), $value, $this->getBaseObject(), true);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\ConditionInterface::getComparator()
     */
    public function getComparator() : string
    {
        if ($this->comparator === null) {
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
        if ($this->hasBaseObject() === false && null === ($base_object = $this->getLeftExpression()->getMetaObject() ?? $this->getRightExpression()->getMetaObject()) ){
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
        } elseif ($base_object !== null
            && strpos($expression_string, EXF_LIST_SEPARATOR) === false
            && $base_object->hasAttribute($expression_string)
            && ($base_object->getAttribute($expression_string)->getDataType() instanceof NumberDataType
                || $base_object->getAttribute($expression_string)->getDataType() instanceof RelationDataType)
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
            $this->comparator = ComparatorDataType::cast($value);
        } catch (DataTypeCastingError $e){
            throw new UnexpectedValueException('Invalid comparator value in condition "' . $this->getExpression()->toString() . ' ' . $value . ' ' . $this->getValue() . '"!', '6W1SD52', $e);
        }
        
        return $this;
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
     * {@inheritdoc}
     * @see ConditionalExpressionInterface::toString()
     */
    public function toString() : string
    {
        return $this->getLeftExpression()->toString() . ' ' . $this->getComparator() . ' ' . $this->getRightExpression()->toString();
    }

    /**
     * 
     * @return string
     */
    public function __toString()
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
        $uxon->setProperty('left_expression', $this->getLeftExpression()->toString());
        $uxon->setProperty('comparator', $this->getComparator());
        $uxon->setProperty('right_expression', $this->getRightExpression()->toString());
        $uxon->setProperty('object_alias', $this->getBaseObject()->getAliasWithNamespace());
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
            throw new LogicException('Cannot import UXON description into a non-empty condition (' . $this->toString() . ')!');
        }
        if ($baseObjAlias = $uxon->getProperty('object_alias')) {
            $this->setObjectAlias($baseObjAlias);
        }
        
        $leftExpr = $uxon->getProperty('left_expression');
        if ($uxon->hasProperty('expression') === true) {
            if ($leftExpr !== null) {
                throw new UxonParserError($uxon, 'Invalid UXON condition syntax: cannot use properties `left_expression` and `expression` at the same time - use only `left_expression` instead!');
            } else {
                $leftExpr = $uxon->getProperty('expression');
            }
        }
        if ($uxon->hasProperty('attribute_alias') === true) {
            if ($leftExpr !== null) {
                throw new UxonParserError($uxon, 'Invalid UXON condition syntax: cannot use properties `left_expression`, `expression` and `attribute_alias` at the same time - use only `left_expression` instead!');
            } else {
                $leftExpr = $uxon->getProperty('attribute_alias');
            }
        }
        
        if ($this->hasBaseObject() === false) {
            throw new UxonParserError($uxon, 'Invalid UXON condition syntax: Missing object alias!');
        }
        $this->setLeftExpression($leftExpr);
        
        if ($comp = $uxon->getProperty('comparator')) {
            $this->setComparator($comp);
        }
        
        $rigthExpr = $uxon->getProperty('right_expression');
        if ($uxon->hasProperty('value') === true){
            if ($rigthExpr !== null) {
                throw new UxonParserError($uxon, 'Invalid UXON condition syntax: cannot use properties `right_expression` and `value` at the same time - use only `right_expression` instead!');
            }
            $value = $uxon->getProperty('value');
            if (! is_null($value) && $value !== ''){
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
                        throw new UxonParserError($uxon, 'Cannot use comparator "' . $comp . '" with a list of values "' . $value . '"!');    
                    }
                }
                $this->setValue($value);
            }
        } else {
            
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
        return ! $this->rightExprIsSet || ! $this->leftExprIsSet;
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
    public function copy()
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
            return $data_sheet;
        }
        
        if ($data_sheet === null && $row_number !== null) {
            throw new RuntimeException('Cannot evaluate a condition: do data provided!');
        }
        
        $leftVal = $this->getExpression()->evaluate($data_sheet, $row_number);
        $rightVal = $this->getValue(); // Value is already parsed via datatype in setValue()

        $listDelimiter = $this->getExpression()->isMetaAttribute() ? $this->getExpression()->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
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
                return $rightVal === null || mb_stripos($leftVal, $rightVal) !== false;
            case ComparatorDataType::IS_NOT:
                return mb_stripos($leftVal, $rightVal ?? '') === false;
            case ComparatorDataType::EQUALS:
                return $leftVal === $rightVal;
            case ComparatorDataType::EQUALS_NOT:
                return $leftVal !== $rightVal;
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
     *
     * @return string|NULL
     */
    protected function getBaseObject() : ?MetaObjectInterface
    {
        if ($this->baseObject === null && $this->baseObjectSelector !== null) {
            $this->baseObject = $this->getWorkbench()->model()->getObject($this->baseObjectSelector);
        }
        return $this->baseObject;
    }
    
    protected function hasBaseObject() : bool
    {
        return $this->baseObject !== null || $this->baseObjectSelector !== null;
    }
    
    /**
     * All expressions within this condition will be resolved based on this object.
     *
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     *
     * @param string $value
     * @return ConditionGroup
     */
    protected function setObjectAlias(string $aliasWithNamespaceOrUid) : ConditionInterface
    {
        $this->baseObjectSelector = $aliasWithNamespaceOrUid;
        $this->baseObject = null;
        return $this;
    }
    
    protected function setLeftExpression($expressionOrStringOrUxon) : Condition
    {
        if ($expressionOrStringOrUxon instanceof ExpressionInterface) {
            $this->leftExpr = $expressionOrStringOrUxon;
            $this->leftExprRaw = null;
            $this->data_type = $expressionOrStringOrUxon->getDataType();
        } else {
            $this->leftExpr = null;
            $this->leftExprRaw = $expressionOrStringOrUxon;
        }
        // TODO check if data types of the two condition sides are compatible!
        $this->leftExprIsSet = true;
        return $this;
    }
    
    public function getLeftExpression() : ExpressionInterface
    {
        if ($this->leftExpr === null) {
            if ($this->leftExprRaw !== null) {
                $this->leftExpr = $this->exface->model()->parseExpression($this->leftExprRaw, $this->getBaseObject());
            } else {
                $this->leftExpr = ExpressionFactory::createForObject($this->getBaseObject(), '');
            }
        }
        return $this->leftExpr;
    }
    
    protected function setRightExpression($expressionOrStringOrUxon) : Condition
    {
        if ($expressionOrStringOrUxon instanceof ExpressionInterface) {
            $this->rightExpr = $expressionOrStringOrUxon;
            $this->rightExprRaw = null;
        } else {
            $this->rightExpr = null;
            $this->rightExprRaw = $expressionOrStringOrUxon;
        }
        $this->rightExprIsSet = true;
        return $this;
    }
    
    public function getRightExpression() : ExpressionInterface
    {
        if ($this->rightExpr === null) {
            if ($this->rightExprRaw !== null) {
                $this->rightExpr = $this->exface->model()->parseExpression($this->rightExprRaw, $this->getBaseObject());
            } else {
                $this->rightExpr = ExpressionFactory::createForObject($this->getBaseObject(), '');
            }
        }
        return $this->rightExpr;
    }
}