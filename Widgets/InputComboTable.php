<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\Factories\QueryBuilderFactory;

/**
 * An InputComboTable is similar to InputCombo, but it uses a DataTable to show the autosuggest values.
 * 
 * Thus, the user can see more information about every suggested object. The InputComboTable is very often used with relations,
 * where the related object may have many more data, then merely it's id (which is the value of the relation attribute).
 * 
 * The DataTable for autosuggests can either be genreated automatically based on the meta object, or specified by the user via
 * UXON or even extended from any other ready-made DataTable!
 * 
 * While not every UI-framework supports such a kind of widget, there are many ways to implement the main idea of the InputComboTable:
 * showing more data about a selectable object in the autosuggest. Mobile templates might use cards like in Googles material design,
 * for example.
 * 
 * InputComboTables support two type of live references to other objects: in the value and in the data filters. Concider the following
 * example, where we need a product selector for an order position. We order a specific product variant, but we need a two-step
 * selector, so we can select the product first and choose one of it's variants afterwards. To do this, we need an extra product
 * selector befor the actual variant selector for our order position. The product selector does not refer to an order attribute,
 * so we declare it display_only (so it will not get included in action data). Our variant selector has a filter reference to the
 * product selector. This means, that once a product is selected, only variants of that product will be displayed. If no product
 * is selected, we can search through all product variants in the system. But what happens if we select a variant and do not
 * touch the product selector (This will actually happen every time the form is prefilled). The id-reference in the product
 * selector takes care of that: It sets the value of the selector to the product id of the selected variant. Of course, if
 * the product id does not belong to the default display attributes of the variant, we need to add it to the respective combo
 * manually: just add it next to the `~DEFAULT_DISPLAY` attribute group.
 * 
 * ```
 * {
 *  "widget_type": "Form",
 *  "object_alias": "MY.APP.ORDER_POSITION",
 *  {
 *      "widget_type": "InputComboTable",
 *      "object_alias": "MY.APP.PRODUCT",
 *      "id": "product_selector",
 *      "value": "=product_variant_selector!product_id",
 *      "display_only": true
 *  },
 *  {
 *      "widget_type": "InputComboTable",
 *      "attribute_alias: "PRODUCT_VARIANT"
 *      "id": "product_variant_selector",
 *      "table": {
 *          "columns": [
 *              { "attribute_group_alias": "~DEFAULT_DISPLAY" },
 *              { "attribute_alias": "PRODUCT"}
 *          ],
 *      "filters": [
 *          {
 *              "attribute_alias": "PRODUCT"
 *              "value": "=product_selector!id"
 *          }
 *      ]
 *  }
 * }
 *  
 * ```
 * 
 * You can add as many widgets in this chain of live references, as you wish. This way, interactive selectors can be built
 * for very complex hierarchies. If you do not want the lower hierarchy levels to be selectable before the higher levels
 * are set, make the respective fiters required (in the above example, adding "required": "true" to the PRODUCT-filter of
 * the variant selector would make this selector disabled until a product is selected).
 * 
 * Note, that if a value is changed by the user, all the referencing filters will be updated causing their widgets
 * to revalidate. This means, that changing the product in our example, will reload data for the variant selector filtered
 * by the new product. Most likely, the previously selected variant will not belong to the new product, so the variant
 * selector will be emptied automatically. Unless, of course, the new product only has one variant and
 * autoselect_single_suggestion is true, than the value of the only variant of the new product will be automatically selected.
 * 
 * Changing or removing a value will also change/empty all referencing values.
 * 
 * For hierarchies like the one in the above example this means, that changing a value at a certain level will change the
 * values at higher levels and revalidate values at lower levels. Similarly, removing a value will in the middle will empty
 * higher level selectors and revalidate lower level fields.
 * 
 * @author Andrej Kabachnik
 *        
 */
class InputComboTable extends InputCombo implements iCanPreloadData
{

    private $text_column_id = null;

    private $value_column_id = null;

    private $data_table = null;

    private $table_uxon = null;

    /**
     * Returns the relation, this widget represents or FALSE if the widget stands for a direct attribute.
     * This shortcut function is very handy because a InputComboTable often stands for a relation.
     *
     * @return \exface\Core\CommonLogic\Model\relation
     */
    public function getRelation()
    {
        if ($this->getAttribute()->isRelation()) {
            return $this->getMetaObject()->getRelation($this->getAttributeAlias());
        } else {
            return false;
        }
    }

    /**
     * Returns the DataTable, that is used for autosuggesting in a InputComboTable or false if a DataTable cannot be created
     *
     * @return \exface\Core\Widgets\DataTable|boolean
     */
    public function getTable()
    {
        // If the data table was not specified explicitly, attempt to create one from the attirbute_alias
        if ($this->data_table === null) {
            $this->initTable();
        }
        return $this->data_table;
    }

    protected function initTable()
    {
        // This will only work if there is an attribute_alias specified
        if (! $this->getAttributeAlias()) {
            throw new WidgetConfigurationError($this, 'Cannot create a DataTable for a InputComboTable before an attribute_alias for the Comobo is specified!', '6T91QQ8');
            return false;
        }
        
        // Create a table widget and set those options, that may be overridden by the user in the UXON description of the Combo
        /* @var $table \exface\Core\Widgets\DataTable */
        $table = $this->getPage()->createWidget('DataTable', $this);
        $table->setMetaObject($this->getTableObject());
        $table->setHideHelpButton(true);
        $table->setUidColumnId($this->getValueColumnId());
        $table->setHeaderSortMultiple(false);
        $table->getToolbarMain()->setIncludeNoExtraActions(true);
        $table->getPaginator()->setCountAllRows(false);
        
        // Now see if the user had already defined a table in UXON
        /* @var $table_uxon \exface\Core\CommonLogic\UxonObject */
        $table_uxon = $this->getTableUxon();
        if (! $table_uxon->isEmpty()) {
            // Do not allow custom widget types
            $table_uxon->unsetProperty('widget_type');
            $table->importUxonObject($table_uxon);
        }
        
        // Add default attributes
        if (! $table_uxon->hasProperty('columns') || $table_uxon->getProperty('columns')->isEmpty()) {
            $table->addColumnsForDefaultDisplayAttributes();
        }
        
        // Enforce those options that cannot be overridden in the table's UXON description
        $table->setMultiSelect($this->getMultiSelect());
        $table->setLazyLoading($this->getLazyLoading());
        $table->setLazyLoadingActionAlias($this->getLazyLoadingActionAlias());
        
        $this->data_table = $table;
        
        // Ensure, that special columns needed for the InputComboTable are present. This must be done after $this->data_table is
        // set, because the method may use autogeneration of the text column, which needs to know about the DataTable
        $this->addComboColumns();
        return $table;
    }

    protected function getTableUxon()
    {
        if (is_null($this->table_uxon)) {
            if ($this->exportUxonObjectOriginal()->hasProperty('table')) {
                $this->table_uxon = $this->exportUxonObjectOriginal()->getProperty('table');
            } else {
                $this->table_uxon = new UxonObject();
            }
        }
        return $this->table_uxon;
    }

    /**
     * Defines, what the table used to display autosuggests will look like.
     * Leave empty for an autogenerated table.
     *
     * @uxon-property table
     * @uxon-type \exface\Core\Widgets\Data
     * @uxon-template {"object_alias": "", "columns": [{"attribute_alias": ""}]}
     *
     * @param UxonObject|DataTable $widget_or_uxon_object            
     * @throws WidgetConfigurationError
     * @throws WidgetPropertyInvalidValueError
     * @return InputComboTable
     */
    public function setTable($widget_or_uxon_object)
    {
        if ($widget_or_uxon_object instanceof DataTable) {
            $this->data_table = $widget_or_uxon_object;
            $this->setTableObjectAlias($widget_or_uxon_object->getMetaObject()->getAliasWithNamespace());
        } elseif ($widget_or_uxon_object instanceof UxonObject) {
            if ($widget_or_uxon_object->hasProperty('object_alias')) {
                $this->setTableObjectAlias($widget_or_uxon_object->getProperty('object_alias'));
            }
            // Do noting, the table will be initialized later, when all the other UXON properties have been processed.
            // TODO this works fine with creating widgets from UXON but will not work if a UXON object is being passed
            // programmatically - need to save the given UXON in an extra variable if we are to support this.
            if ($this->data_table) {
                throw new WidgetConfigurationError($this, 'Cannot load the table-UXON of a "' . $this->getWidgetType() . '": the internal table had been already initialized!');
            }
        } elseif ($widget_or_uxon_object != '') {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value for property "table" of "' . $this->getWidgetType() . '" given! This property only accepts UXON widget description objects or instantiated DataTable widgets.');
        }
        return $this;
    }

    /**
     * Creates table columns for the value and text attributes of the combo and adds them to the table.
     * NOTE: the columns are only added if they are not there already (= if they are not part of the default columns)
     * and they will be automatically hidden, if the corresponding attribute is hidden!
     *
     * @return InputComboTable
     */
    protected function addComboColumns()
    {
        $table = $this->getTable();
        $table_meta_object = $this->getTable()->getMetaObject();
        
        // If there is no text column, use text_attribute_alias: first see if there already is a corresponding
        // column in the table and use it then, otherwise add one.
        if (! $this->getTextColumnId()) {
            // If there is no text column explicitly defined, take the label attribute as text column
            if ($text_column = $this->getTable()->getColumnByAttributeAlias($this->getTextAttributeAlias())) {
                // If the table already has a lable column, use it
                $this->setTextColumnId($text_column->getId());
            } else {
                // If there is no label column yet, add it...
                $text_column = $table->createColumnFromAttribute($table_meta_object->getAttribute($this->getTextAttributeAlias()));
                // ...but make it hidden if there are other columns there, because the regular columns are what the user actually
                // wants to see - they will probably already contain the label data, but, perhaps, split into multiple columns.
                if ($table->hasColumns()){
                    $text_column->setHidden(true);
                }
                $table->addColumn($text_column);
                $this->setTextColumnId($text_column->getId());
            }
        }
        
        // Same goes for the value column: use the first existing column for value_attribute_alias or create a new one.
        if (! $this->getValueColumnId()) {
            if ($value_column = $this->getTable()->getColumnByAttributeAlias($this->getValueAttributeAlias())) {
                $this->setValueColumnId($value_column->getId());
            } else {
                $value_column = $table->createColumnFromAttribute($table_meta_object->getAttribute($this->getValueAttributeAlias()), null, true);
                $table->addColumn($value_column);
                $this->setValueColumnId($value_column->getId());
            }
        }
        
        // Make sure, the table has the corret quick search filter
        // If not, the quick search would only get performed on the object label, which is not neccessarily 
        // the text attribute of the widget. And object without a label would not work at all
        $quickSearchFilter = WidgetFactory::createFromUxon($this->getPage(), new UxonObject([
            "widget_type" => 'InputHidden',
            "attribute_alias" => $this->getTextAttributeAlias()
        ]), $table);
        $table->addFilter($quickSearchFilter, true);
        
        return $this;
    }

    public function getTextColumnId()
    {
        return $this->text_column_id;
    }

    /**
     * Makes the displayed value (text) shown in the Combo come from a specific column of the data widget
     *
     * If not set, text_attribute_alias will be used, just like in a regular InputCombo
     *
     * @uxon-property text_column_id
     * @uxon-type string
     *
     * @param string $value 
     * @return InputComboTable           
     */
    public function setTextColumnId($value)
    {
        $this->text_column_id = $value;
        if ($this->getTextColumn()) {
            $this->setTextAttributeAlias($this->getTextColumn()->getAttributeAlias());
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid text_column_id "' . $value . '" specified: no matching column found in the autosuggest table!', '6TV1LBR');
        }
        return $this;
    }

    /**
     * Returns the column of the DataTable, where the text displayed in the combo will come from
     *
     * @throws WidgetLogicError if no text column can be found
     * @return DataColumn
     */
    public function getTextColumn()
    {
        $col = $this->getTable()->getColumn($this->getTextColumnId());
        if (! $col) {
            throw new WidgetLogicError($this, 'No text data column found for ' . $this->getWidgetType() . ' with attribute_alias "' . $this->getAttributeAlias() . '"!');
        }
        return $col;
    }

    public function getValueColumnId()
    {
        return $this->value_column_id;
    }

    /**
     * Makes the internal value (mostyl invisible) of the Combo come from a specific column of the data widget
     *
     * If not set, value_attribute_alias will be used, just like in a regular InputCombo
     *
     * @uxon-property value_column_id
     * @uxon-type string
     *
     * @param string $value 
     * @return InputComboTable           
     */
    public function setValueColumnId($value)
    {
        $this->value_column_id = $value;
        $this->getTable()->setUidColumnId($value);
        
        if ($this->getValueColumn()) {
            $this->setValueAttributeAlias($this->getValueColumn()->getAttributeAlias());
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value_column_id "' . $value . '" specified: no matching column found in the autosuggest table!', '6TV1LBR');
        }
        return $this;
    }

    /**
     * Returns the column of the DataTable, where the value of the combo will come from
     *
     * @throws WidgetLogicError if no value column defined
     * @return DataColumn
     */
    public function getValueColumn()
    {
        if (! $this->getTable()->getColumn($this->getValueColumnId())) {
            throw new WidgetLogicError($this, 'No value data column found for ' . $this->getWidgetType() . ' with attribute_alias "' . $this->getAttributeAlias() . '"!');
        }
        return $this->getTable()->getColumn($this->getValueColumnId());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefillWithWidgetObject()
     */
    protected function doPrefillWithWidgetObject(DataSheetInterface $data_sheet)
    {
        if (! $this->getAttributeAlias() || ! $data_sheet->getColumns()->getByExpression($this->getAttributeAlias())){
            return;
        }
        
        // If the prefill data is based on the same object, as the widget and has a column matching
        // this widgets attribute_alias, simply look for all the required attributes in the prefill data.
        if ($col = $data_sheet->getColumns()->getByExpression($this->getAttributeAlias())) {
            $valuePointer = DataPointerFactory::createFromColumn($col, 0);
            $this->setValue($valuePointer->getValue());
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', $valuePointer));
        }
        
        // Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
        // but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
        // set it here. In most templates, setting merely the value of the combo well make the template load the
        // corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
        if ($this->getAttribute()->isRelation()) {
            $text_column_expr = RelationPath::relationPathAdd($this->getRelation()->getAlias(), $this->getTextColumn()->getAttributeAlias());
            // If the column we would need is not there and it's the label column (which is very probable), it might just be named differently
            // Many DataSheets include relation__LABEL columns but may not inlcude a column with the alias of the label attribute. It's worth
            // trying this trick to prevent additional queries to the data source just to find the text for the combo value!
            if (! $data_sheet->getColumns()->getByExpression($text_column_expr) && $this->getTextColumn()->getAttribute()->isLabelForObject() === true) {
                $text_column_expr = RelationPath::relationPathAdd($this->getRelation()->getAlias(), $this->getWorkbench()->getConfig()->getOption('METAMODEL.OBJECT_LABEL_ALIAS'));
            }
        } elseif ($this->getMetaObject()->isExactly($this->getTable()->getMetaObject())) {
            $text_column_expr = $this->getTextColumn()->getExpression()->toString();
        }
        
        if ($text_column_expr && $col = $data_sheet->getColumns()->getByExpression($text_column_expr)) {
            $textPointer = DataPointerFactory::createFromColumn($col, 0);
            $this->setValueText($textPointer->getValue());
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value_text', $textPointer));
        }
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefillWithOptionsObject()
     */
    protected function doPrefillWithOptionsObject(DataSheetInterface $data_sheet)
    {
        // If the sheet is based upon the object, that is being selected by this Combo, we can use the prefill sheet
        // values directly
        if ($col = $data_sheet->getColumns()->getByAttribute($this->getValueAttribute())) {
            $pointer = DataPointerFactory::createFromColumn($col, 0);
            $this->setValue($pointer->getValue());
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value', $pointer));
        }
        if ($col = $data_sheet->getColumns()->getByAttribute($this->getTextAttribute())) {
            $pointer = DataPointerFactory::createFromColumn($col, 0);
            $this->setValueText($pointer->getValue());
            $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'value_text', $pointer));
        }
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefillWithRelationsInData()
     */
    protected function doPrefillWithRelationsInData(DataSheetInterface $data_sheet)
    {
        if (! $this->getRelation()){
            return;
        }
        
        // If it is not the object selected within the combo, than we still can look for columns in the sheet, that
        // contain selectors (UIDs) of that object. This means, we need to look for data columns showing relations
        // and see if their related object is the same as the related object of the relation represented by the combo.
        foreach ($data_sheet->getColumns()->getAll() as $column) {
            if ($column->getAttribute() && $column->getAttribute()->isRelation()) {
                if ($column->getAttribute()->getRelation()->getRightObject()->is($this->getRelation()->getRightObject())) {
                    $this->setValuesFromArray($column->getValues(false));
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'values', DataPointerFactory::createFromColumn($column)));
                    return;
                }
            }
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        
        if (! $data_sheet->isEmpty()) {
            if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
                $this->doPrefillWithWidgetObject($data_sheet);
            } else {
                // If the prefill data was loaded for another object, there are still multiple possibilities to prefill
                if ($data_sheet->getMetaObject()->is($this->getTableObject())) {
                    $this->doPrefillWithOptionsObject($data_sheet);
                    return;
                } elseif ($this->getRelation()) {
                    $this->doPrefillWithRelationsInData($data_sheet);
                    return;
                }
                // If we are still here, that means, the above checks did not work. We still can try to use the prefill data
                // to filter the options, so just pass it to the internal data widget
                $this->getTable()->prefill($data_sheet);
            }
        }
    }

    /**
     *
     * {@inheritdoc} To prefill a combo, we need it's value and the corresponding text.
     *              
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
        // Do not request any prefill data, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return $data_sheet;
        }
        
        if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
            $data_sheet->getColumns()->addFromExpression($this->getAttributeAlias());
            
            // Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
            // but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
            // set it here. In most templates, setting merely the value of the combo will make the template load the
            // corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
            if ($this->getAttribute() && $this->getAttribute()->isRelation()) {
                $text_column_expr = RelationPath::relationPathAdd($this->getRelation()->getAlias(), $this->getTextColumn()->getAttributeAlias());
                // When the text for a combo comes from another data source, reading it in advance
                // might have a serious performance impact. Since addint the text column to the prefill
                // is generally optional (see above), it is a good idea to check, if the text column
                // can be read with the same query, as the rest of the prefill da and, if not, exclude
                // it from the prefill.
                $sheetObj = $data_sheet->getMetaObject();
                if ($sheetObj->hasAttribute($text_column_expr)) {
                    $sheetQuery = QueryBuilderFactory::createForObject($sheetObj);
                    if (! $sheetQuery->canRead($text_column_expr)) {
                        unset($text_column_expr);
                    }
                }
            } elseif ($this->getMetaObject()->isExactly($this->getTable()->getMetaObject())) {
                $text_column_expr = $this->getTextColumn()->getExpression()->toString();
            } 
            
            if ($text_column_expr) {
                $data_sheet->getColumns()->addFromExpression($text_column_expr);
            }
        } elseif ($this->getRelation() && $this->getRelation()->getRightObject()->is($data_sheet->getMetaObject())) {
            $data_sheet->getColumns()->addFromAttribute($this->getRelation()->getRightKeyAttribute());
            foreach ($this->getTable()->getColumns() as $col) {
                $data_sheet->getColumns()->addFromExpression($col->getExpression(), $col->getDataColumnName());
            }
        } else {
            // TODO what if the prefill object is not the one at the end of the current relation?
        }
        
        return $data_sheet;
    }

    /**
     * Since the InputComboTable contains a DataTable widget, we need to return it as a child widget to allow ajax data loaders to
     * find the table a load data for it.
     * This does not make the InputComboTable a container though!
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getTable();
    }

    public function getMaxSuggestions()
    {
        if (parent::getMaxSuggestions() === null && $this->getTable() && $this->getTable()->getPaginator()->getPageSize() !== null) {
            $this->setMaxSuggestions($this->getTable()->getPaginator()->getPageSize());
        }
        return parent::getMaxSuggestions();
    }

    public function getTableObjectAlias()
    {
        return $this->getOptionsObjectAlias();
    }

    /**
     * Makes the autosuggest-table use a different meta object than the input.
     *
     * Use with care! Using a different object normally requires custom value_column_id and text_column_id.
     *
     * @uxon-property table_object_alias
     * @uxon-type metamodel:object
     *
     * @param string $value            
     * @return \exface\Core\Widgets\InputComboTable
     */
    public function setTableObjectAlias($value)
    {
        return $this->setOptionsObjectAlias($value);
    }

    /**
     * Returns the meta object, that the table within the combo will show
     *
     * @throws WidgetConfigurationError
     * @return MetaObjectInterface
     */
    public function getTableObject()
    {
        return $this->getOptionsObject();
    }
    
    /**
     * The options object of a InputComboTable is the meta object of the relation it 
     * represents if not specified explicitly.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::getOptionsObject()
     */
    public function getOptionsObject()
    {
        if (! $this->isOptionsObjectSpecified()) {
            if ($this->getAttribute()->isRelation()) {
                $this->setOptionsObject($this->getMetaObject()->getRelation($this->getAttributeAlias())->getRightObject());
            }
        }
        return parent::getOptionsObject();
    }

    /**
     * Sets an optional array of filter-objects to be used when fetching autosugest data from a data source.
     *
     * For example, if we have a InputComboTable for customer ids, but we only wish to show customers of a certain
     * class (assuming every custer hase a relation "CUSOMTER_CLASS"), we would need the following InputComboTable:
     * 
     * ```
     *  {
     *      "options_object_alias": "my.app.CUSTOMER",
     *      "filters": [
     *          {"attribute_alias": "CUSTOMER_CLASS__ID", "value": "VIP", "comparator": "="}
     *      ]
     *  }
     *  
     * ```
     *
     * We can even use widget references to get the filters. Imagine, the InputComboTable for customers above is
     * placed in a form, where the customer class can be selected explicitly in another InputComboTable or a InputSelect
     * with the id "customer_class_selector".
     * 
     * ```
     *  {
     *      "options_object_alias": "my.app.CUSTOMER",
     *      "filters": [
     *          {"attribute_alias": "CUSTOMER_CLASS__ID", "value": "=customer_class_selector!ID"}
     *      ]
     *  }
     *  
     * ```
     *
     * @uxon-property filters
     * @uxon-type \exface\Core\CommonLogic\Model\Condition[]
     * @uxon-template [{"attribute_alias": "", "value": "", "comparator": "="}]
     *
     * @param Condition[]|UxonObject $conditions_or_uxon_objects            
     * @return \exface\Core\Widgets\InputSelect
     */
    public function setFilters($conditions_or_uxon_objects)
    {
        if (! $this->getTableUxon()->hasProperty('filters')) {
            $this->getTableUxon()->setProperty('filters', array());
        }
        
        foreach ($conditions_or_uxon_objects as $condition_or_uxon_object) {
            if ($condition_or_uxon_object instanceof Condition) {
                // TODO
            } elseif ($condition_or_uxon_object instanceof UxonObject) {
                $this->getTableUxon()->setProperty('filters', array_merge($this->getTableUxon()->getProperty('filters')->toArray(), array(
                    $condition_or_uxon_object
                )));
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'Cannot set filters of ' . $this->getWidgetType() . ': expecting instantiated conditions or their UXON descriptions - ' . gettype($condition_or_uxon_object) . ' given instead!');
            }
        }
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
        // TODO add properties specific to this widget here
        return $uxon;
    }
    
    /**
     * Set to TRUE to preload table data asynchronously (e.g. for offline-capable templates)
     * 
     * @uxon-property preload_data
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::setPreloadData()
     */
    public function setPreloadData($uxonOrString): iCanPreloadData
    {
        $this->getTable()->setPreloadData($uxonOrString);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::isPreloadDataEnabled()
     */
    public function isPreloadDataEnabled(): bool
    {
        return $this->getTable()->isPreloadDataEnabled();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::prepareDataSheetToPreload()
     */
    public function prepareDataSheetToPreload(DataSheetInterface $dataSheet): DataSheetInterface
    {
        return $this->getPreloader()->prepareDataSheetToPreload($dataSheet);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::getPreloader()
     */
    public function getPreloader(): DataPreloader
    {
        return $this->getTable()->getPreloader();
    }

}
?>