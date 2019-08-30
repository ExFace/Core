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
 * {
 *  "object_alias": "my.App.myObject",
 *  "expression": "myAttribute",
 *  "comparator": "=",
 *  "value" = "myValue"
 * }
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

    private $value = null;
    
    private $value_set = false;

    private $comparator = null;

    private $data_type = null;

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
     * The left side of the condition.
     * 
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
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
     * The right side of the condition.
     * 
     * @uxon-property value
     * @uxon-type metamodel:expression
     * 
     * @param string $value
     * @throws RangeException
     * @return ConditionInterface
     */
    protected function setValue(string $value) : ConditionInterface
    {
        $this->value_set = true;
        try {
            $value = $this->getDataType()->parse($value);
        } catch (\Throwable $e) {
            throw new RangeException('Illegal filter value "' . $value . '" for attribute "' . $this->getAttributeAlias() . '" of data type "' . $this->getExpression()->getAttribute()->getDataType()->getName() . '": ' . $e->getMessage(), '6T5WBNB', $e);
            $value = null;
            $this->unset();
        }
        $this->value = $value;
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
            && ($base_object->getAttribute($expression_string)->getDataType() instanceof NumberDataType
                || $base_object->getAttribute($expression_string)->getDataType() instanceof RelationDataType
                )
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
        $uxon->setProperty('expression', $this->getExpression()->toString());
        $uxon->setProperty('comparator', $this->getComparator());
        $uxon->setProperty('value', $this->getValue());
        $uxon->setProperty('object_alias', $this->getExpression()->getMetaObject()->getAliasWithNamespace());
        return $uxon;
    }

    /**
     * Imports data from UXON objects like {"object_alias": "...", "expression": "...", "value": "...", "comparator": "..."}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()            
     */
    public function importUxonObject(UxonObject $uxon_object)
    {
        if (! $this->isEmpty()) {
            throw new LogicException('Cannot import UXON description into a non-empty condition (' . $this->toString() . ')!');
        }
        if ($uxon_object->hasProperty('expression')) {
            $expression = $uxon_object->getProperty('expression');
        } elseif ($uxon_object->hasProperty('attribute_alias')) {
            $expression = $uxon_object->getProperty('attribute_alias');
        }
        if (! $objAlias = $uxon_object->getProperty('object_alias')) {
            throw new UxonParserError($uxon_object, 'Invalid UXON condition syntax: Missing object alias!');
        }
        $this->setExpression($this->exface->model()->parseExpression($expression, $this->exface->model()->getObject($objAlias)));
        if ($uxon_object->hasProperty('comparator') && $comp = $uxon_object->getProperty('comparator')) {
            $this->setComparator($comp);
        }
        if ($uxon_object->hasProperty('value')){
            $value = $uxon_object->getProperty('value');
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
                        throw new UxonParserError($uxon_object, 'Cannot use comparator "' . $comp . '" with a list of values "' . $value . '"!');    
                    }
                }
                $this->setValue($value);
            }
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
    public function copy()
    {
        return ConditionFactory::createFromUxon($this->getWorkbench(), $this->exportUxonObject());
    }

}