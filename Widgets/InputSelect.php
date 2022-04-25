<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataSorter;
use exface\Core\Factories\DataSorterFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Widgets\iHaveValues;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Factories\ConditionGroupFactory;

/**
 * A dropdown menu to select from: each dropdown item has a value and a text. 
 * 
 * Multiple selection can be enabled/disabled via `multi_select`. There are also some advanced
 * options like `multi_select_value_delimiter`, etc.
 * 
 * `InputSelect`s should be used if you do not have many options: i.e. not more than 20. With more
 * options you are better off with `InputCombo` or `InputComboTable`, which support searching and
 * lazy loading.
 * 
 * ## Selectable options
 * 
 * The selectable options can either be specified directly (via the property `selectable_options`) 
 * or generated from the data source. In the latter case, attributes for text and values can be 
 * specified via `text_attribute_alias` and `value_attribute_alias`. They do not need to have 
 * something to do with the object or attribute, that the widget represents: the options are just 
 * values to pick from. Event a totally unrelated object can be specified to fetch the options - via 
 * `options_object_alias` property. The selected value will then be saved to the attribute being
 * represented by the `InputSelect` itself.
 * 
 * The widget will also add some generic menu items automatically:
 * 
 * - an option to empty the selection if the widget is not required 
 * (the value of this option is an empty string)
 * - an option to select empty values if the widget is based on an 
 * attribute which is not required (the value is the empty-comparator `NULL`)
 * 
 * ## Prefill 
 * 
 * By turning `use_prefill_to_filter_options` on or off, the prefill 
 * behavior can be customized. By default, the values from the prefill 
 * data will be used as options in the select automatically.
 * 
 * ## Examples
 * 
 * ### Manually defined options
 * 
 * ```
 *  {
 *      "object_alias": "MY.APP.CUSTOMER",
 *      "widget_type": "InputSelect",
 *      "attribute_alias": "CLASSIFICATION",
 *      "selectable_options": [
 *          "A": "A-Customer",
 *          "B": "B-Customer",
 *          "C": "C-Customer"
 *      ]
 *  }
 *  
 * ```
 * 
 * ### Attributes of another object as options
 * 
 * ```
 *  {
 *      "object_alias": "MY.APP.CUSTOMER",
 *      "widget_type": "InputSelect",
 *      "attribute_alias": "CLASSIFICATION",
 *      "options_object_alias": "MY.APP.CUSTOMER_CLASSIFICATION",
 *      "value_attribute_alias": "ID",
 *      "text_attribute_alias": "CLASSIFICATION_NAME"
 *  }
 *  
 * ```
 *
 * @author Andrej Kabachnik
 */
class InputSelect extends Input implements iSupportMultiSelect
{

    private $value_text = '';

    private $multi_select = false;

    private $multi_select_value_delimiter = null;

    private $multi_select_text_delimiter = null;

    private $selectable_options = array();
    
    private $selectable_null = null;

    private $text_attribute_alias = null;

    private $value_attribute_alias = null;

    private $options_object = null;

    private $options_object_alias = null;

    private $options_data_sheet = null;

    private $use_prefill_to_filter_options = true;

    private $use_prefill_values_as_options = false;
    
    private $filtersUxon = null;
    
    private $filters = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        // Make sure to set muti-select option first as many other things like treating
        // values depend on it.
        if ($uxon->hasProperty('multi_select')) {
            $this->setMultiSelect($uxon->getProperty('multi_select'));
        }
        return parent::importUxonObject($uxon);
    }

    /**
     *
     * @return string
     */
    public function getValueText()
    {
        return $this->value_text;
    }

    /**
     * Sets the text to be displayed for the current value (only makes sense if the `value` is set too!)
     *
     * @uxon-property value_text
     * @uxon-type string
     *
     * @param string $value            
     */
    public function setValueText($value)
    {
        $this->value_text = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::getMultiSelect()
     */
    public function getMultiSelect()
    {
        return $this->getMultipleValuesAllowed();
    }

    /**
     * Set to TRUE/FALSE to force the option to select multiple items on or off.
     *
     * @uxon-property multi_select
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::setMultiSelect()
     */
    public function setMultiSelect($value)
    {
        return $this->setMultipleValuesAllowed(\exface\Core\DataTypes\BooleanDataType::cast($value));
    }
    
    /**
     * Same as setMultiSelect()
     * 
     * Overriding this method here hides the UXON property `multiple_values_delimiter` in
     * `InputSelect` widgets, only showing `multi_select_value_delimiter`.
     * 
     * @see \exface\Core\Widgets\Input::setMultipleValuesAllowed()
     */
    public function setMultipleValuesAllowed(bool $value) : Input
    {
        return parent::setMultipleValuesAllowed($value);
    }

    /**
     * Returns an array of value/label-pairs
     *
     * @return array
     */
    public function getSelectableOptions()
    {
        // If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
        if (empty($this->selectable_options) && $this->isBoundToAttribute()) {
            $data_type = $this->getAttribute()->getDataType();
            if ($data_type instanceof BooleanDataType) {
                $this->setSelectableOptions(array(
                    1,
                    0
                ), array(
                    $this->translate('WIDGET.SELECT_YES'),
                    $this->translate('WIDGET.SELECT_NO')
                ));
            } elseif ($data_type instanceof EnumDataTypeInterface) {
                $this->setSelectableOptions($data_type->getLabels());
            }
        }
        
        // If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
        if (empty($this->selectable_options) && ! $this->getOptionsDataSheet()->isBlank()) {
            $this->setOptionsFromDataSheet($this->getOptionsDataSheet());
        }
        
        $options = $this->getSelectableGenericOptions() + $this->selectable_options;
        
        return $options;
    }
    
    /**
     * Returns generic options like "none" or "empty"
     * 
     * @return string[]
     */
    protected function getSelectableGenericOptions()
    {
        $generic_options =  [];
        // Unselect option if the input is not required and not disabled with a fixed value
        if (! $this->isRequired() && ! ($this->isDisabled() && $this->getValue()) && ! array_key_exists('', $this->selectable_options)) {
            $generic_options[''] = $this->translate('WIDGET.SELECT_NONE');
        }
        // Select empty option if based on an attribute that is not required
        if ($this->isSelectableNull()){
            $generic_options[EXF_LOGICAL_NULL] = $this->translate('WIDGET.SELECT_EMPTY');
        }
        return $generic_options;
    }
    
    /**
     * Returns TRUE if there are selectable options currently defined (not generic ones!)
     * 
     * @return boolean
     */
    public function hasOptions()
    {
        if (! empty($this->selectable_options)){
            return true;
        }
        return false;
    }

    /**
     * Sets the options explicitly: `{"value1": "text1", "value2": "text2"}`.
     *
     * @uxon-property selectable_options
     * @uxon-type object
     * @uxon-template {"": ""}
     *
     * When adding options programmatically an assotiative array can be used or separate arrays 
     * with equal length: one for values and one for the text labels.
     *
     * @param string[]|UxonObject $array_or_object            
     * @param array $options_texts_array            
     * @throws WidgetPropertyInvalidValueError
     * @return InputSelect
     */
    public function setSelectableOptions($array_or_object, array $options_texts_array = NULL)
    {
        $options = array();
        
        // Transform the options into an array
        if ($array_or_object instanceof UxonObject) {
            $array = $array_or_object->toArray();
        } elseif (is_array($array_or_object)) {
            $array = $array_or_object;
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Wrong data type for possible values of ' . $this->getWidgetType() . '! Expecting array or object, ' . gettype($array_or_object) . ' given.', '6T91S9G');
        }
        
        // If it is not an assosiative array, attemt to transform it to one
        if (array_values($array) === $array) {
            if (is_array($options_texts_array)) {
                if (count($array) !== count($options_texts_array)) {
                    throw new WidgetPropertyInvalidValueError($this, 'Number of possible values (' . count($array) . ') differs from the number of keys (' . count($options_texts_array) . ') for widget "' . $this->getId() . '"!', '6T91S9G');
                } else {
                    foreach ($array as $nr => $id) {
                        $options[$id] = $options_texts_array[$nr];
                    }
                }
            } else {
                $options = array_combine($array, $array);
            } 
        } else {
            $options = $array;
        }
        
        //Translate options
        foreach ($options as $key => $value) {
            $options[$key] = $this->evaluatePropertyExpression($value);
        }
        
        $this->selectable_options = $options;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isSelectableNull() : bool
    {
        return $this->selectable_null ?? (($this->getParent() instanceof Filter) && $this->isBoundToAttribute() && ! $this->getAttribute()->isRequired() && ! $this->isRequired());
    }
    
    /**
     * Set to TRUE/FALSE to include/exclude the empty value (null) from `selectable_options`.
     * 
     * By default the empty value (null) will be included automatically in filters
     * over non-required attributes.
     * 
     * @uxon-property selectable_null
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return InputSelect
     */
    public function setSelectableNull(bool $value) : InputSelect
    {
        $this->selectable_null = $value;
        return $this;
    }
    
    /**
     * Returns the current number of selectable options
     *
     * @return integer
     */
    public function countSelectableOptions($include_generic_options = false)
    {
        return count($this->getSelectableOptions()) - ($include_generic_options ? 0 : count($this->getSelectableGenericOptions()));
    }
    
    /**
     * Prefills the widget with a data sheet based on the same object as the 
     * widget itself or a derivative of it.
     * 
     * @param DataSheetInterface $data_sheet
     * @return void
     */
    protected function doPrefillWithWidgetObject(DataSheetInterface $data_sheet)
    {
        if ($this->isBoundToAttribute() === false || ! $data_sheet->getMetaObject()->is($this->getMetaObject())){
            return;
        }
        
        if ($col = $data_sheet->getColumns()->getByAttribute($this->getAttribute())){
            $this->setSelectableOptions($col->getValues(false));
            $this->setValuesFromArray($col->getValues(false));
        }
        return;
    }
    
    /**
     * Prefills the widget with a data sheet based on the same object as the 
     * options object or a derivative of it.
     * 
     * @param DataSheetInterface $data_sheet
     * @return void
     */
    protected function doPrefillWithOptionsObject(DataSheetInterface $data_sheet)
    {
        if (! $data_sheet->getMetaObject()->is($this->getOptionsObject())){
            return;
        }
        
        // If the sheet is based upon the object, that is being selected by this Combo, we can use the prefill sheet
        // values directly
        $values_column = $data_sheet->getColumns()->getByAttribute($this->getValueAttribute());
        $texts_column = $data_sheet->getColumns()->getByAttribute($this->getTextAttribute());
        
        if ($values_column){
            $this->setOptionsFromPrefillColumns($values_column, $texts_column ? $texts_column : null);
        }
        
        // Now see if the prefill object can be used to filter values
        if (! $this->getUsePrefillValuesAsOptions() && $this->getUsePrefillToFilterOptions()) {
            if ($data_sheet->getMetaObject()->is($this->getOptionsObject())) {
                // TODO which values from the prefill are we going to use here for fitlers? Which columns?
                // Or maybe use the filter of the prefill sheet? Or even ignore this case completely?
            }
        }
        
        return;
    }
    
    /**
     * Prefills the widget if it represents a relation by searching the prefill
     * data for columns with relations to the same object as the widget's
     * relation points to.
     * 
     * E.g. if we have a select for people responsible for a task and a prefill
     * is perfomed with data containing the UID of a person in any form (e.g.
     * a store with it's manager), we can use this UID to prefill our select
     * even though the data is not directly related.
     * 
     * @param DataSheetInterface $data_sheet
     * @return void
     */
    protected function doPrefillWithRelationsInData(DataSheetInterface $data_sheet)
    {
        if (! $this->getAttribute() || ! $this->getAttribute()->isRelation()){
            return;
        }
        // If it is not the object selected within the combo, than we still can look for columns in the sheet, that
        // contain selectors (UIDs) of that object. This means, we need to look for data columns showing relations
        // and see if their related object is the same as the related object of the relation represented by the combo.
        foreach ($data_sheet->getColumns()->getAll() as $column) {
            if ($column->getAttribute() && $column->getAttribute()->isRelation()) {
                if ($column->getAttribute()->getRelation()->getRightObject()->is($this->getAttribute()->getRelation()->getRightObject())) {
                    // TODO what about texts?
                    $pointer = DataPointerFactory::createFromColumn($column);
                    $this->setOptionsFromPrefillColumns($column);
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'selectable_options', $pointer));
                    return;
                }
            }
        }
        return;
    }
    
    /**
     * Prefills the widet with data of another object by using the given relation
     * from the options object to the prefill object.
     * 
     * @param DataSheetInterface $data_sheet
     * @param MetaRelationInterface $relation_from_options_to_prefill_object
     * @return void
     */
    protected function doPrefillWithRelatedObject(DataSheetInterface $data_sheet, MetaRelationInterface $relation_from_options_to_prefill_object)
    { 
        // Now see if the prefill object can be used to filter values
        if (! $this->getUsePrefillValuesAsOptions() && $this->getUsePrefillToFilterOptions()) {
            // Use this relation as filter to query the data source for selectable options
            if ($col = $data_sheet->getColumns()->getByAttribute($relation_from_options_to_prefill_object->getRightKeyAttribute(true))) {
                $pointer = DataPointerFactory::createFromColumn($col);
                $this->getOptionsDataSheet()->getFilters()->addConditionFromValueArray($relation_from_options_to_prefill_object->getAlias(), $col->getValues(false));
                // TODO there is actually no filters property for InputSelect. Perhaps it is a good idea to
                // transfer it to InputSelect from InputCombo - since we can even prefill it here?
                $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'filters', $pointer));
            }
        }
        
        return;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        
        // If it is a single-select but the prefill has multiple values (either explicitly or as a delimited list),
        // do not use the prefill data - we don't know which value to use!
        if ($this->getMultiSelect() === false && $this->isBoundToAttribute()) {
            $prefill_columns = $this->prepareDataSheetToPrefill(DataSheetFactory::createFromObject($data_sheet->getMetaObject()))->getColumns();
            if (! $prefill_columns->isEmpty() && $col = $data_sheet->getColumns()->getByExpression($prefill_columns->getFirst()->getExpressionObj())) {
                if (count($col->getValues(false)) > 1 || count(explode($this->getMultipleValuesDelimiter(), ($col->getValue(0) ?? ''))) > 1) {
                    return;
                }
            }
        }
        
        // First do the regular prefill for an input (setting the value)
        parent::doPrefill($data_sheet);
        
        // Additionally the InputSelect can use the prefill data to generate selectable options.
        // If the InputSelect is based on a meta attribute and does not have explicitly defined options, we can try to use
        // the prefill values to get the options.
        if ($this->getAttribute() && ! $this->countSelectableOptions()) {
            // If the prefill is based on the same object, just look for values of this attribute, add them as selectable options
            // and select all of them
            if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
                $this->doPrefillWithWidgetObject($data_sheet);
            } else {
                // If the prefill data was loaded for another object, there are still multiple possibilities to prefill
                if ($data_sheet->getMetaObject()->is($this->getOptionsObject())) {
                    $this->doPrefillWithOptionsObject($data_sheet);
                    return;
                } elseif ($this->getAttribute()->isRelation()) {
                    $this->doPrefillWithRelationsInData($data_sheet);
                    return;
                } elseif ($rel = $this->getOptionsObject()->findRelation($data_sheet->getMetaObject(), true)){
                    $this->doPrefillWithRelatedObject($data_sheet, $rel);
                }
            }
        }
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
        if ($data_sheet->getMetaObject()->is($this->getOptionsObject()) && $textAttr = $this->getTextAttribute()) {
            $data_sheet->getColumns()->addFromAttribute($textAttr);
        }
        
        return $data_sheet;
    }
    
    /**
     * 
     * @param DataColumnInterface $value_column
     * @param DataColumnInterface $text_column
     * @return \exface\Core\Widgets\InputSelect
     */
    protected function setOptionsFromPrefillColumns(DataColumnInterface $value_column, DataColumnInterface $text_column = null)
    {
        $values = $value_column->getValues(false);
        
        if ($text_column) {
            $texts = $text_column->getValues(false);
        }
        
        $this->setSelectableOptions($values, $texts);
        // FIXME #OnPrefillChangeProperty fire event with a range pointer
        $this->setValuesFromArray($values);
        $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'values', DataPointerFactory::createFromColumn($value_column)));
        if ($text_column) {
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value_texts', DataPointerFactory::createFromColumn($text_column)));
        }
        return $this;
    }

    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @return \exface\Core\Widgets\InputSelect
     */
    protected function setOptionsFromDataSheet(DataSheetInterface $data_sheet, bool $readIfNotFresh = true)
    {
        $data_sheet->getColumns()->addFromAttribute($this->getValueAttribute());
        $data_sheet->getColumns()->addFromAttribute($this->getTextAttribute());
        if ($readIfNotFresh !== false && ! $data_sheet->isFresh()) {
            $data_sheet->dataRead();
        }
        $this->setSelectableOptions($data_sheet->getColumns()->getByAttribute($this->getValueAttribute())->getValues(false), $data_sheet->getColumns()->getByAttribute($this->getTextAttribute())->getValues(false));
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::getValues()
     */
    public function getValues() : array
    {
        $array = [];
        if ($val = $this->getValue()) {
            // Split the value by value delimiter, but only if the raw value does not match
            // one of the selectable options exactly!
            if (! array_key_exists($val, $this->getSelectableOptions())) {
                $array = array_map('trim', explode($this->getMultiSelectValueDelimiter(), $this->getValue()));
            } else {
                $array = [$this->getValue()];
            }
        } 
        return $array;
    }
    
    /**
     * 
     * @see getValueWithDefaults()
     * @return array
     */
    public function getValuesWithDefaults() : array
    {
        $array = $this->getValues();
        if (empty($array) && $def = $this->getDefaultValue()) {
            if (is_array($def)) {
                $array = $def;
            } else {
                $sep = $this->getMultipleValuesDelimiter();
                $array = explode($sep, $def);
            }
        }
        return $array;
    }

    /**
     * Initial values for multi-select widgets via array or delimited list.
     * 
     * This only works if `multi_select` = `true`! In this case, the `value` 
     * property can be used to set a single value or a string list delimited by
     * `multi_select_value_delimiter` (comma by default), while the `values` 
     * property accepts an array of expressions, which is more convenient in
     * many cases.
     *
     * @uxon-property values
     * @uxon-type array
     * @uxon-template [""]
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValues()
     */
    public function setValues($expressionOrArrayOrDelimitedString, bool $parseStringAsExpression = true) : iHaveValues
    {
        if ($expressionOrArrayOrDelimitedString instanceof UxonObject) {
            $expressionOrArrayOrDelimitedString = $expressionOrArrayOrDelimitedString->toArray();
        }
        
        if (is_array($expressionOrArrayOrDelimitedString) === true) {
            $this->setValuesFromArray($expressionOrArrayOrDelimitedString, $parseStringAsExpression);
        } else {
            $this->setValue($expressionOrArrayOrDelimitedString, $parseStringAsExpression);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValuesFromArray()
     */
    public function setValuesFromArray(array $values, bool $parseStringsAsExpressions = true) : iHaveValues
    {
        if (empty($values)) {
            return $this;
        }
        
        if ($this->getMultiSelect()) {
            $this->setValue(implode($this->getMultiSelectValueDelimiter(), $values), $parseStringsAsExpressions);
        } else {
            $this->setValue(reset($values), $parseStringsAsExpressions);
        }
        return $this;
    }

    /**
     *
     * @return MetaAttributeInterface|NULL
     */
    public function getTextAttribute() : ?MetaAttributeInterface
    {
        if (! $this->isBoundToAttribute()) {
            return null;
        }
        return $this->getOptionsObject()->getAttribute($this->getTextAttributeAlias());
    }

    /**
     * Defines the alias of the attribute of the options object to be displayed for every value.
     * 
     * **NOTE:** This alias is resolved relative to the `options_object`!
     * 
     * If not set, the system will try to determine one automatically.
     *
     * If the text_attribute_alias was not set explicitly (e.g. via UXON), it will be determined as follows:
     * 
     * - If an option object was specified explicitly, it's label will be used (or it's UID if no label is defined)
     * - If the widget represents a relation, the related object's label will be used
     * - If the widget represents the UID of it's object, than the label of this object will be used
     * - If the widget represents any other attribute and there is no explicit options_object, this attribute
     * will be used for values as well as for the displayed text.
     *
     * @uxon-property text_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setTextAttributeAlias($value)
    {
        $this->text_attribute_alias = $value;
        return $this;
    }

    /**
     * Returns the alias of the options object's attribute to be displayed, when a value is selected.
     *
     * @see setTextAttributeAlias()
     *
     * @return string
     */
    public function getTextAttributeAlias()
    {
        if (is_null($this->text_attribute_alias)) {
            // If options are taken from the same object, than they are probably values of the referenced attribute,
            // unless it is a self-reference-relation (which should be treated just like a relation to other objects)
            // or the UID (which should get the label as text).
            if ($this->getOptionsObject()->isExactly($this->getMetaObject()) && ! ($this->getAttribute() && ($this->getAttribute()->isRelation() || $this->getAttribute()->isUidForObject()))) {
                $this->text_attribute_alias = $this->getAttributeAlias();
            } else {
                if ($this->getOptionsObject()->hasLabelAttribute()) {
                    $this->text_attribute_alias = $this->getOptionsObject()->getLabelAttribute()->getAlias();
                } else {
                    $this->text_attribute_alias = $this->getOptionsObject()->getUidAttributeAlias();
                }
            }
        }
        return $this->text_attribute_alias;
    }

    /**
     * Returns TRUE if the options object was specified explicitly (e.g.
     * via UXON-property "options_object_alias") and FALSE otherwise.
     *
     * @return boolean
     */
    public function isOptionsObjectSpecified()
    {
        if (is_null($this->options_object) && is_null($this->options_object_alias)){
            return false;
        }
        return true;
    }

    public function getOptionsObject()
    {
        if (is_null($this->options_object)) {
            if (! $this->getMetaObject()->isExactly($this->getOptionsObjectAlias())) {
                $this->options_object = $this->getWorkbench()->model()->getObject($this->getOptionsObjectAlias());
            } else {
                $this->options_object = $this->getMetaObject();
            }
        }
        return $this->options_object;
    }

    public function setOptionsObject(MetaObjectInterface $value)
    {
        $this->options_object = $value;
        return $this;
    }

    public function getOptionsObjectAlias()
    {
        if (is_null($this->options_object_alias)) {
            $this->options_object_alias = $this->getMetaObject()->getAliasWithNamespace();
        }
        return $this->options_object_alias;
    }

    /**
     * The meta object, which value_attribute_alias and text_attribute_alias belong to.
     * 
     * By default, it is the object of the widget itself. A different object can be used
     * though, to make the widget get it's options from anywhere in the model. This is
     * usefull if there is no explicit relation between the widget's object an the value
     * data.
     *
     * @uxon-property options_object_alias
     * @uxon-type metamodel:object
     *
     * @param string $value            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setOptionsObjectAlias($value)
    {
        $this->options_object_alias = $value;
        return $this;
    }

    public function getValueAttributeAlias()
    {
        // If the not set explicitly, try to determine the value attribute automatically
        if (is_null($this->value_attribute_alias)) {
            // If options are taken from the same object, than they are probably values of the referenced attribute,
            // unless it is a self-reference-relation, which should be treated just like a relation to other objects
            if ($this->getOptionsObject()->isExactly($this->getMetaObject()) && ! ($this->getAttribute() && $this->getAttribute()->isRelation())) {
                $this->value_attribute_alias = $this->getAttributeAlias();
            } elseif ($this->getOptionsObject()->getUidAttributeAlias()) {
                $this->value_attribute_alias = $this->getOptionsObject()->getUidAttributeAlias();
            } else {
                throw new WidgetConfigurationError($this, 'Cannot create ' . $this->getWidgetType() . ': there is no value attribute defined and the options object "' . $this->getOptionsObject()->getAliasWithNamespace() . '" has no UID attribute!', '6V5FGYF');
            }
        }
        return $this->value_attribute_alias;
    }

    /**
     * 
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getValueAttribute()
    {
        return $this->getOptionsObject()->getAttribute($this->getValueAttributeAlias());
    }

    /**
     * The alias of the attribute of the options object to be used as the internal value of the select.
     * 
     * **NOTE:** This alias is resolved relative to the `options_object_alias`!
     * 
     * If not set, the UID attribute will be used.
     *
     * @uxon-property value_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value            
     * @return InputSelect
     */
    public function setValueAttributeAlias($value)
    {
        $this->value_attribute_alias = $value;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getUsePrefillToFilterOptions() : bool
    {
        return $this->use_prefill_to_filter_options;
    }

    /**
     * By default, the widget will try to only show options applicable to the prefill data - set to FALSE to always show all options.
     *
     * @uxon-property use_prefill_to_filter_options
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setUsePrefillToFilterOptions($value)
    {
        $this->use_prefill_to_filter_options = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getUsePrefillValuesAsOptions() : bool
    {
        return $this->use_prefill_values_as_options;
    }

    /**
     * Makes the select only contain values from the prefill (if there are any) and no other options.
     *
     * @uxon-property use_prefill_values_as_options
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setUsePrefillValuesAsOptions($value)
    {
        $this->use_prefill_values_as_options = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * @throws WidgetConfigurationError
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function getOptionsDataSheet() : DataSheetInterface
    {
        if (is_null($this->options_data_sheet)) {
            $sheet = DataSheetFactory::createFromObject($this->getOptionsObject());
            if ($vAttr = $this->getValueAttribute()) {
                $sheet->getColumns()->addFromAttribute($vAttr);
            }
            if (($tAttr = $this->getTextAttribute()) && $tAttr !== $vAttr) {
                $sheet->getColumns()->addFromAttribute($tAttr);
            }
            if (null !== ($filters = $this->getFilters())) {
                $condGroup = ConditionGroupFactory::createForDataSheet($sheet, $filters->getOperator());
                foreach ($filters->getConditions() as $cond) {
                    /* @var $cond \exface\Core\Widgets\Parts\ConditionalPropertyCondition */
                    if ($cond->hasLiveReference()) {
                        continue;
                    }
                    if ($cond->getValueLeftExpression()->isMetaAttribute()) {
                        $condGroup->addConditionFromExpression($cond->getValueLeftExpression(), $cond->getValueRightExpression()->__toString(), $cond->getComparator());
                    } else {
                        throw new WidgetConfigurationError($this, 'Invalid configuration of filter in ' . $this->getWidgetType() . ': the left side must be an attribute alias!');
                    }
                }
                if ($condGroup->isEmpty() === false) {
                    $sheet->getFilters()->addNestedGroup($condGroup);
                }
            }
            $this->options_data_sheet = $sheet;
        }
        return $this->options_data_sheet;
    }

    /**
     * A custom data sheet to fetch get selectable options
     * 
     * **WARNING:** This is an advanced feature, which is not easy to use. Concider the simpler 
     * `filters` and `sorters` properties first!
     * 
     * @uxon-property options_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": ""}
     * 
     * @param DataSheetInterface $data_sheet
     * @throws WidgetPropertyInvalidValueError
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setOptionsDataSheet(DataSheetInterface $data_sheet)
    {
        if (! $this->getOptionsObject()->isExactly($data_sheet->getMetaObject())) {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot set options data sheet for ' . $this->getWidgetType() . ': meta object "' . $this->getOptionsObject()->getAliasWithNamespace() . '", but "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '" given instead!');
        }
        $this->options_data_sheet = $data_sheet;
        return $this;
    }

    /**
     * Condition group to filter the selectable options.
     * 
     * Each condition can either be a static filter or contain live references to current values
     * of other widgets.
     *
     * For example, if we have a select for some data loaded from the data source and each data item
     * has a `VALUE`, `NAME` and `ACTIVE` attributes, we can filter values to show only active items 
     * as follows:
     * 
     * ```
     *  {
     *      "options_object_alias": "my.app.myobject",
     *      "value_attribute_alias": "VALUE",
     *      "text_attribute_alias": "NAME",
     *      "filters": {
     *          "operator": "AND",
     *          "conditions": [
     *              {"value_left": "ACTIVE", "comparator": "==", "value_right": "1"}
     *          ]
     *      }
     *  }
     * 
     * ```
     * 
     * Instad of using the static value `1` we could also use a live reference like `=other_widget_id`.
     * 
     * @uxon-property filters
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "==", "value_right": ""}]}
     *
     * @param UxonObject $uxon            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setFilters(UxonObject $uxon)
    {
        $this->filters = null;
        $this->filtersUxon = $uxon;
        return $this;
    }
    
    /**
     *
     * @return ConditionalProperty|NULL
     */
    public function getFilters() : ?ConditionalProperty
    {
        if ($this->filters === null && $this->filtersUxon !== null) {
            $this->filters = new ConditionalProperty($this, 'filters', $this->filtersUxon, $this->getOptionsObject());
        }
        return $this->filters;
    }

    /**
     * Sets an optional array of sorter-objects to be used when fetching selectable options from a data source.
     *
     * For example, if we have a select for sizes of a product and we only wish to show sort the ascendingly,
     * we would need the following config:
     * 
     * ```
     *  {
     *      "options_object_alias": "my.app.product",
     *      "value_attribute_alias": "SIZE_ID",
     *      "text_attribute_alias": "SIZE_TEXT",
     *      "sorters": [
     *          {"attribute_alias": "SIZE_TEXT", "direction": "ASC"}
     *      ]
     *  }
     *  
     * ```
     *
     * @uxon-property sorters
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSorter[]
     * @uxon-template [{"attribute_alias": "", "direction": "asc"}]
     *
     * @param DataSorter[]|UxonObject $data_sorters_or_uxon_object            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setSorters(UxonObject $data_sorters_or_uxon_object)
    {
        foreach ($data_sorters_or_uxon_object as $sorter_or_uxon) {
            if ($sorter_or_uxon instanceof DataSorter) {
                $this->getOptionsDataSheet()->getSorters()->add($sorter_or_uxon);
            } elseif ($sorter_or_uxon instanceof UxonObject) {
                $uxon = $sorter_or_uxon;
                $this->getOptionsDataSheet()->getSorters()->add(DataSorterFactory::createFromUxon($this->getOptionsDataSheet(), $uxon));
            } else {
                throw new WidgetPropertyInvalidValueError('Cannot set sorters for ' . $this->getWidgetType() . ': invalid format ' . gettype($sorter_or_uxon) . ' given instead of and instantiated DataSorter or its UXON description.');
            }
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getMultiSelectValueDelimiter()
    {
        return $this->getMultipleValuesDelimiter();
    }

    /**
     * Sets the delimiter to be used for values.
     * 
     * Default: value list delimiter from the value attribute or "," if no value attribute defined.
     *
     * Be careful overriding this setting, as the data source must understand, what to do with the custom delimiter.
     *
     * @uxon-property multi_select_value_delimiter
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setMultiSelectValueDelimiter($value)
    {
        return $this->setMultipleValuesDelimiter($value);
    }
    
    /**
     * Same as setMultiSelectValueDelimiter()
     * 
     * Overriding this method here hides the UXON property `multiple_values_delimiter` in
     * `InputSelect` widgets, only showing `multi_select_value_delimiter`.
     * 
     * @see \exface\Core\Widgets\Input::setMultipleValuesDelimiter()
     */
    public function setMultipleValuesDelimiter(string $value) : Input
    {
        return parent::setMultipleValuesDelimiter($value);
    }
    
    /**
     * 
     * @return string
     */
    public function getMultiSelectTextDelimiter()
    {
        if (is_null($this->multi_select_text_delimiter)){
            if ($this->getTextAttribute()){
                $this->multi_select_text_delimiter = $this->getTextAttribute()->getValueListDelimiter();
            } else {
                $this->multi_select_text_delimiter = EXF_LIST_SEPARATOR;
            }
        }
        return $this->multi_select_text_delimiter;
    }

    /**
     * Sets the delimiter to be used for the displayed text.
     * 
     * Default: value list delimiter from the text attribute or "," if no text attribute defined.
     *
     * This setting will only affect the displayed text, not the value passed to the server.
     *
     * @uxon-property multi_select_text_delimiter
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setMultiSelectTextDelimiter($value)
    {
        $this->multi_select_text_delimiter = $value;
        return $this;
    }

    /**
     * Returns TRUE if the given value is among the selectable options of this select widget and FALSE otherwise.
     *
     * @param string $value            
     * @return boolean
     */
    public function hasOption($value)
    {
        foreach (array_keys($this->getSelectableOptions()) as $v) {
            if (strcasecmp($v, $value) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sets the currently selected internal value (and the corresponding value text, if available).
     * 
     * If multi-select is enabled, a delimited list can be passed as value (using the delimiter,
     * specified in `multi_select_value_delimiter`). If multi-select is off, but a delimited value
     * list is passed, the first value in the list will be used.
     *
     * @uxon-property value
     * @uxon-type metamodel:expression
     *
     * @see \exface\Core\Widgets\AbstractWidget::setValue()
     */
    public function setValue($value, bool $parseStringAsExpression = true)
    {
        if (strpos($value, $this->getMultiSelectValueDelimiter()) > 0 && ! $this->hasOption($value)) {
            if (! $this->getMultiSelect()) {
                return parent::setValue(explode($this->getMultiSelectValueDelimiter(), $value)[0], $parseStringAsExpression);
            }
        }
        return parent::setValue($value, $parseStringAsExpression);
    }
}