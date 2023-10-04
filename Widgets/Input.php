<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\Widgets\iHaveDefaultValue;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\CommonLogic\UxonObject;

/**
 * A generic input field: a single line accepting any set of characters.
 * 
 * `Input` is the base for all sorts of input-widgets like `InputNumber`, `InputDate`, etc.
 * They are all meant to collect user input in forms, different configurator widgets, editable
 * table columns and so on.
 * 
 * Inputs will validate the received data against their `value_data_type` and display errors
 * and/or hints if this validation fails. You can also add custom validation via `invalid_if`.
 * 
 * Generic inputs like `Input` or `InputHidden` can optionally accept multiple values separated
 * by a delimiter. Use `multiple_values_allowed` and `multiple_values_delimiter` to control this.
 * 
 * Actions in forms and dialogs get their input data from input widgets, unless these are
 * marked `readonly` or `display_only`. Note: `disabled` inputs still pass their data to actions,
 * they merely disallow user interaction!
 * 
 * @author Andrej Kabachnik
 *
 */
class Input extends Value implements iTakeInput, iHaveDefaultValue, iCanBeRequired
{
    /**
     * Focus the input
     *
     * @uxon-property focus
     *
     * @var string
     */
    const FUNCTION_FOCUS = 'focus';
    
    /**
     * Empty the input
     * 
     * Inputs are emptied automatically on some ocasions:
     * - when a `hidden_if` hides the widget
     * - when a `disabled_if` disables the widget - unless the `value` is a link to another widget
     *
     * @uxon-property empty
     *
     * @var string
     */
    const FUNCTION_EMPTY = 'empty';
    
    /**
     * Mark the input as required
     *
     * @uxon-property require
     *
     * @var string
     */
    const FUNCTION_REQUIRE = 'require';
    
    /**
     * Make the input optional (not required)
     *
     * @uxon-property unrequire
     *
     * @var string
     */
    const FUNCTION_UNREQUIRE = 'unrequire';
    
    private $required = null;
    
    private $requiredIf = null;
    
    private $invalidIf = null;

    private $readonly = false;

    private $display_only = false;
    
    private $allowMultipleValues = false;
    
    private $multiValueDelimiter = null;
    
    private $disableValidation = false;

    /**
     * Input widgets are considered as required if they are explicitly marked as such or if the represent a meta attribute,
     * that is a required one.
     *              
     * IDEA It's not quite clear, if automatically marking an input as required depending on it's attribute being required,
     * is a good idea. This works well for forms creating objects, but what if the form is used for something else? If there
     * will be problems with this feature, the alternative would be making the EditObjectAction loop through it's widgets
     * and set the required flag depending on attribute setting.
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isRequired()
     */
    public function isRequired()
    {
        if ($this->required === null) {
            if ($this->getAttribute()) {
                return $this->getAttribute()->isRequired();
            } else {
                return false;
            }
        }
        return $this->required;
    }

    /**
     * Marks the widget as mandatory input (TRUE) or optional (FALSE).
     * 
     * If not set, the widget will automatically become required if it allows input for
     * a required attribute of the metamodel. Use this property to override this behavior.
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
     * @return ConditionalProperty|NULL
     */
    public function getRequiredIf(): ?ConditionalProperty
    {
        if ($this->requiredIf === null) {
            return null;
        }
        
        if (! ($this->requiredIf instanceof ConditionalProperty)) {
            $this->requiredIf = new ConditionalProperty($this, 'required_if', $this->requiredIf);
        }
        
        return $this->requiredIf;
    }
    
    /**
     * Sets a condition to make the widget required.
     *
     * E.g. make an `Input` required if a checkbox is checked:
     *
     * ```json
     *  {
     *      "widget_type": "Input",
     *      "required_if": {
     *          "value_left": "=id_of_checkbox",
     *          "comparator": "==",
     *          "value_right": "1"
     *      }
     *  }
     *
     * ```
     *
     * @uxon-property required_if
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]}
     *
     * @param UxonObject $value
     * @return \exface\Core\Widgets\AbstractWidget
     */
    public function setRequiredIf(UxonObject $uxon): iCanBeRequired
    {
        $this->requiredIf = $uxon;
        return $this;
    }   
    
    /**
     *
     * @return ConditionalProperty|NULL
     */
    public function getInvalidIf(): ?ConditionalProperty
    {
        if ($this->invalidIf === null) {
            return null;
        }
        
        if (! ($this->invalidIf instanceof ConditionalProperty)) {
            $this->invalidIf = new ConditionalProperty($this, 'invalid_if', $this->invalidIf);
        }
        
        return $this->invalidIf;
    }
    
    /**
     * Sets a condition to make the widget invalid.
     *
     * E.g. make an `Input` invalid if its value is less than that of another one:
     *
     * ```json
     *  {
     *      "widget_type": "Input",
     *      "invalid_if": {
     *          "value_left": "=~self",
     *          "comparator": "<",
     *          "value_right": "=id_of_other_input"
     *      }
     *  }
     *
     * ```
     *
     * @uxon-property invalid_if
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]}
     *
     * @param UxonObject $value
     * @return \exface\Core\Widgets\AbstractWidget
     */
    public function setInvalidIf(UxonObject $uxon): Input
    {
        $this->invalidIf = $uxon;
        return $this;
    }    

    /**
     * Input widgets are disabled if the displayed attribute is not editable or if the widget was explicitly disabled.
     *
     * @see \exface\Core\Widgets\AbstractWidget::isDisabled()
     */
    public function isDisabled() : ?bool
    {
        if ($this->isReadonly()) {
            return true;
        }
        
        $disabled = parent::isDisabled();
        if (is_null($disabled)) {
            try {
                if ($this->isBoundToAttribute() && ! $this->getAttribute()->isEditable()) {
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
     * Set to TRUE to make the input inactive, while still being regarded as action input.
     * 
     * Input widgets are automatically disabled if they display a non-editable attribute.
     * 
     * The following states of input widgets are available:
     * - display_only = true - active (user can interact with the widget), but not considered as input for actions
     * - disabled = true - inactive (user cannot interact with the widget), but considered as input for action
     * - readonly = true - inactive and not considered as action input (same as display_only + disabled)
     * 
     * If a widget is readonly, will also get display-only and disabled automatically.
     * 
     * @uxon-property disabled
     * @uxon-type boolean
     * @uxon-default false
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setDisabled()
     */
    public function setDisabled(?bool $trueOrFalseOrNull, string $reason = null) : WidgetInterface
    {
        return parent::setDisabled($trueOrFalseOrNull, $reason);
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isReadonly()
     */
    public function isReadonly() : bool
    {
        return $this->readonly;
    }

    /**
     * Set to TRUE to make the widget inactive and ignored by actions - FALSE by default.
     * 
     * The following states of input widgets are available:
     * 
     * - display_only = true - active (user can interact with the widget), but not considered as input for actions
     * - disabled = true - inactive (user cannot interact with the widget), but considered as input for action
     * - readonly = true - inactive and not considered as action input (same as display_only + disabled)
     * 
     * If a widget is readonly, will also get display-only and disabled automatically.
     * 
     * @uxon-property readonly
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setReadonly()
     */
    public function setReadonly($value) : WidgetInterface
    {
        $this->readonly = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
                $value = $default_expr->evaluate($data_sheet, 0);
            } elseif ($default_expr->isStatic()) {
                $value = $default_expr->evaluate();
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
     * Set to TRUE to not use the default value from the metamodel.
     * 
     * By default, an input showing an attribute will use default and fixed values from
     * the attribtue's metamodel. You can explicitly remove the widget's default value
     * by setting `ignore_default_value = TRUE`.
     * 
     * @uxon-property ignore_default_value
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setIgnoreDefaultValue()
     */
    public function setIgnoreDefaultValue($value)
    {
        $this->ignore_default_value = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isDisplayOnly()
     */
    public function isDisplayOnly() : bool
    {
        if ($this->isReadonly()) {
            return true;
        }
        return $this->display_only;
    }

    /**
     * Makes the widget display-only if set to TRUE (= interactive, but being ignored by most actions) - FALSE by default.
     * 
     * The following states of input widgets are available:
     * 
     * - display_only = true - active (user can interact with the widget), but not considered as input for actions
     * - disabled = true - inactive (user cannot interact with the widget), but considered as input for action
     * - readonly = true - inactive and not considered as action input (same as display_only + disabled)
     * 
     * If a widget is readonly, will also get display-only and disabled automatically.
     * 
     * @uxon-property display_only
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setDisplayOnly()
     */
    public function setDisplayOnly($value) : iTakeInput
    {
        $this->display_only = BooleanDataType::cast($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::getValueWithDefaults()
     */
    public function getValueWithDefaults()
    {
        $value = parent::getValueWithDefaults();
        if ($value === null || $value === '') {
            $value = $this->getDefaultValue();
        }
        return $value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveDefaultValue::hasDefaultValue()
     */
    public function hasDefaultValue() : bool
    {
        $def = $this->getDefaultValue();
        return $def !== null && $def !== '';
    }
    
    /**
     *
     * @return bool
     */
    public function getMultipleValuesAllowed() : bool
    {
        return $this->allowMultipleValues;
    }
    
    /**
     * Set to TRUE to allow input of multiple values sepearted by the `multiple_value_delimiter`.
     * 
     * @uxon-property multiple_values_allowed
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return Input
     */
    public function setMultipleValuesAllowed(bool $value) : Input
    {
        $this->allowMultipleValues = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getMultipleValuesDelimiter() : string
    {
        if (is_null($this->multiValueDelimiter)){
            if ($this->getAttribute()){
                $this->multiValueDelimiter = $this->getAttribute()->getValueListDelimiter();
            } else {
                $this->multiValueDelimiter = EXF_LIST_SEPARATOR;
            }
        }
        return $this->multiValueDelimiter;
    }
    
    /**
     * Separator to use when `multiple_values_allowed` is `true`.
     * 
     * If the input is bound to an attribute from the meta model, that attribute's
     * value list delimiter is used by default. Otherwise a comma `,` is the default
     * delimiter.
     * 
     * @uxon-property multiple_values_delimiter
     * @uxon-type string
     * @uxon-default ,
     * 
     * @param string $value
     * @return Input
     */
    public function setMultipleValuesDelimiter(string $value) : Input
    {
        $this->multiValueDelimiter = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getHiddenIf()
     */
    public function getHiddenIf() : ?ConditionalProperty
    {
        $condProp = parent::getHiddenIf();
        if ($condProp !== null && $condProp->getFunctionOnTrue() === null) {
            $condProp->setFunctionOnTrue(self::FUNCTION_EMPTY);
        }
        return $condProp;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getDisabledIf()
     */
    public function getDisabledIf() : ?ConditionalProperty
    {
        $condProp = parent::getDisabledIf();
        if ($condProp !== null && $condProp->getFunctionOnTrue() === null && $this->getCalculationWidgetLink() === null) {
            $condProp->setFunctionOnTrue(self::FUNCTION_EMPTY);
        }
        return $condProp;
    }
    
    /**
     * 
     * @return bool
     */
    public function getDisableValidation() : bool
    {
        return $this->disableValidation;
    }
    
    /**
     * Set to TRUE to disable data-type specific validation like min/max values etc.
     * 
     * Normally a widget will validate input automatically and make sure it fits the constraints defined in the data type
     * of its attributes. However, you can disable this for a specific widget to allow any input the widget type allows.
     * 
     * For example, if you have a date attribute, that must be a future date, you will set `min:0` in the data type
     * customization. Any input for this attribute will now accept future dates only. However, this will also be true
     * for filters, where you still need past days too. Filters will turn off this type of validation automatically, but
     * you can also do it manually if required.
     * 
     * @uxon-property disable_validation
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return Input
     */
    public function setDisableValidation(bool $value) : Input
    {
        $this->disableValidation = $value;
        return $this;
    }
}
?>