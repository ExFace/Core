<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\Widgets\iHaveDefaultValue;
use exface\Core\DataTypes\BooleanDataType;

class Input extends Text implements iTakeInput, iHaveDefaultValue
{

    private $required = null;

    private $validator = null;

    private $readonly = false;

    private $display_only = false;

    public function getValidator()
    {
        return $this->validator;
    }

    public function setValidator($value)
    {
        $this->validator = $value;
    }

    /**
     *
     * {@inheritdoc} Input widgets are considered as required if they are explicitly marked as such or if the represent a meta attribute,
     *               that is a required one.
     *              
     *               IDEA It's not quite clear, if automatically marking an input as required depending on it's attribute being required,
     *               is a good idea. This works well for forms creating objects, but what if the form is used for something else? If there
     *               will be problems with this feature, the alternative would be making the EditObjectAction loop through it's widgets
     *               and set the required flag depending on attribute setting.
     *              
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isRequired()
     */
    public function isRequired()
    {
        if (is_null($this->required)) {
            if ($this->getAttribute()) {
                return $this->getAttribute()->isRequired();
            } else {
                return false;
            }
        }
        return $this->required;
    }

    public function setRequired($value)
    {
        $this->required = $value;
    }

    /**
     * Input widgets are disabled if the displayed attribute is not editable or if the widget was explicitly disabled.
     * 
     * @see \exface\Core\Widgets\AbstractWidget::isDisabled()
     */
    public function isDisabled()
    {
        if ($this->isReadonly()) {
            return true;
        }
        
        $disabled = parent::isDisabled();
        if (is_null($disabled)) {
            try {
                if ($this->getMetaObject()->hasAttribute($this->getAttributeAlias()) && ! $this->getAttribute()->isEditable()) {
                    $disabled = true;
                } else {
                    $disabled = false;
                }
            } catch (MetaAttributeNotFoundError $e) {
                // Ignore invalid attributes
            }
        }
        return $disabled;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isReadonly()
     */
    public function isReadonly()
    {
        return $this->readonly;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setReadonly()
     */
    public function setReadonly($value)
    {
        $this->readonly = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getDefaultValue()
     */
    public function getDefaultValue()
    {
        if (! $this->getIgnoreDefaultValue() && $default_expr = $this->getDefaultValueExpression()) {
            if ($data_sheet = $this->getPrefillData()) {
                $value = $default_expr->evaluate($data_sheet, \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getAttribute()
                    ->getAlias()), 0);
            } elseif ($default_expr->isString()) {
                $value = $default_expr->getRawValue();
            }
        }
        return $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getDefaultValueExpression()
     */
    public function getDefaultValueExpression()
    {
        if ($attr = $this->getAttribute()) {
            if (! $default_expr = $attr->getFixedValue()) {
                $default_expr = $attr->getDefaultValue();
            }
        }
        return $default_expr;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getIgnoreDefaultValue()
     */
    public function getIgnoreDefaultValue()
    {
        return $this->ignore_default_value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setIgnoreDefaultValue()
     */
    public function setIgnoreDefaultValue($value)
    {
        $this->ignore_default_value = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     * Inputs have a separate default placeholder value (mostly none).
     * Placeholders should be specified manually for each
     * widget to give the user a helpful hint.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Text::getEmptyText()
     */
    public function getEmptyText()
    {
        if (parent::getEmptyText() == $this->translate('WIDGET.TEXT.EMPTY_TEXT')) {
            parent::setEmptyText($this->translate('WIDGET.INPUT.EMPTY_TEXT'));
        }
        return parent::getEmptyText();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isDisplayOnly()
     */
    public function isDisplayOnly()
    {
        if ($this->isReadonly()) {
            return true;
        }
        return $this->display_only;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setDisplayOnly()
     */
    public function setDisplayOnly($value)
    {
        $this->display_only = BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Text::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('display_only', $this->isDisplayOnly());
        $uxon->setProperty('readonly', $this->isReadonly());
        $uxon->setProperty('required', $this->isRequired());
        return $uxon;
    }
}
?>