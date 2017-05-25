<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\UnexpectedValueException;

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

    private $exface = NULL;

    private $expression = NULL;

    private $value = NULL;

    private $comparator = EXF_COMPARATOR_IS;

    private $data_type = NULL;

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
     * @return Expression
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * Sets the expression that will be compared to the value
     * 
     * @param Expression $expression            
     */
    public function setExpression(Expression $expression)
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
        try {
            $value = $this->getDataType()->parse($value);
        } catch (\Throwable $e) {
            throw new RangeException('Illegal filter value "' . $value . '" for attribute "' . $this->getAttributeAlias() . '" of data type "' . $this->getExpression()
                ->getAttribute()
                ->getDataType()
                ->getName() . '": ' . $e->getMessage(), '6T5WBNB', $e);
            $value = null;
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
            $this->comparator = EXF_COMPARATOR_IS;
        }
        return $this->comparator;
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
        $this->comparator = $value;
        
        if (! $validated) {
            throw new UnexpectedValueException('Invalid comparator value in condition "' . $this->getExpression()->toString() . ' ' . $value . ' ' . $this->getValue() . '"!');
        }
        return $this;
    }

    /**
     *
     * @return AbstractDataType
     */
    public function getDataType()
    {
        if (is_null($this->data_type)) {
            $this->data_type = DataTypeFactory::createFromAlias($this->exface, EXF_DATA_TYPE_STRING);
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
        $uxon->expression = $this->getExpression()->toString();
        $uxon->comparator = $this->getComparator();
        $uxon->value = $this->getValue();
        $uxon->object_alias = $this->getExpression()
            ->getMetaObject()
            ->getAliasWithNamespace();
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
        $this->setExpression($this->exface->model()
            ->parseExpression($expression, $this->exface->model()
            ->getObject($uxon_object->getProperty('object_alias'))));
        if ($uxon_object->hasProperty('comparator') && $uxon_object->getProperty('comparator')) {
            $this->setComparator($uxon_object->getProperty('comparator'));
        }
        $this->setValue($uxon_object->getProperty('value'));
    }

    public function getModel()
    {
        return $this->exface->model();
    }
}