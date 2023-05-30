<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Events\Widget\OnWidgetLinkedEvent;
use exface\Core\Interfaces\Events\WidgetLinkEventInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\DataSheetFactory;

/**
 * An InputComboTable is similar to InputCombo, but it uses a DataTable to show the autosuggest values.
 * 
 * This way, the user can see more information about every suggested items. The `InputComboTable` is 
 * very handy to use with relations, where the related object often has more relevant data, then merely 
 * it's UID or `ALIAS`.
 * 
 * There are multiple typical use cases for the `InputComboTable`:
 * 
 * - with an attribute, that is a relation. In this case, the suggestion-table automatically shows the 
 * related objects with their default display attributes while the value of the `InputComboTable` itself 
 * is the relation attribute (i.e. foreign key). You can define a custom `table` for the suggestions if
 * you need additional columns as shown below.
 * - with a relation attribute and a custom `table` definition. In this case you can configure the table
 * completely individually as you would do for a regular `DataTable`. Keep in mind, that all attributes 
 * and relation paths need to be relative to the table's object (= the related object).
 * - with a regular attribute. In this case, the suggestion table will show a distinct list of previously
 * used values of the attribute. The table will have just a single column unless you add a `table`
 * definition manually as in the case above.
 * - with a custom `value_attribute_alias` and a custom `table` definition. This allows to look up
 * an value in a totally unrelated object. The `value_attribute_alias` would be the attribute of the
 * table-object to take the value from while the widget's `attribute_alias` would be the place to use
 * that value.
 * 
 * In general, the `DataTable` for autosuggests will always be genreated automatically as shown above
 * unless it is specified by the user via UXON in the `table` property. If a custom `table` is used,
 * all it's attributes and relations are based on the table's object. The same goes for `value_attribute_alias`
 * and `text_attribute_alias` - they too are meant to be relative to the `table_object_alias`.
 * 
 * In addition to the tabluar autosuggest, the `InputComboTable` has a `lookup_action`, which will open
 * an advanced search dialog with even more details, filters and other options. By default, the generic
 * `exface.Core.ShowLookupDialog` action is used, which produces a dialog automatiscally from the object's 
 * model default display attributes, but you can customize the the action as well as it's widget within
 * the `lookup_action` property of the `InputComboTable`.
 * 
 * While not every UI-framework supports tabular autosuggests directly, there are many ways to implement 
 * the idea of the `InputComboTable`: showing more data about a selectable object in the autosuggest. Mobile 
 * facades might use cards like in Google's material design, for example. Also not all facades support the
 * `lookup_action` - in this case the corresponding button will simply not show up.
 * 
 * ## Examples
 * 
 * ### Custom lookup widget
 * 
 * ```
 * {
 *  "widget_type": "InputComboTable",
 *  "lookup_action": {
 *      "alias": "exface.Core.ShowLookupDialog",
 *      "widget": {
 *          "object_alias": "...",
 *          "widget_type": "DataTable",
 *          "filters": [
 *              {
 *                "attribute_alias": "..."
 *              }
 *          ],
 *          "columns": [
 *              {
 *                "attribute_alias": "..."
 *              }
 *          ]
 *      }
 *   }
 * }
 * 
 * ```
 * 
 * ### Live references between InputComboTables
 * 
 * Concider the following example, where we need a product selector for an order position. We order 
 * a specific product variant, but we need a two-step selector, so we can select the product first and choose 
 * one of it's variants afterwards. To do this, we need an extra product selector befor the actual variant 
 * selector for our order position. The product selector does not refer to an order attribute, so we declare 
 * it display_only (so it will not get included in action data). Our variant selector has a filter reference 
 * to the product selector. This means, that once a product is selected, only variants of that product will 
 * be displayed. If no product is selected, we can search through all product variants in the system. But what 
 * happens if we select a variant and do not touch the product selector (This will actually happen every time 
 * the form is prefilled). The id-reference in the product selector takes care of that: It sets the value 
 * of the selector to the product id of the selected variant. Of course, if the product id does not belong 
 * to the default display attributes of the variant, we need to add it to the respective combo manually: 
 * just add it next to the `~DEFAULT_DISPLAY` attribute group.
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
    
    private $lookupActionUxon = null;
    
    private $lookupButton = null;
    
    private $tableDataSheet = null;
    
    /**
     * 
     * @var WidgetLinkInterface[]
     */
    private $incomingLinks = [];
    
    protected function init()
    {
        parent::init();
        
        $this->getWorkbench()->eventManager()->addListener(OnWidgetLinkedEvent::getEventName(), [$this, 'handleWidgetLinkedEvent']);
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
            if ($this->isRelation()) {
                foreach ($table->createDefaultColumns() as $col) {
                    $table->addColumn($col);
                }
            } elseif ($this->isBoundToAttribute()) {
                $table->addColumn($table->createColumnFromAttribute($this->getAttribute()));
                $table->addColumn($table->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => DataAggregation::addAggregatorToAlias($this->getAttributeAlias(), AggregatorFunctionsDataType::COUNT),
                    'caption' => '=TRANSLATE("exface.Core", "WIDGET.INPUTCOMBOTABLE.COLUMN_NAME_USES")'
                ])));
            }
        }
        
        if (! $this->isRelation()) {
            $table->setAggregateByAttributeAlias($this->getAttributeAlias());
        }
        
        // Enforce those options that cannot be overridden in the table's UXON description
        $table->setMultiSelect($this->getMultiSelect());
        $table->setLazyLoading($this->getLazyLoading());
        $table->setLazyLoadingAction($this->getLazyLoadingActionUxon());
        
        // Add a quick-search filter over the text-attribute to make sure quick search works correctly
        // even if the table object has no alias!
        $table->addFilter(
            $table->getConfiguratorWidget()->createFilterWidget($this->getTextAttributeAlias())
            ->setHidden(true)
            ->setIncludeInQuickSearch(true)
        );
        
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
     * @uxon-template {"object_alias": "", "columns": [{"attribute_group_alias": "~DEFAULT_DISPLAY"}, {"attribute_alias": ""}]}
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
        if ($col = $this->getTextColumn()) {
            $this->setTextAttributeAlias($col->getAttributeAlias());
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
        
        if ($col = $this->getValueColumn()) {
            $this->setValueAttributeAlias($col->getAttributeAlias());
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
        if (! $col = $this->getTable()->getColumn($this->getValueColumnId())) {
            throw new WidgetLogicError($this, 'No value data column found for ' . $this->getWidgetType() . ' with attribute_alias "' . $this->getAttributeAlias() . '"!');
        }
        return $col;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputCombo::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        
        if ($data_sheet->isEmpty() === true) {
            return;
        }
        
        if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
            $this->doPrefillWithWidgetObject($data_sheet);
        } else {
            // If the prefill data was loaded for another object, there are still multiple 
            // possibilities to prefill
            if ($data_sheet->getMetaObject()->is($this->getTableObject())) {
                $this->doPrefillWithOptionsObject($data_sheet);
                return;
            } elseif ($this->isRelation()) {
                $this->doPrefillWithRelationsInData($data_sheet);
                return;
            }
            // If we are still here, that means, the above checks did not work. We still can try to use the prefill data
            // to filter the options, so just pass it to the internal data widget
            $this->getTable()->prefill($data_sheet);
        }
        
        return;
    }

    /**
     * In addition to the combo prefill, we need the table columns if possible
     * 
     * @see \exface\Core\Widgets\InputCombo::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        $sheetObj = $data_sheet->getMetaObject();
        $widgetObj = $this->getMetaObject();
        
        if (! $sheetObj->is($widgetObj) && $this->isRelation() && $this->getRelation()->getRightObject()->is($sheetObj)) {
            foreach ($this->getTable()->getColumns() as $col) {
                $data_sheet->getColumns()->addFromExpression($col->getExpression(), $col->getDataColumnName());
            }
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
        yield $this->getLookupButton();
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
     * Condition group to filter rows of the table.
     * 
     * In contrast to `filters` inside the `table` definition, these filters here are meant to be evaluated
     * after the data was read from the data source. Thus, they can contain live references to current
     * values of other widgets.
     *
     * For example, if we have a InputComboTable for customer ids, which is placed in a form, where the 
     * customer class can be selected explicitly in another InputComboTable or a InputSelect with the id 
     * "customer_class_selector".
     *
     * ```
     *  {
     *      "options_object_alias": "my.app.CUSTOMER",
     *      "filters": {
     *          "operator": "AND",
     *          "conditions": [
     *              {
     *                  "value_left": "CUSTOMER_CLASS__ID", 
     *                  "comparator": "==", 
     *                  "value_right": "=customer_class_selector!ID"
     *              }
     *          ]
     *      }
     *  }
     *
     * ```
     * 
     * On the other hand, if the customer class is static, the configuration would look like this:
     * 
     * ```
     *  {
     *      "options_object_alias": "my.app.CUSTOMER",
     *      "filters": {
     *          "operator": "AND",
     *          "conditions": [
     *              {
     *                  "value_left": "CUSTOMER_CLASS__ID", 
     *                  "comparator": "=", 
     *                  "value_right": "VIP"
     *              }
     *          ]
     *      }
     *  }
     *
     * ```
     *
     * @uxon-property filters
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "==", "value_right": ""}]}
     *
     * @see \exface\Core\Widgets\InputCombo::setFilters($uxon)
     */
    public function setFilters(UxonObject $uxon) : InputSelect
    {
        // Handle legacy syntax `[{"attribute_alias": "", "value": "", "comparator": "="}]`
        if ($uxon->isArray()) {
            if (! $this->getTableUxon()->hasProperty('filters')) {
                $this->getTableUxon()->setProperty('filters', []);
            }
            $filterPropUxon = new UxonObject([
                'operator' => EXF_LOGICAL_AND,
                'conditions' => []
            ]);
            foreach ($uxon as $filterUxon) {
                if ($filterUxon instanceof UxonObject) {
                    $this->getTableUxon()->appendToProperty('filters', $filterUxon);
                    $filterPropUxon->appendToProperty('conditions', new UxonObject([
                        'value_left' => $filterUxon->getProperty('attribute_alias'),
                        'comparator' => $filterUxon->getProperty('comparator') ?? ComparatorDataType::EQUALS,
                        'value_right' => $filterUxon->getProperty('value')
                    ]));
                } else {
                    throw new WidgetPropertyInvalidValueError($this, 'Cannot set filters of ' . $this->getWidgetType() . ': expecting instantiated conditions or their UXON descriptions - ' . gettype($filterUxon) . ' given instead!');
                }
            }
            return parent::setFilters($filterPropUxon);
        }
        
        return parent::setFilters($uxon);
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
     * Set to TRUE to preload table data asynchronously (e.g. for offline-capable facades)
     * 
     * @deprecated replaced by the PWA model
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
    
    /**
     * 
     * @throws WidgetPropertyNotSetError
     * @return ActionInterface
     */
    public function getLookupAction() : ActionInterface
    {
        return $this->getLookupButton()->getAction();
    }
    
    /**
     * The action to open an advanced search dialog.
     * 
     * NOTE: depending on the facade used, the trigger for the advanced search dialog
     * may be different: some facades will place a search-button next to the dropdown,
     * others may include a "more..."-item in the dropdown menu, etc. There may also
     * be facades, that do not support lookup dialogs for `InputComboTable`.
     * 
     * By default, the generic action `exface.Core.ShowLookupDialog` is used. It creates
     * a search dialog based on default-display settings in the metamodel of the object
     * being searched. This basically means, that the lookup-dialog will show the same
     * table as the `InputComboTable` and provide filter over every visible column.
     * 
     * You can customize the lookup dialog by specifying a custom `lookup_action` or
     * a custom `widget` within the action's configuration:
     * 
     * ```
     * {
     *  "widget_type": "InputComboTable",
     *  "lookup_action": {
     *      "alias": "exface.Core.ShowLookupDialog",
     *      "widget": {
     *          "object_alias": "...",
     *          "widget_type": "DataTable",
     *          "filters": [
     *              {
     *                "attribute_alias": "..."
     *              }
     *          ],
     *          "columns": [
     *              {
     *                "attribute_alias": "..."
     *              }
     *          ]
     *      }
     *   }
     * }
     * 
     * ```
     * 
     * If you plan to reuse the lookup widget multiple times, save the action's
     * configuration in the model and use it like this:
     * 
     * ```
     * {
     *  "widget_type": "InputComboTable",
     *  "lookup_action": {
     *      "alias": "my.App.MyLookupAction"
     *  }
     * }
     * 
     * ```
     *
     * @uxon-property lookup_action
     * @uxon-type \exface\Core\Actions\ShowLookupDialog
     * @uxon-template {"alias": "exface.Core.ShowLookupDialog"}
     *
     * @param UxonObject $uxon
     * @throws WidgetLogicError
     * @return InputComboTable
     */
    public function setLookupAction(UxonObject $uxon) : InputComboTable
    {
        if ($this->lookupButton !== null) {
            throw new WidgetLogicError($this, 'Cannot set lookup_action for ' . $this->getWidgetType() . ': the action has been already instantiated!');
        }
        $this->lookupActionUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject
     */
    protected function getLookupActionUxon() : UxonObject
    {
        if ($this->lookupActionUxon !== null) {
            $uxon = $this->lookupActionUxon;
        } else {
            $uxon = new UxonObject([
                'alias' => 'exface.Core.ShowLookupDialog'
            ]);
        }
        
        if ($uxon->hasProperty('object_alias') === false) {
            $uxon->setProperty('object_alias', $this->getTableObject()->getAliasWithNamespace());
        }
        
        if ($uxon->hasProperty('target_widget_id') ===  false) {
            $uxon->setProperty('target_widget_id', $this->getId());
        }
        
        return $uxon;
    }
    
    /**
     * 
     * @return Button
     */
    public function getLookupButton() : Button
    {
        if ($this->lookupButton === null) {
            /* @var $btn \exface\Core\Widgets\Button */
            $btn = WidgetFactory::createFromUxonInParent($this, new UxonObject([
                'widget_type' => 'Button',
                'object_alias' => $this->getTable()->getMetaObject()->getAliasWithNamespace(),
                'action' => $this->getLookupActionUxon()
            ]));
            $btn->setInputWidget($this);
            $this->lookupButton = $btn;
        }
        return $this->lookupButton;
    }
    
    /**
     * Returns an array of widget links that point to this widget
     * 
     * @return WidgetLinkInterface[]
     */
    public function getValueLinksToThisWidget() : array
    {
        return $this->incomingLinks;
    }
    
    /**
     * 
     * @param WidgetLinkEventInterface $event
     * @return void
     */
    public function handleWidgetLinkedEvent(WidgetLinkEventInterface $event)
    {
        $link = $event->getWidgetLink();
        if ($link->getTargetWidgetId() !== $this->getId()) {
            return;
        }
        
        foreach ($this->incomingLinks as $existing) {
            if ($link->getSourceWidget() === $existing->getSourceWidget() && $link->getTargetColumnId() === $existing->getTargetColumnId()) {
                return;
            }
        }
        
        $this->incomingLinks[] = $event->getWidgetLink();
    }
    
    public function findRelationPathFromObject(MetaObjectInterface $object) : ?MetaRelationPathInterface
    {
        // If the object is the one at the end of the relation represented by the combo,
        // simply return the reverse path
        if ($this->isRelation() && $object->is($this->getMetaObject()->getRelatedObject($this->getAttributeAlias()))) {
            return RelationPathFactory::createFromString($this->getMetaObject(), $this->getAttributeAlias())->reverse();
        }
        
        // If the action is based on the same object as the widget's parent, use the widget's
        // logic to find the relation to the parent. Otherwise try to find a relation to the
        // action's object and throw an error if this fails.
        if ($this->hasParent() && $object->is($this->getParent()->getMetaObject()) && $relPath = $this->getObjectRelationPathFromParent()) {
            return $relPath;
        }
        
        if ($relPath = $object->findRelationPath($this->getMetaObject())) {
            return $relPath;
        }
        
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputSelect::getOptionsDataSheet()
     */
    public function getOptionsDataSheet() : DataSheetInterface
    {
        if ($this->tableDataSheet === null) {
            if ($this->getLazyLoading() === false && $this->isBoundToAttribute() && $this->getAttribute()->isRelation()) {
                $rel = $this->getAttribute()->getRelation();
                $sheet = $this->getTable()->prepareDataSheetToRead(DataSheetFactory::createFromObject($rel->getRightObject()));
                if (null !== ($filters = $this->getFilters())) {
                    $condGroup = ConditionGroupFactory::createForDataSheet($sheet, $filters->getConditionGroup()->getOperator());
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
                $this->tableDataSheet = $sheet;
            } else {
                return parent::getOptionsDataSheet();
            }
        }
        return $this->tableDataSheet;
    }
}