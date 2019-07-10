<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\WidgetInterface;

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

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::getValues()
     */
    public function getValues()
    {
        // TODO set selected table rows programmatically
        /*
         * if ($this->getValue()){
         * return explode(EXF_LIST_SEPARATOR, $this->getValue());
         * }
         */
        return array();
    }
    
    public function getValueWithDefaults()
    {
        // TODO return the UID of programmatically selected row
        return null;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValues()
     */
    public function setValues($expression_or_delimited_list)
    {
        // TODO set selected table rows programmatically
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValuesFromArray()
     */
    public function setValuesFromArray(array $values)
    {
        $this->setValue(implode($this->getUidColumn()->getAttribute()->getValueListDelimiter(), $values));
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
    public function hasValue()
    {
        return is_null($this->getValue()) ? false : true;
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