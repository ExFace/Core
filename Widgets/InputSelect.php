<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Factories\ConditionFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataSorter;
use exface\Core\Factories\DataSorterFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\Model\Relation;

/**
 * A dropdown menu to select from.
 * Each menu item has a value and a text. Optional support for selecting multiple items.
 *
 * The selectable options can either be specified directly (via the property "selectable_options") or generated from
 * the data source. In the latter case, attributes for text and values can be specified via text_attribute_alias and
 * value_attribute_alias. They do not need to have something to do with the object or attribute, that the widget
 * represents: the options are just values to pick from. Event a totally unrelated object can be specified to fetch
 * the options - via options_object_alias property. The selected value will then be saved to the attribute being
 * represented by the InputSelect itself.
 *
 * Example 1 (manually defined options):
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
 * Example 2 (attributes of another object as options):
 *  {
 *      "object_alias": "MY.APP.CUSTOMER",
 *      "widget_type": "InputSelect",
 *      "attribute_alias": "CLASSIFICATION",
 *      "options_object_alias": "MY.APP.CUSTOMER_CLASSIFICATION",
 *      "value_attribute_alias": "ID",
 *      "text_attribute_alias": "CLASSIFICATION_NAME"
 *  }
 *
 * By turning "use_prefill_to_filter_options" on or off, the prefill behavior can be customized. By default, the values
 * from the prefill data will be used as options in the select automatically.
 *
 * InputSelects should be used for small data sets, as not all frameworks will support searching for values or
 * lazy loading. If you have a large amount of data, use an InputCombo instead!
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

    private $text_attribute_alias = null;

    private $value_attribute_alias = null;

    private $options_object = null;

    private $options_object_alias = null;

    private $options_data_sheet = null;

    private $use_prefill_to_filter_options = true;

    private $use_prefill_values_as_options = false;

    /**
     *
     * @return string
     */
    public function getValueText()
    {
        return $this->value_text;
    }

    /**
     * Sets the text to be displayed for the current value (only makes sense if the "value" is set too!)
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
        return $this->multi_select;
    }

    /**
     * Set to TRUE to allow multiple items to be selected.
     * FALSE by default.
     *
     * @uxon-property multi_select
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::setMultiSelect()
     */
    public function setMultiSelect($value)
    {
        $this->multi_select = \exface\Core\DataTypes\BooleanDataType::parse($value);
    }

    /**
     * Returns an array of value/label-pairs
     *
     * @return array
     */
    public function getSelectableOptions()
    {
        // If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
        if (empty($this->selectable_options) && $this->getAttribute()) {
            if ($this->getAttribute()->getDataType()->is(EXF_DATA_TYPE_BOOLEAN)) {
                $this->setSelectableOptions(array(
                    1,
                    0
                ), array(
                    $this->translate('WIDGET.SELECT_YES'),
                    $this->translate('WIDGET.SELECT_NO')
                ));
            }
        }
        
        // If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
        if (empty($this->selectable_options) && ! $this->getOptionsDataSheet()->isBlank()) {
            $this->setOptionsFromDataSheet($this->getOptionsDataSheet());
        }
        
        // Add unselected uption
        if (! $this->isRequired() && ! array_key_exists('', $this->selectable_options) && ! ($this->isDisabled() && $this->getValue())) {
            $options = array(
                '' => $this->translate('WIDGET.SELECT_NONE')
            ) + $this->selectable_options;
        } else {
            $options = $this->selectable_options;
        }
        return $options;
    }

    /**
     * Sets the possible options of the select widget via assotiative array or object: {"value1": "text1", "value2": "text2"].
     *
     * @uxon-property selectable_options
     * @uxon-type Object
     *
     * When adding options programmatically, separate arrays with equal length can be used: one for values and one for the text labels.
     *
     * @param array|stdClass $array_or_object            
     * @param array $options_texts_array            
     * @throws WidgetPropertyInvalidValueError
     * @return InputSelect
     */
    public function setSelectableOptions($array_or_object, array $options_texts_array = NULL)
    {
        $options = array();
        
        // Add the specified options
        if ($array_or_object instanceof \stdClass) {
            $options = (array) $array_or_object;
        } elseif (is_array($array_or_object)) {
            if (is_array($options_texts_array)) {
                if (count($array_or_object) != count($options_texts_array)) {
                    throw new WidgetPropertyInvalidValueError($this, 'Number of possible values (' . count($array_or_object) . ') differs from the number of keys (' . count($options_texts_array) . ') for widget "' . $this->getId() . '"!', '6T91S9G');
                } else {
                    foreach ($array_or_object as $nr => $id) {
                        $options[$id] = $options_texts_array[$nr];
                    }
                }
            } else {
                // See if it is an assotiative array
                if (array_keys($array_or_object)[0] === 0 && array_keys($array_or_object)[count($array_or_object) - 1] == (count($array_or_object) - 1)) {
                    $options = array_combine($array_or_object, $array_or_object);
                } else {
                    $options = $array_or_object;
                }
            }
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Wrong data type for possible values of ' . $this->getWidgetType() . '! Expecting array or object, ' . gettype($array_or_object) . ' given.', '6T91S9G');
        }
        $this->selectable_options = $options;
        return $this;
    }

    /**
     * Returns the current number of selectable options
     *
     * @return integer
     */
    public function countSelectableOptions()
    {
        $number = count($this->getSelectableOptions());
        if (array_key_exists('', $this->getSelectableOptions())) {
            $number --;
        }
        return $number;
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
        if (! $this->getAttribute() || ! $data_sheet->getMetaObject()->is($this->getMetaObject())){
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
                if ($column->getAttribute()->getRelation()->getRelatedObject()->is($this->getAttribute()->getRelation()->getRelatedObject())) {
                    // TODO what about texts?
                    $this->setOptionsFromPrefillColumns($column);
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
     * @param Relation $relation_from_options_to_prefill_object
     * @return void
     */
    protected function doPrefillWithRelatedObject(DataSheetInterface $data_sheet, Relation $relation_from_options_to_prefill_object)
    {
        
        // Now see if the prefill object can be used to filter values
        if (! $this->getUsePrefillValuesAsOptions() && $this->getUsePrefillToFilterOptions()) {
            // Use this relation as filter to query the data source for selectable options
            if ($col = $data_sheet->getColumns()->getByExpression($relation_from_options_to_prefill_object->getRelatedObjectKeyAlias())) {
                $this->getOptionsDataSheet()->addFilterInFromString($relation_from_options_to_prefill_object->getAlias(), $col->getValues(false));
            }
        }
    }

    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        
        // First du the regular prefill for an input (setting the value)
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
    
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
        if ($data_sheet->getMetaObject()->is($this->getOptionsObject())) {
            $data_sheet->getColumns()->addFromAttribute($this->getTextAttribute());
        }
        
        return $data_sheet;
    }
    
    protected function setOptionsFromPrefillColumns(DataColumnInterface $value_column, DataColumnInterface $text_column = null)
    {
        $values = $value_column->getValues(false);
        
        if ($text_column) {
            $texts = $text_column->getValues(false);
        }
        
        $this->setSelectableOptions($values, $texts);
        $this->setValuesFromArray($values);
        return $this;
    }

    protected function setOptionsFromDataSheet(DataSheetInterface $data_sheet)
    {
        $data_sheet->getColumns()->addFromAttribute($this->getValueAttribute());
        $data_sheet->getColumns()->addFromAttribute($this->getTextAttribute());
        $data_sheet->dataRead();
        $this->setSelectableOptions($data_sheet->getColumns()->getByAttribute($this->getValueAttribute())->getValues(false), $data_sheet->getColumns()->getByAttribute($this->getTextAttribute())->getValues(false));
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::getValues()
     */
    public function getValues()
    {
        if ($this->getValue()) {
            // Split the value by value delimiter, but only if the raw value does not match
            // one of the selectable options exactly!
            if (! array_key_exists($this->getValue(), $this->getSelectableOptions())) {
                return explode($this->getMultiSelectValueDelimiter(), $this->getValue());
            } else {
                return array(
                    $this->getValue()
                );
            }
        } else {
            return array();
        }
    }

    /**
     * Defines multiple current values for the select via comma-separated list.
     * To be used instead of "value" if "multi-select" is TRUE.
     *
     * @uxon-property values
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValues()
     */
    public function setValues($expression_or_delimited_list)
    {
        $this->setValue($expression_or_delimited_list);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValuesFromArray()
     */
    public function setValuesFromArray(array $values)
    {
        if ($this->getMultiSelect()) {
            $this->setValue(implode($this->getMultiSelectValueDelimiter(), $values));
        } else {
            $this->setValue(reset($values));
        }
        return $this;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function getTextAttribute()
    {
        return $this->getOptionsObject()->getAttribute($this->getTextAttributeAlias());
    }

    /**
     * Defines the alias of the attribute of the options object to be displayed for every value.
     * If not set, the system will try to determine one automatically.
     *
     * If the text_attribute_alias was not set explicitly (e.g. via UXON), it will be determined as follows:
     * - If an option object was specified explicitly, it's label will be used (or it's UID if no label is defined)
     * - If the widget represents a relation, the related object's label will be used
     * - If the widget represents the UID of it's object, than the label of this object will be used
     * - If the widget represents any other attribute and there is no explicit options_object, this attribute
     * will be used for values as well as for the displayed text.
     *
     * @uxon-property text_attribute_alias
     * @uxon-type string
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
     * If the text_attribute_alias was not set explicitly (e.g. via UXON), it will be determined as follows:
     * - If an option object was specified explicitly, it's label will be used (or it's UID if no label is defined)
     * - If the widget represents a relation, the related object's label will be used
     * - If the widget represents the UID of it's object, than the label of this object will be used
     * - If the widget represents any other attribute and there is no explicit options_object, this attribute
     * will be used for values as well as for the displayed text.
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
                if ($this->getOptionsObject()->getLabelAttribute()) {
                    $this->text_attribute_alias = $this->getOptionsObject()->getLabelAlias();
                } else {
                    $this->text_attribute_alias = $this->getOptionsObject()->getUidAlias();
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

    public function setOptionsObject(Object $value)
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
     * Defines the meta object, which will be used to fetch the selectable options.
     * By default it is the object of the widget itself.
     *
     * @uxon-property options_object_alias
     * @uxon-type string
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
            } elseif ($this->getOptionsObject()->getUidAlias()) {
                $this->value_attribute_alias = $this->getOptionsObject()->getUidAlias();
            } else {
                throw new WidgetConfigurationError($this, 'Cannot create ' . $this->getWidgetType() . ': there is no value attribute defined and the options object "' . $this->getOptionsObject()->getAliasWithNamespace() . '" has no UID attribute!', '6V5FGYF');
            }
        }
        return $this->value_attribute_alias;
    }

    public function getValueAttribute()
    {
        return $this->getOptionsObject()->getAttribute($this->getValueAttributeAlias());
    }

    /**
     * Defines the alias of the attribute of the options object to be used as the internal value of the select.
     * If not set, the UID will be used.
     *
     * @uxon-property value_attribute_alias
     * @uxon-type string
     *
     * @param string $value            
     * @return InputSelect
     */
    public function setValueAttributeAlias($value)
    {
        $this->value_attribute_alias = $value;
        return $this;
    }

    public function getUsePrefillToFilterOptions()
    {
        return $this->use_prefill_to_filter_options;
    }

    /**
     * By default, the widget will try to only show options applicable to the prefill data.
     * Set to FALSE to always show all options.
     *
     * @uxon-property use_prefill_to_filter_options
     * @uxon-type string
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setUsePrefillToFilterOptions($value)
    {
        $this->use_prefill_to_filter_options = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getUsePrefillValuesAsOptions()
    {
        return $this->use_prefill_values_as_options;
    }

    /**
     * Makes the select only contain values from the prefill (if there are any) and no other options.
     *
     * @uxon-property use_prefill_to_filter_options
     * @uxon-type string
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setUsePrefillValuesAsOptions($value)
    {
        $this->use_prefill_values_as_options = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getOptionsDataSheet()
    {
        if (is_null($this->options_data_sheet)) {
            $this->options_data_sheet = DataSheetFactory::createFromObject($this->getOptionsObject());
        }
        return $this->options_data_sheet;
    }

    public function setOptionsDataSheet(DataSheetInterface $data_sheet)
    {
        if (! $this->getOptionsObject()->isExactly($data_sheet->getMetaObject())) {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot set options data sheet for ' . $this->getWidgetType() . ': meta object "' . $this->getOptionsObject()->getAliasWithNamespace() . '", but "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '" given instead!');
        }
        $this->options_data_sheet = $data_sheet;
        return $this;
    }

    /**
     * Sets an optional array of filter-objects to be used when fetching selectable options from a data source.
     *
     * For example, if we have a select for values of attributes of a meta object, but we only wish to show
     * values of active instances (assuming our object has the attribute "ACTIVE"), we would need the following
     * select:
     * {
     * "options_object_alias": "my.app.myobject",
     * "value_attribute_alias": "VALUE",
     * "text_attribute_alias": "NAME",
     * "filters":
     * [
     * {"attribute_alias": "ACTIVE", "value": "1", "comparator": "="}
     * ]
     * }
     *
     * @uxon-property filters
     * @uxon-type \exface\Core\CommonLogic\Model\Condition
     *
     * @param Condition[]|UxonObject[] $conditions_or_uxon_objects            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setFilters(array $conditions_or_uxon_objects)
    {
        foreach ($conditions_or_uxon_objects as $condition_or_uxon_object) {
            if ($condition_or_uxon_object instanceof Condition) {
                $this->getOptionsDataSheet()->getFilters()->addCondition($condition_or_uxon_object);
            } elseif ($condition_or_uxon_object instanceof \stdClass) {
                $uxon = UxonObject::fromAnything($condition_or_uxon_object);
                if (! $uxon->hasProperty('object_alias')) {
                    $uxon->setProperty('object_alias', $this->getMetaObject()->getAliasWithNamespace());
                }
                $this->getOptionsDataSheet()->getFilters()->addCondition(ConditionFactory::createFromUxon($this->getWorkbench(), $uxon));
            }
        }
        return $this;
    }

    /**
     * Sets an optional array of sorter-objects to be used when fetching selectable options from a data source.
     *
     * For example, if we have a select for sizes of a product and we only wish to show sort the ascendingly,
     * we would need the following config:
     * {
     * "options_object_alias": "my.app.product",
     * "value_attribute_alias": "SIZE_ID",
     * "text_attribute_alias": "SIZE_TEXT",
     * "sorters":
     * [
     * {"attribute_alias": "SIZE_TEXT", "direction": "ASC"}
     * ]
     * }
     *
     * @uxon-property sorters
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSorter
     *
     * @param DataSorter[]|UxonObject[] $data_sorters_or_uxon_objects            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setSorters(array $data_sorters_or_uxon_objects)
    {
        foreach ($data_sorters_or_uxon_objects as $sorter_or_uxon) {
            if ($sorter_or_uxon instanceof DataSorter) {
                $this->getOptionsDataSheet()->getSorters()->add($sorter_or_uxon);
            } elseif ($sorter_or_uxon instanceof \stdClass) {
                $uxon = UxonObject::fromAnything($sorter_or_uxon);
                $this->getOptionsDataSheet()->getSorters()->add(DataSorterFactory::createFromUxon($this->getOptionsDataSheet(), $uxon));
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
        if (is_null($this->multi_select_value_delimiter)){
            if ($this->getAttribute()){
                $this->multi_select_value_delimiter = $this->getAttribute()->getValueListDelimiter();
            } else {
                $this->multi_select_value_delimiter = EXF_LIST_SEPARATOR;
            }
        }
        return $this->multi_select_value_delimiter;
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
        $this->multi_select_value_delimiter = $value;
        return $this;
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
     * Sets the current value of the select.
     * If the given value is a delimited list and multi-select is disabled, the first item of the list will be used.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::setValue()
     */
    public function setValue($value)
    {
        if (! $this->hasOption($value) && strpos($value, $this->getMultiSelectValueDelimiter())) {
            if (! $this->getMultiSelect()) {
                return parent::setValue(explode($this->getMultiSelectValueDelimiter(), $value)[0]);
            }
        }
        return parent::setValue($value);
    }
}
?>