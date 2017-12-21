<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\RelationDataType;

/**
 * .
 * Thus, a condition is basically
 * something like "expr = a" or "date > 01.01.1970", etc, while a ConditionGroup can be used to combine multiple conditions using
 * logical operators like AND, OR, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class Condition implements iCanBeConvertedToUxon
{

    private $exface = null;

    private $expression = null;

    private $value = null;
    
    private $value_set = false;

    private $comparator = null;

    private $data_type = null;

    /**
     *
     * @deprecated use ConditionFactory instead!
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    public function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Returns the expression to filter
     *
     * @return ExpressionInterface
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * Sets the expression that will be compared to the value
     *
     * @param ExpressionInterface $expression            
     */
    public function setExpression(ExpressionInterface $expression)
    {
        $this->expression = $expression;
    }

    /**
     * Returns the value to compare to
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Sets the value to compare to
     *
     * @param mixed $value            
     * @throws RangeException
     */
    public function setValue($value)
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
    }

    /**
     * Returns the comparison operator from this condition.
     * Normally it is one of the EXF_COMPARATOR_xxx constants.
     *
     * @return string
     */
    public function getComparator()
    {
        if (is_null($this->comparator)) {
            $this->comparator = $this->guessComparator();
        }
        return $this->comparator;
    }
    
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
        $this->setValue($value);
        
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
     * Sets the comparison operator for this condition.
     * Use one of the EXF_COMPARATOR_xxx constants.
     *
     * @param string $value            
     * @throws UnexpectedValueException if the value does not match one of the EXF_COMPARATOR_xxx constants
     * @return Condition
     */
    public function setComparator($value)
    {
        try {
            $this->comparator = static::sanitizeComparator($value);
        } catch (UnexpectedValueException $e){
            throw new UnexpectedValueException('Invalid comparator value in condition "' . $this->getExpression()->toString() . ' ' . $value . ' ' . $this->getValue() . '"!', '6W1SD52', $e);
        }
        
        return $this;
    }
    
    public static function sanitizeComparator($value){
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
     * @return AbstractDataType
     */
    public function getDataType()
    {
        if (is_null($this->data_type)) {
            $this->data_type = DataTypeFactory::createBaseDataType($this->exface);
        }
        return $this->data_type;
    }

    /**
     *
     * @param AbstractDataType $value            
     */
    public function setDataType(AbstractDataType $value)
    {
        $this->data_type = $value;
    }

    /**
     * Returns the attribute_alias to filter if the filter is based upon an attribute or FALSE otherwise
     *
     * @return string|boolean
     */
    public function getAttributeAlias()
    {
        if ($this->getExpression()->isMetaAttribute()) {
            return $this->getExpression()->toString();
        } else {
            return false;
        }
    }

    public function toString()
    {
        return $this->getExpression()->toString() . ' ' . $this->getComparator() . ' ' . $this->getValue();
    }

    public function __toString()
    {
        return $this->toString();
    }

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
     * @param UxonObject $uxon_object            
     */
    public function importUxonObject(UxonObject $uxon_object)
    {
        if ($uxon_object->hasProperty('expression')) {
            $expression = $uxon_object->getProperty('expression');
        } elseif ($uxon_object->hasProperty('attribute_alias')) {
            $expression = $uxon_object->getProperty('attribute_alias');
        }
        $this->setExpression($this->exface->model()->parseExpression($expression, $this->exface->model()->getObject($uxon_object->getProperty('object_alias'))));
        if ($uxon_object->hasProperty('comparator') && $uxon_object->getProperty('comparator')) {
            $this->setComparator($uxon_object->getProperty('comparator'));
        }
        if ($uxon_object->hasProperty('value')){
            $value = $uxon_object->getProperty('value');
            if (! is_null($value) && $value !== ''){
                $this->setValue($value);
            }
        }
    }

    public function getModel()
    {
        return $this->exface->model();
    }
    
    /**
     * Returns TRUE if the condition does not affect anything and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isEmpty()
    {
        return ! $this->value_set;
    }
    
    /**
     * Unsets the value of the condition making query builders etc. ignore it.
     * 
     * @return Condition
     */
    public function unsetValue()
    {
        $this->value = null;
        $this->value_set = false;
    }
}