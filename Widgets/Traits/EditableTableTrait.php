<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveValues;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ExpressionFactory;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
trait EditableTableTrait
{    
    private $required = false;
    
    private $readOnly = false;
    
    private $displayOnly = false;
    
    private $preselectedUids = [];
    
    private $preselectExpression = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::getValues()
     */
    public function getValues() : array
    {
        return $this->preselectedUids;
    }
    
    public function getValueWithDefaults()
    {
        return $this->getValue();
    }
    
    protected function getValueListDelimiter() : string
    {
        if ($this->hasUidColumn()) {
            $delim = $this->getUidColumn()->getAttribute()->getValueListDelimiter();
        } else {
            $delim = EXF_LIST_SEPARATOR;
        }
        return $delim;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValues()
     */
    public function setValues($expressionOrArrayOrDelimitedString, bool $parseStringAsExpression = true) : iHaveValues
    {
        switch (true) {
            case is_array($expressionOrArrayOrDelimitedString):
                return $this->setValuesFromArray($expressionOrArrayOrDelimitedString);
            case is_string($expressionOrArrayOrDelimitedString):
                return $this->setValuesFromArray(explode($this->getValueListDelimiter(), $expressionOrArrayOrDelimitedString));
            default:
                throw new InvalidArgumentException('Cannot use "' . gettype($expressionOrArrayOrDelimitedString . '" as value list for widget ' . $this->getWidgetType() . '!')); 
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValuesFromArray()
     */
    public function setValuesFromArray(array $values, bool $parseStringsAsExpressions = true) : iHaveValues
    {
        $this->preselectedUids = $values;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueDataType()
     */
    public function getValueDataType()
    {
        return $this->getUidColumn()->getDataType();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::hasValue()
     */
    public function hasValue() : bool
    {
        return ! empty($this->getValues());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::hasValue()
     */
    public function setValue($expressionOrString, bool $parseStringAsExpression = true)
    {
        switch (true) {
            case is_array($expressionOrString):
                $this->setValues($expressionOrString);
                $expr = ExpressionFactory::createAsScalar($this->getWorkbench(), $this->getValue(), $this->getMetaObject());
                break;
            case $expressionOrString instanceof ExpressionInterface:
                $expr = $expressionOrString;
                break;
            case $parseStringAsExpression === false:
                $expr = ExpressionFactory::createAsScalar($this->getWorkbench(), $expressionOrString, $this->getMetaObject());
                if ($expr->isStatic()) {
                    $this->setValues($expr->evaluate());
                }
                break;
            default:
                $expr = ExpressionFactory::createForObject($this->getMetaObject, $expressionOrString);
        }
        $this->preselectExpression = $expr;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValue()
     */
    public function getValue()
    {
        return implode($this->getValueListDelimiter(), $this->getValues());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueExpression()
     */
    public function getValueExpression() : ?ExpressionInterface
    {
        return $this->preselectExpression;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWidgetLink()
     */
    public function getValueWidgetLink() : ?WidgetLinkInterface
    {
        $link = null;
        $expr = $this->getValueExpression();
        if ($expr && $expr->isReference()) {
            $link = $expr->getWidgetLink($this);
        }
        return $link;
    }
    
    /**
     * Set to TRUE to force the user to fill all required fields of at least one row.
     * 
     * @uxon-property required
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\Widgets\iCanBeRequired::setRequired()
     */
    public function setRequired($value)
    {
        $this->required = BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanBeRequired::isRequired()
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * If set to TRUE, the table remains fully interactive, but it's data will be ignored by actions.
     * 
     * @uxon-property display_only
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setDisplayOnly()
     */
    public function setDisplayOnly($true_or_false) : iTakeInput
    {
        $this->displayOnly = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isDisplayOnly()
     */
    public function isDisplayOnly() : bool
    {
        if ($this->isReadonly() === true) {
            return true;
        }
        return $this->displayOnly;
    }

    /**
     * In a DataTable readonly is the opposite of editable, so there is no point in an
     * extra uxon-property here.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setReadonly()
     */
    public function setReadonly($true_or_false) : WidgetInterface
    {
        $this->setEditable(! BooleanDataType::cast($true_or_false));
        return $this;
    }

    /**
     * A DataTable is readonly as long as it is not editable.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isReadonly()
     */
    public function isReadonly() : bool
    {
        return $this->isEditable() === false;
    }
}