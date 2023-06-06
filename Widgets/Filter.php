<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\Widgets\WidgetPropertyUnknownError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Exceptions\Widgets\WidgetLogicError;

/**
 * A filter for data widgets, etc - consists of a logical comparator and an input widget.
 * 
 * All you need for a simple filter is the `attribute_alias` to filter over. Such filter will
 * get the default editor of the attribute as `input_widget` automatically and the logical
 * comparison operation will be determined from the data type: i.e. the `=` comparator will
 * be used in most cases.
 * 
 * If you need more control over the filter use the `comparator` (e.g. `!=`, `>`, `<`, etc.) and 
 * `input_widget` properties to override the defaults. Adding a `value` will give the filter a 
 * default value (see examples below). You can also mark a filter as `required` to prevent 
 * loading data if it has no value.
 * 
 * Note, that generic inputs `Input` or `InputHidden` will automatically get `multiple_values_allowed` 
 * set to `true`, so they can be used with in-comparators (`[`, `![`) by default. This means, you can 
 * type "value1, value2" into a regular `Input` filter and will get an `OR`-search automatically.
 * 
 * To create complex filter with multiple conditions, you can specify a custom `condition_group`
 * with as many conditions on different attributes as you like. Use the `[#value#]` in the
 * `value` property of a condition to get the current value from the filter's input widget.
 * 
 * You can make force the data widget to reload automatically once a filter's value changes
 * by setting `apply_on_change` to `true` for this filter. This is especially usefull for
 * filters with values linked to other widgets like those in master-details scenarios.
 * 
 * If the filter belongs to a widget, that supports quick search, you can include it in the
 * quick search conditions by setting `include_in_quick_search` to `true`: the filter will
 * be used as a further OR-condition when performing a quick search.
 * 
 * ## Examples
 * 
 * ### Simple filters
 * 
 * Providing an `attribute_alias` is enough to start with. Use relation concatennation via `__`
 * to filter over related attributes.
 * 
 * ```
 *  {
 *      "attribute_alias": "my_attribute"
 *  },
 *  {
 *      "attribute_alias": "relation_to__my_attribute"
 *  },
 *  {
 *      "attribute_alias": "valid_to",
 *      "comparator": ">=",
 *      "value": "now"
 *  }
 * 
 * ```
 * 
 * Note the relative date value `now` for the last date filter - giving default values to
 * date filters in all kinds of logs can greatly improve performance: e.g. show only today's
 * entries or those of the last two weeks (i.e. `value: >=2w`)!
 * 
 * ### Hidden and disabled filters
 * 
 * Mark a filter `disabled` to prevent users from changing it's values.
 * 
 * Use hidden filters to make the entire data widget operate on a subset of the data: for example 
 * only showing visible items no matter what other filters are set. Such filters are common for 
 * dashboards, where you need your tables and chart to only show rows with certain properties.
 * 
 * ```
 *  {
 *      "attribute_alias": "state",
 *      "value": "10",
 *      "disabled": "true"
 *  },
 *  {
 *      "attribute_alias": "visible_flag",
 *      "value": "1",
 *      "comparator": "==",
 *      "input_widget": {
 *          "widget_type": "InputHidden"
 *      }
 *  }
 * 
 * ```
 * 
 * Using `InputHidden` as `input_widget` makes sure, the user can neither see nor change the value. Use 
 * `InputHidden` instead of `hidden: true` because the `InputHidden` is a very simple widget and 
 * generally performs better than hidden default editors. 
 * 
 * It is also a good idea to specify a `comparator` for hidden filters to be sure, how the filtering
 * will behave. 
 * 
 * ### Custom input widgets
 * 
 * When filtering over date-time attributes you can use `InputDate` widgets for input if filtering for
 * a specific time does not make much sense. This will simplify the user experience.
 * 
 * ```
 *  {
 *      "attribute_alias": "start_time",
 *      "input_widget": {
 *          "widget_type": "InputDate"
 *      }
 *  }
 * 
 * ```
 * 
 * ### Filter with a custom condition group
 * 
 * ```
 * {
 *   "caption": "Available titles",
 *   "condition_group": {
 *     "operator": "OR",
 *     "conditions": [
 *       {
 *         "expression": "title",
 *         "comparator": "=",
 *         "value": "[#value#]"
 *       },
 *       {
 *         "expression": "available_flag",
 *         "comparator": "==",
 *         "value": "1"
 *       }
 *     ]
 *   }
 * }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class Filter extends AbstractWidget implements iTakeInput, iShowSingleAttribute, iCanPreloadData
{

    private $inputWidget = null;
    
    private $inputWidgetUxon = null;

    private $comparator = null;

    private $required = null;
    
    private $apply_on_change = false;
    
    private $customConditionGroupUxon = null;
    
    private $attributeAlias = null;
    
    private $includeInQuickSearch = false;
    
    private $displayOnly = false;
    
    private $emptyText = null;
    
    private $readonly = false;
    
    private $value = null;
    
    private $width = null;
    
    private $height = null;
    
    private $preloadConfig = null;
    
    private $preloader = null;
    
    private $useHiddenInput = false;
    
    /**
     * Returns TRUE if the input widget was already instantiated.
     * 
     * @return bool
     */
    protected function isInputWidgetInitialized() : bool
    {
        return $this->inputWidget !== null;    
    }
    
    /**
     * Returns TRUE if there was a custom input widget defined.
     * 
     * NOTE: Always returns TRUE if the input widget was already instantiated because then
     * it is not possible to distinguish a custom widget anymore!
     * 
     * @return bool
     */
    protected function hasCustomInputWidget() : bool
    {
        return $this->isInputWidgetInitialized() === true || $this->inputWidgetUxon !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        if ($uxon->hasProperty('attribute_alias') === true) {
            $this->setAttributeAlias($uxon->getProperty('attribute_alias'));
        } elseif (($uxon->getProperty('input_widget') instanceof UxonObject) && $uxon->getProperty('input_widget')->hasProperty('attribute_alias')) {
            $this->setAttributeAlias($uxon->getProperty('input_widget')->getProperty('attribute_alias'));
        }
        
        try {
            return parent::importUxonObject($uxon);
        } catch (WidgetPropertyUnknownError $e) {
            $inputProps = new UxonObject();
            foreach ($uxon->getPropertiesAll() as $prop => $val) {
                $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($prop);
                if (method_exists($this, $setterCamelCased) === false) {
                    $inputProps->setProperty($prop, $val);
                    $uxon->unsetProperty($prop);
                }
            }
            
            if ($inputProps->isEmpty() === false) {
                $inputUxon = $uxon->getProperty('input_widget') ?? new UxonObject();
                $inputUxon = $inputUxon->extend($inputProps);
                $uxon->setProperty('input_widget', $inputUxon);
            }
            
            return parent::importUxonObject($uxon);
        }
    }

    /**
     * Returns the widget used to interact with the filter (typically some kind of input widget)
     *
     * @return iTakeInput|iContainOtherWidgets
     */
    public function getInputWidget() : WidgetInterface
    {
        if ($this->isInputWidgetInitialized() === false) {
            $uxon = $this->getInputWidgetUxon() ?? new UxonObject();
            $this->setInputWidget($this->createInputWidget($uxon));
        }
        return $this->inputWidget;
    }

    /**
     * 
     * @return UxonObject|NULL
     */
    protected function getInputWidgetUxon() : ?UxonObject
    {
        return $this->inputWidgetUxon;
    }
    
    /**
     * 
     * 
     * @param UxonObject $uxon
     * @throws WidgetPropertyInvalidValueError
     * @return WidgetInterface
     */
    protected function createInputWidget(UxonObject $uxon) : WidgetInterface
    {
        // Look for the best configuration for the input_widget
        switch (true) {
            // If not UXON defined by user and the filter is explicitly hidden - use a simple `InputHidden`.
            case $this->useHiddenInput && $uxon->isEmpty() && $this->isBoundToAttribute():
                $defaultEditorUxon = new UxonObject([
                    'widget_type' => 'InputHidden',
                    'attribute_alias' => $this->getAttributeAlias()
                ]);
                break;
            // If the filter is bound to an attribute, use its default editor UXON
            case $this->isBoundToAttribute() === true:
                try {
                    $attr = $this->getMetaObject()->getAttribute($this->getAttributeAlias());
                } catch (MetaAttributeNotFoundError $e) {
                    throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for attribute alias "' . $this->getAttributeAlias() . '" in widget "' . $this->getParent()->getWidgetType() . '": attribute not found for object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!', '6T91AR9', $e);
                }
                
                // Set a special caption for filters on relations, which is derived from the relation itself
                // IDEA this might be obsolete since it probably allways returns the attribute name anyway, but I'm not sure
                if (false === $uxon->hasProperty('caption') && $attr->isRelation()) {
                    // Get the relation from the object and not $attr->getRelation() because the latter would
                    // yield the wrong relation direction in case of reverse reltions.
                    $uxon->setProperty('caption', $this->getMetaObject()->getRelation($this->getAttributeAlias())->getName());
                }
                
                // Try to use the default editor UXON of the attribute
                if ($attr->isRelation() === true && $this->getMetaObject()->getRelation($this->getAttributeAlias())->isReverseRelation() === true) {
                    $defaultEditorUxon = $this->getMetaObject()->getRelation($this->getAttributeAlias())->getDefaultEditorUxon()->extend($uxon);
                    if (! $defaultEditorUxon->hasProperty('attribute_alias')) {
                        $defaultEditorUxon->setProperty('attribute_alias', $this->getAttributeAlias());
                    }
                } else {
                    $defaultEditorUxon = $attr->getDefaultEditorUxon()->extend($uxon);
                    // Make sure to keep the attribute alias of the filter exactly as it was set.
                    // Otherwise modifiers like an aggregator will get lost because the default
                    // editor "thinks" it is a regular input for the attribute
                    $defaultEditorUxon->setProperty('attribute_alias', $this->getAttributeAlias());
                }
                break;
            case $this->hasCustomConditionGroup() === false && $this->hasCustomInputWidget() === false:
                throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for an empty attribute alias in widget "' . $this->getId() . '"!', '6T91AR9');
        } 
        
        if ($defaultEditorUxon && $defaultEditorUxon->isEmpty() === false) {
            // If the merged UXON from the default editor and the filter does not work,
            // create a widget from the explicitly defined filter UXON. This can happen
            // if the default editor presumes a widget type, that is not compatible with
            // properties, defined for the filter.
            // TODO this is not a very elegant solution: need a better way, to handle
            // conflicts between the default editor and the filter definition!
            try {
                $inputWidget = WidgetFactory::createFromUxonInParent($this, $defaultEditorUxon);
            } catch (WidgetPropertyUnknownError $e) {
                $inputWidget = null;
            }
        }
        
        if ($inputWidget === null) {
            if ($uxon->hasProperty('attribute_alias') === false && $this->isBoundToAttribute() === true) {
                $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
            }
            $inputWidget = WidgetFactory::createFromUxonInParent($this, $uxon, 'Input');
        }
        
        return $inputWidget;
    }

    /**
     * Sets the widget used to interact with the filter (typically some kind of input widget)
     * 
     * @uxon-property input_widget
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     * @uxon-template {"widget_type": ""}
     *
     * @param iTakeInput|iContainOtherWidgets|UxonObject $widget_or_uxon_object            
     * @return \exface\Core\Widgets\Filter
     */
    public function setInputWidget($widget_or_uxon_object) : Filter
    {
        if ($widget_or_uxon_object instanceof UxonObject) {
            // instantiate the widget later - when it is first requested via getInputWidget()
            $this->inputWidgetUxon = $widget_or_uxon_object;
            return $this;
        } elseif ($widget_or_uxon_object instanceof WidgetInterface) {
            if ($widget_or_uxon_object instanceof iTakeInput || $widget_or_uxon_object instanceof iContainOtherWidgets) {
                $input = $widget_or_uxon_object;
            } else {
                throw new WidgetConfigurationError('Cannot use widget "' . $widget_or_uxon_object->getWidgetType() . '" as input widget for a filter: only input widgets and containers supported!');
            }
        } else {
            throw new UnexpectedValueException('Invalid input_widget for a filter: expecting a UXON description or an instantiated widget, received "' . gettype($widget_or_uxon_object) . '" instead!');
        }
        
        $this->inputWidget = $this->enhanceInputWidget($input);
        
        return $this;
    }

    /**
     * 
     * @param WidgetInterface $input
     * @return WidgetInterface
     */
    protected function enhanceInputWidget(WidgetInterface $input) : WidgetInterface
    {
        // Some widgets need to be transformed to be a meaningfull filter
        if ($input->is('InputCheckBox')) {
            $input = $input->transformIntoSelect();
        }
        
        if ($input->getWidgetType() === 'Input' || $input->getWidgetType() === 'InputHidden') {
            $input->setMultipleValuesAllowed(true);
        }
        
        // Set a default comparator
        $defaultComparator = $this->getDefaultComparator($input);
        if ($this->comparator === null) {
            if ($defaultComparator !== null) {
                $this->setComparator($defaultComparator->__toString());
            }
        }
        
        if (parent::getCaption() !== null) {
            $input->setCaption(parent::getCaption());
        }
        
        // If the filter has a specific comparator, that is non-intuitive, add a corresponding suffix to
        // the caption of the input widget.
        $input = $this->enhanceInputWidgetWithComparatorHint($input);
        
        // The widgets in the filter should not be required accept for the case if the filter itself is marked
        // as required (see set_required()). This is important because, inputs based on required attributes are
        // marked required by default: this should not be the case for filters, however!
        if ($input instanceof iCanBeRequired) {
            $input->setRequired($this->required ?? false);
        }
        
        // Filters do not have default values, because they are empty if nothing has been entered. It is important
        // to tell the underlying widget to ignore defaults as it will use the default value of the meta attribute
        // otherwise. You can still set the value of the filter. This only prevents filling the value automatically
        // via the meta model defaults.
        if ($input instanceof iHaveValue) {
            $input->setIgnoreDefaultValue(true);
        }
        
        // The filter should be enabled all the time, except for the case, when it is diabled explicitly
        // In particularly, it's disabled-state should not depend on the settings of the attribute, etc.
        if (true === parent::isDisabled()) {
            $input->setDisabled(true);
        } else {
            $input->setDisabled(false);
        }
        
        if ($disableCond = parent::getDisabledIf()) {
            $input->setDisabledIf($disableCond->exportUxonObject());
        }
        
        // Simply inherit do_not_prefill
        $input->setDoNotPrefill(parent::getDoNotPrefill());
        
        if ($this->emptyText !== null) {
            $input->setEmptyText($this->emptyText);
        }
        
        // Pass readonly / display-only properties if applicable
        if ($input instanceof iTakeInput) {
            if ($this->displayOnly === true) {
                $input->setDisplayOnly(true);
            }
            if ($this->readonly === true) {
                $input->setReadonly(true);
            }
        }
        
        // Pass value if set and applicable
        if ($this->value !== null && $input instanceof iHaveValue) {
            $input->setValue($this->value);
        }
        
        if ($this->width !== null) {
            $input->setWidth($this->width);
        }
        
        if ($this->height !== null) {
            $input->setWidth($this->height);
        }
        
        if ($this->preloadConfig !== null && $input instanceof iCanPreloadData) {
            $input->setPreloadData($this->preloadConfig);
        }
        
        return $input;
    }
    
    /**
     * If the filter has a specific comparator, that is non-intuitive, add a corresponding 
     * suffix to the caption of the input widget.
     *  
     * @param WidgetInterface $input
     * @return WidgetInterface
     */
    protected function enhanceInputWidgetWithComparatorHint(WidgetInterface $input) : WidgetInterface
    {
        switch ($this->getComparator()) {
            case EXF_COMPARATOR_GREATER_THAN:
            case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS:
            case EXF_COMPARATOR_LESS_THAN:
            case EXF_COMPARATOR_LESS_THAN_OR_EQUALS:
                $input->setCaption($input->getCaption() . ' (' . $this->getComparator() . ')');
                break;
        }
        return $input;
    }
    
    /**
     * 
     * @param WidgetInterface $input
     * @return ComparatorDataType|NULL
     */
    protected function getDefaultComparator(WidgetInterface $input) : ?ComparatorDataType
    {
        switch (true) {
            case $input->implementsInterface('iSupportMultiselect') && $input->getMultiSelect():
                // If the input widget will produce multiple values, use the IN comparator
                return ComparatorDataType::IN($this->getWorkbench());
                break;
            case $input instanceof InputSelect:
                return ComparatorDataType::EQUALS($this->getWorkbench());
                break;
            default:
                // Otherwise leave the comparator null for other parts of the logic to use their defaults
                return null;
        }
    }

    /**
     *
     * @see \exface\Core\Widgets\Container::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getInputWidget();
    }

    /**
     *
     * @return MetaAttributeInterface
     */
    public function getAttribute() : ?MetaAttributeInterface
    {
        return $this->getInputWidget()->getAttribute();
    }

    /**
     *
     * @return string|NULL
     */
    public function getAttributeAlias()
    {
        if ($this->isInputWidgetInitialized() === false && $this->attributeAlias !== null) {
            return $this->attributeAlias;
        } 
        if ($this->isInputWidgetInitialized() === true && $this->getInputWidget() instanceof iShowSingleAttribute) {
            return $this->getInputWidget()->getAttributeAlias();
        }
        return null;
    }

    /**
     * The alias of the attribute to filter over.
     * 
     * This property will be automatically inherited by the input widget.
     * 
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @return \exface\Core\Widgets\Filter
     */
    public function setAttributeAlias($value)
    {
        $this->attributeAlias = $value;
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setAttributeAlias($value);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getValue()
     */
    public function getValue()
    {
        return $this->getInputWidget()->getValue();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWidgetLink()
     */
    public function getValueWidgetLink() : ?WidgetLinkInterface
    {
        return $this->getInputWidget()->getValueWidgetLink();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWithDefaults()
     */
    public function getValueWithDefaults()
    {
        return $this->getInputWidget()->getValueWithDefaults();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getValueExpression()
     */
    public function getValueExpression() : ?ExpressionInterface
    {
        return $this->getInputWidget()->getValueExpression();
    }

    /**
     * Initial value for the filter.
     * 
     * @uxon-property value
     * @uxon-type metamodel:expression
     * 
     * @see \exface\Core\Widgets\AbstractWidget::setValue()
     */
    public function setValue($value, bool $parseStringAsExpression = true)
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setValue($value, $parseStringAsExpression);
        }
        $this->value = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption() : ?string
    {
        return $this->getInputWidget()->getCaption();
    }

    /**
     * Magic method to forward all calls to methods, not explicitly defined in the filter to ist value widget.
     * Thus, the filter is a simple proxy from the point of view of the facade. However, it can be easily
     * enhanced with additional methods, that will override the ones of the value widget.
     * TODO this did not really work so far. Don't know why. As a work around, added some explicit proxy methods
     *
     * @param string $name            
     * @param array $arguments            
     */
    public function __call($name, $arguments)
    {
        $widget = $this->getInputWidget();
        return call_user_func_array(array(
            $widget,
            $name
        ), $arguments);
    }

    /**
     * 
     * @return string|NULL
     */
    public function getComparator() : ?string
    {
        return $this->comparator;
    }

    /**
     * The comparison operator for the filter.
     * 
     * Possible comparators:
     * 
     * - `=` - universal comparator similar to SQL's `LIKE` with % on both sides. Can compare different 
     * data types. If the left value is a string, becomes TRUE if it contains the right value. Case 
     * insensitive for strings
     * - `!=` - yields TRUE if `IS` would result in FALSE
     * - `==` - compares two single values of the same type. Case sensitive for stings. Normalizes the 
     * values before comparison though, so the date `-1 == 21.09.2020` will yield TRUE on the 22.09.2020. 
     * - `!==` - the inverse of `EQUALS`
     * - `[` - IN-comparator - compares to each vaule in a list via EQUALS. Becomes true if the left
     * value equals at least on of the values in the list within the right value. The list on the
     * right side must consist of numbers or strings separated by commas or the attribute's value
     * list delimiter if filtering over an attribute. The right side can also be another type of
     * expression (e.g. a formula or widget link), that yields such a list.
     * - `![` - the inverse von `[` . Becomes true if the left value equals none of the values in the 
     * list within the right value. The list on the right side must consist of numbers or strings separated 
     * by commas or the attribute's value list delimiter if filtering over an attribute. The right side can 
     * also be another type of expression (e.g. a formula or widget link), that yields such a list.
     * - `<` - yields TRUE if the left value is less than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     * - `<=` - yields TRUE if the left value is less than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     * - `>` - yields TRUE if the left value is greater than the right one. Both values must be of
     * comparable types: e.g. numbers or dates.
     * - `>=` - yields TRUE if the left value is greater than or equal to the right one. 
     * Both values must be of comparable types: e.g. numbers or dates.
     * 
     * @uxon-property comparator
     * @uxon-type metamodel:comparator
     * @uxon-default =
     * 
     * @param string $value
     * @throws WidgetPropertyInvalidValueError
     * @return \exface\Core\Widgets\Filter
     */
    public function setComparator(string $value) : Filter
    {
        if (! $value){
            return $this;
        }
        try {
            $this->comparator = Condition::sanitizeComparator($value);
        } catch (UnexpectedValueException $e){
            throw new WidgetPropertyInvalidValueError($this, 'Invalid comparator "' . $value . '" used for filter widget!', '6W1SD52', $e);
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanBeRequired::isRequired()
     */
    public function isRequired()
    {
        return $this->required || ($this->hasCustomInputWidget() === true && $this->getInputWidget() instanceof iCanBeRequired && $this->getInputWidget()->isRequired());
    }

    /**
     * Set to TRUE to make the filter mandatory (no search will be possible if it is not set!).
     * 
     * By default all filters are optional - regardless of the required-setting of the
     * underlying input widget.
     * 
     * @uxon-property required
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\Widgets\iCanBeRequired::setRequired()
     */
    public function setRequired($value)
    {
        $value = BooleanDataType::cast($value);
        $this->required = $value;
        if ($this->isInputWidgetInitialized() === true && $this->getInputWidget() instanceof iCanBeRequired) {
            $this->getInputWidget()->setRequired($value);
        }
        return $this;
    }

    /**
     * Set to TRUE to disable the filter (still visible, but inactive).
     * 
     * @uxon-property disabled
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Widgets\Container::setDisabled()
     */
    public function setDisabled(?bool $trueOrFalseOrNull, string $reason = null) : WidgetInterface
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setDisabled($trueOrFalseOrNull, $reason);
        }
        return parent::setDisabled($trueOrFalseOrNull, $reason);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setDisableCondition()
     */
    public function setDisableCondition($value)
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setDisableCondition($value);
        }
        return parent::setDisableCondition($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setDisabledIf()
     */
    public function setDisabledIf(UxonObject $value) : WidgetInterface
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setDisabledIf($value);
        }
        return parent::setDisabledIf($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::isDisabled()
     */
    public function isDisabled() : ?bool
    {
        return parent::isDisabled() || ($this->hasCustomInputWidget() && $this->getInputWidget()->isDisabled());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('comparator', $this->getComparator());
        $uxon->setProperty('required', $this->isRequired());
        $uxon->setProperty('input_widget', $this->getInputWidget()->exportUxonObject());
        if ($this->hasCustomConditionGroup() === true) {
            $uxon->setProperty('condition_group', $this->getCustomConditionGroupUxon());
        }
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueDataType()
     */
    public function getValueDataType()
    {
        return $this->getInputWidget()->getValueDataType();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getEmptyText()
     */
    public function getEmptyText()
    {
        if ($this->isInputWidgetInitialized() === true) {
            return $this->getInputWidget()->getEmptyText();
        }
        return $this->emptyText;
    }
    
    /**
     * This text will be displayed if the filter is empty.
     * 
     * @uxon-property empty_text
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::setEmptyText()
     */
    public function setEmptyText($value)
    {
        $this->emptyText = $value;
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setEmptyText($value);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::isBoundToAttribute()
     */
    public function isBoundToAttribute() : bool
    {
        if ($this->isInputWidgetInitialized() === true) {
            return $this->getInputWidget()->isBoundToAttribute();
        }
        return $this->attributeAlias !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::isBoundToLabelAttribute()
     */
    public function isBoundToLabelAttribute() : bool
    {
        if ($this->isInputWidgetInitialized() === true && $this->getInputWidget() instanceof iShowSingleAttribute) {
            return $this->getInputWidget()->isBoundToLabelAttribute();
        }
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::hasValue()
     */
    public function hasValue() : bool
    {
        return $this->getInputWidget()->hasValue();
    }
    
    /**
     * A filter is prefillable if it is not marked with `do_not_prefill`, it's
     * input widget is prefillable and that widget does not have a live reference
     * for value.
     * 
     * Regular input widgets are prefillable even with a live reference as value,
     * but this does not feel right for filters: you will use live refs in filters
     * when they explicitly depend on another control and a user will not understand
     * why that reference would be overridden by a prefill.
     * 
     * @see \exface\Core\Widgets\AbstractWidget::isPrefillable()
     */
    public function isPrefillable()
    {
        return parent::isPrefillable() 
        && $this->getInputWidget()->isPrefillable()
        && ! ($this->hasValue() && $this->getValueExpression()->isReference());
    }
    
    /**
     * Set to TRUE to not prefill the widget with any action data.
     * 
     * @uxon-property do_not_prefill
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see AbstractWidget::setDoNotPrefill()
     */
    public function setDoNotPrefill($value)
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setDoNotPrefill($value);
        }
        return parent::setDoNotPrefill($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getDoNotPrefill()
     */
    public function getDoNotPrefill()
    {
        return parent::getDoNotPrefill() || ($this->hasCustomInputWidget() === true && $this->getInputWidget()->getDoNotPrefill());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::isHidden()
     */
    public function isHidden()
    {
        return parent::isHidden() || ($this->hasCustomInputWidget() === true && $this->getInputWidget()->isHidden());
    }
    
    /**
     * 
     * @return bool
     */
    public function getApplyOnChange() : bool
    {
        return $this->apply_on_change;
    }
    
    /**
     * Set to TRUE to refresh the filterd widget automatically when the value of the filter changes.
     * 
     * FALSE by default.
     * 
     * @uxon-property apply_on_change
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param boolean $true_or_false
     * @return Filter
     */
    public function setApplyOnChange($true_or_false) : Filter
    {
        $this->apply_on_change = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isDisplayOnly()
     */
    public function isDisplayOnly() : bool
    {
        return $this->displayOnly || ($this->hasCustomInputWidget() === true && $this->getInputWidget() instanceof iTakeInput && $this->getInputWidget()->isDisplayOnly());
    }

    /**
     *
     * Set to TRUE to make the widget inactive and ignored by actions - FALSE by default.
     * 
     * The following states of input widgets are available:
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
    public function setReadonly($true_or_false) : WidgetInterface
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setReadonly($true_or_false);
        }
        $this->readonly = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isReadonly()
     */
    public function isReadonly() : bool
    {
        return $this->readonly || ($this->hasCustomInputWidget() === true && $this->getInputWidget() instanceof iTakeInput && $this->getInputWidget()->isReadonly());
    }

    /**
     * Makes the widget display-only if set to TRUE (= interactive, but being ignored by most actions) - FALSE by default.
     * 
     * The following states of input widgets are available:
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
    public function setDisplayOnly($true_or_false) : iTakeInput
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setDisplayOnly($true_or_false);
        } 
        $this->displayOnly = BooleanDataType::cast($true_or_false);
        return $this;
    }
    
    /**
     * 
     * @return boolean
     */
    public function hasCustomConditionGroup() {
        return $this->customConditionGroupUxon !== null;
    }
    
    /**
     * Returns the custom condition group if defined (a new instance of ConditionGroup every time!).
     * 
     * The $value parameter allows to replace the placeholder by the given fixed value.
     * 
     * @param mixed $value
     * @return ConditionGroupInterface
     */
    public function getCustomConditionGroup($value = '[#value#]') : ConditionGroupInterface
    {
        $uxon = $this->getCustomConditionGroupUxon();
        if ($uxon === null) {
            throw new WidgetLogicError($this, 'Cannot get condition_group from ' . $this->getWidgetType() . ': it is empty!');
        }
        if ($value !== '[#value#]') {
            $uxon = UxonObject::fromJson(str_replace('[#value#]', ($value ?? ''), $uxon->toJson()));
        }
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $uxon, $this->getMetaObject());
    }
    
    /**
     * 
     * @return UxonObject|NULL
     */
    protected function getCustomConditionGroupUxon() : ?UxonObject
    {
        return $this->customConditionGroupUxon;
    }
    
    /**
     * A custom condition group to apply instead of simply comparing attribute_alias to value.
     * 
     * The condition group can include any conditions or even nested groups. The value of the filter
     * widget can be used in the conditions by setting their `value` to the placeholder `[#value#]`.
     * Static values can be used too!
     * 
     * Filters with custom `condition_group` can be easily mixed with simple filters. In the resulting
     * condition group, the latter will yield conditions and the former will produce nested condition
     * groups.
     * 
     * ## Examples
     * 
     * ### Looking for a value in multiple attributes
     * 
     * ```
     * {
     *   "caption": "Search",
     *   "condition_group": {
     *     "operator": "OR",
     *     "conditions": [
     *       {
     *         "expression": "attr1",
     *         "comparator": "=",
     *         "value": "[#value#]"
     *       },
     *       {
     *         "expression": "attr2",
     *         "comparator": "=",
     *         "value": "[#value#]"
     *       }
     *     ]
     *   }
     * }
     * 
     * ```
     * 
     * ### Static conditions
     * 
     * This filter will search in the attribute `attr1` of items, that have `visible_flag` set to `1`.
     * 
     * ```
     * {
     *   "caption": "Search",
     *   "condition_group": {
     *     "operator": "OR",
     *     "conditions": [
     *       {
     *         "expression": "attr1",
     *         "comparator": "=",
     *         "value": "[#value#]"
     *       },
     *       {
     *         "expression": "visible_flag",
     *         "comparator": "==",
     *         "value": "1"
     *       }
     *     ]
     *   }
     * }
     * 
     * ```
     * 
     * @uxon-property condition_group
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "OR", "conditions": [{"expression": "", "value": "[#value#]", "comparator": "="}]}
     * 
     * @param UxonObject $uxon
     * @return Filter
     */
    public function setConditionGroup(UxonObject $uxon) : Filter
    {
        if ($uxon->hasProperty('conditions') === true) {
            $objAlias = $this->getMetaObject()->getAliasWithNamespace();
            $enrichedConditions = [];
            foreach ($uxon->getProperty('conditions')->toArray() as $cond) {
                $cond['object_alias'] = $cond['object_alias'] ?? $objAlias;
                $enrichedConditions[] = $cond;
            }
            $uxon->setProperty('conditions', new UxonObject($enrichedConditions));
        }
        $this->customConditionGroupUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getIncludeInQuickSearch() : bool
    {
        return $this->includeInQuickSearch;
    }
    
    /**
     * Set to TRUE to include this filter as a further OR-condition in quick-search queries for the filtered widget.
     * 
     * This only has effect if the filtered widget supports quick search!
     * 
     * @uxon-property include_in_quick_search
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool $value
     * @return Filter
     */
    public function setIncludeInQuickSearch(bool $value) : Filter
    {
        $this->includeInQuickSearch = $value;
        return $this;
    }
    
    /**
    *
    * {@inheritDoc}
    * @see \exface\Core\Widgets\AbstractWidget::doPrefill()
    */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        if (! $this->isPrefillable()) {
            return;
        }
        
        foreach ($this->getChildren() as $widget) {
            $widget->prefill($data_sheet);
        }
        
        return;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        foreach ($this->getChildren() as $widget) {
            $data_sheet = $widget->prepareDataSheetToRead($data_sheet);
        }
        
        return $data_sheet;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
        foreach ($this->getChildren() as $widget) {
            $data_sheet = $widget->prepareDataSheetToPrefill($data_sheet);
        }
        
        return $data_sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getWidth()
     */
    public function getWidth()
    {
        return $this->getInputWidget()->getWidth();
    }
    
    /**
     * Sets the width of the widget.
     * Set to `1` for default widget width in a facade or `max` for maximum width possible.
     *
     * The width can be specified either in
     * - facade-specific relative units (e.g. `width: 2` makes the widget twice as wide
     * as the default width of a widget in the current facade)
     * - percent (e.g. `width: 50%` will make the widget take up half the available space)
     * - any other facade-compatible units (e.g. `width: 200px` will work in CSS-based facades)
     *
     * @uxon-property width
     * @uxon-type string
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WidgetInterface::setWidth()
     */
    public function setWidth($value)
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setWidth($value);
        }
        $this->width = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getHeight()
     */
    public function getHeight()
    {
        return $this->getInputWidget()->getHeight();
    }
    
    /**
     * Sets the height of the widget.
     * Set to `1` for default widget height in a facade or `max` for maximum height possible.
     *
     * The height can be specified either in
     * - facade-specific relative units (e.g. `height: 2` makes the widget twice as high
     * as the default width of a widget in the current facade)
     * - percent (e.g. `height: 50%` will make the widget take up half the available space)
     * - any other facade-compatible units (e.g. `height: 200px` will work in CSS-based facades)
     *
     * @uxon-property height
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setHeight()
     */
    public function setHeight($value)
    {
        if ($this->isInputWidgetInitialized() === true) {
            $this->getInputWidget()->setHeight($value);
        }
        $this->height = $value;
        return $this;
    }
    
    /**
     * Set to `true` to preload all possible data for offline use of the input widget(s).
     * 
     * @uxon-property preload_data
     * @uxon-type boolean|\exface\Core\CommonLogic\DataSheets\DataSheet
     * 
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::setPreloadData()
     */
    public function setPreloadData($uxonOrString): iCanPreloadData
    {
        $this->preloadConfig = $uxonOrString;
        if ($this->isInputWidgetInitialized()) {
            $this->getInputWidget()->setPrefillData($uxonOrString);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::isPreloadDataEnabled()
     */
    public function isPreloadDataEnabled(): bool
    {
        $input = $this->getInputWidget();
        return $input instanceof iCanPreloadData && $input->getPreloader()->isEnabled();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::prepareDataSheetToPreload()
     */
    public function prepareDataSheetToPreload(DataSheetInterface $dataSheet): DataSheetInterface
    {
        $input = $this->getInputWidget();
        if ($input instanceof iCanPreloadData) {
            return $this->getPreloader()->prepareDataSheetToPreload($dataSheet);
        } else {
            return $dataSheet;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::getPreloader()
     */
    public function getPreloader(): DataPreloader
    {
        $input = $this->getInputWidget();
        if ($input instanceof iCanPreloadData) {
            return $input->getPreloader();
        } else {
            if ($this->preloader === null) {
                $this->preloader = new DataPreloader($this);
            }
            return $this->preloader;
        }
    }
    
    /**
     * Set to TRUE to hide the filter and use a simple InputHidden widget by default.
     * 
     * If you just need a hidden filter without any special configuration, set
     * `hidden` to `true`. This will produce a filter with a `InputHidden` for 
     * `input_widget` which is typically a lot simpler and faster, than a fully
     * instantiated widget being hidden via `visibility`. There will be no additional
     * background acitivity/formatting etc. The values will be used as-is.
     * 
     * @uxon-property hidden
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Widgets\AbstractWidget::setHidden()
     */
    public function setHidden($value)
    {
        $this->useHiddenInput = $value;
        return parent::setHidden($value);
    }
}