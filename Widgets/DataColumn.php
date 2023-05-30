<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\WidgetDimensionFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Widgets\iCanBeAligned;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Traits\AttributeCaptionTrait;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Widgets\Parts\DataFooter;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\Widgets\iCanWrapText;

/**
 * The DataColumn represents a column in Data-widgets a DataTable.
 *
 * DataColumns are not always visible as columns. But they are always there, when tabular data is needed
 * for a widget. A DataColumn has a caption (header), an expression for it's contents (an attribute alias,
 * a formula, etc.) and an optional footer, where the contents can be summarized (e.g. summed up).
 *
 * Many widgets support inline-editing. Their columns can be made editable by defining an cell widget
 * for the column. Any input or display widget (Inputs, Combo, Text, ProgressBar etc.) can be used as cell widget.
 *
 * DataColumns can also be made sortable. This is usefull for facade features like changing the sort
 * order via mouse click on the colum header.
 *
 * @method DataColumnGroup getParent()
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataColumn extends AbstractWidget implements iShowDataColumn, iShowSingleAttribute, iCanBeAligned, iCanWrapText
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }
    use AttributeCaptionTrait {
        getCaption as getCaptionViaTrait;
    }
    
    private $attribute_alias = null;
    
    private $attribute = null;

    private $sortable = null;

    private $filterable = null;
    
    private $exportable = null;
    
    private $footer = null;
    
    private $widthMax = null;
    
    private $widthMin = null;

    /**
     * 
     * @var iHaveValue
     */
    private $cellWidget = null;
    
    private $cellWidgetUxon = null;

    private $editable = null;
    
    private $default_sorting_direction = null;

    private $aggregate_function = null;

    private $include_in_quick_search = false;

    private $cell_styler_script = null;

    private $data_column_name = null;
    
    private $calculationExpr = null;
    
    private $nowrap = null;
    
    private $customHint = null;
    
    private $readOnly = false;

    public function getAttributeAlias()
    {
        return $this->attribute_alias;
    }

    /**
     * Makes the column display an attribute of the Data's meta object or a related object.
     *
     * The attribute_alias can contain a relation path and/or an optional aggregator: e.g.
     * "attribute_alias": "ORDER__POSITION__VALUE:SUM"
     *
     * **WARNING:** In earlier versions this field used to accept calculated values like formulas.
     * Don't do this anymore: use `calculation` instead. For the sake of backwards compatibility
     * some calculations will still work in the `attribute_alias` but this fallback is not stable!
     *
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value            
     */
    public function setAttributeAlias($value)
    {
        $this->attribute = null;
        if (Expression::detectCalculation($value)) {
            $this->setCalculation($value);
        } else {
            $this->attribute_alias = $value;
        }
        return $this;
    }
    
    /**
     *
     * @return boolean
     */
    public function isFilterable() : bool
    {
        if ($this->filterable === null) {
            if ($this->isBoundToAttribute() === true && $this->getExpression()->isMetaAttribute() === true && $attr = $this->getAttribute()) {
                $this->filterable = $attr->isFilterable();
            } else {
                $this->filterable = false;
            }
        }
        return $this->filterable;
    }
    
    /**
     * Set to TRUE/FALSE to forcibly enable or disable filtering data via this column.
     *
     * Depending on the facade used, this property will enable or disable header filters for
     * this column and also include this column in all sorts of built-in filter constructors,
     * advanced search, etc.
     * 
     * By default, columns that are bound to attributes inherit the sortable setting of
     * the attribute.
     * 
     * NOTE: you can still add filters over the content of this column in the `filters` list
     * or via custom `configurator` - the `filterable` property only applies to the column
     * and it's representation in the facade.
     *
     * @uxon-property filterable
     * @uxon-type boolean
     *
     * @param bool $trueOrFalse
     * @return DataColumn
     */
    public function setFilterable(bool $trueOrFalse) : DataColumn
    {
        $this->filterable = $trueOrFalse;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function isSortable() : bool
    {
        if ($this->sortable === null) {
            if ($this->isBoundToAttribute() === true && $this->getExpression()->isMetaAttribute() === true && $attr = $this->getAttribute()) {
                $this->sortable = $attr->isSortable();
            } else {
                $this->sortable = false;
            }
        }
        return $this->sortable;
    }

    /**
     * Set to TRUE/FALSE to forcibly enable or disable sorting data via this column.
     *
     * Depending on the facade used, this property will enable or disable sorting when the
     * column header is clicked and include this column in all sorts of sorting configurations.
     * 
     * By default, columns that are bound to attributes inherit the sortable setting of
     * the attribute.
     * 
     * @uxon-property sortable
     * @uxon-type boolean
     *
     * @param bool $trueOrFalse
     * @return DataColumn
     */
    public function setSortable(bool $trueOrFalse) : DataColumn
    {
        $this->sortable = $trueOrFalse;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getFooter() : DataFooter
    {
        if ($this->footer !== null && ! $this->footer instanceof DataFooter) {
            if ($this->getDataWidget() instanceof Data) {
                $footerClass = $this->getDataWidget()->getFooterWidgetPartClass();
            } else {
                $footerClass = '\\' . DataFooter::class;
            }
            if (is_string($this->footer) === true) {
                $this->footer = new $footerClass($this, new UxonObject([
                    'aggregator' => $this->footer
                ]));
            } elseif ($this->footer instanceof UxonObject) {
                $this->footer = new $footerClass($this, $this->footer);
            }
        }
        return $this->footer;
    }
    
    public function hasFooter() : bool
    {
        return $this->footer !== null;
    }

    /**
     * Makes the column display summary information in the footer.
     * 
     * The value can be either 
     * - an aggregator name: SUM, AVG, MIN, MAX, COUNT, COUNT_DISTINCT, LIST or LIST_DISTINCT
     * - or a detailed configuration object like {"aggregator": "SUM"}
     * 
     * Depending on the data widget, the configuration of the footer may take
     * different options. Refer to the documentation of the data widget!
     *
     * @uxon-property footer
     * @uxon-type metamodel:aggregator|\exface\Core\Widgets\Parts\DataFooter
     *
     * @param UxonObject|string $value            
     * @return DataColumn
     */
    public function setFooter($value)
    {
        $this->footer = $value;
        return $this;
    }

    /**
     * Returns the cell widget widget instance for this column
     *
     * @return iHaveValue
     */
    public function getCellWidget()
    {
        if ($this->cellWidget === null) {
            $fallbackWidgetType = null;
            if ($this->cellWidgetUxon !== null) {
                $uxon = $this->cellWidgetUxon;
                $cellWidgetDefinedInUxon = true;
            } else {
                $uxon = new UxonObject();
                $cellWidgetDefinedInUxon = false;
            }
                
            // If the column is based on an attribute, use it's default editor/display widget to render
            // the cells.
            if ($cellWidgetDefinedInUxon === false && $this->isBoundToAttribute() === true) {
                $attr = $this->getAttribute();
                switch (true) {
                    // If the column is hidden, always use InputHidden widgets to avoid instantiating
                    // complex widgets that would actually never be used. This can still be overridden
                    // manually if a `cell_widget` is explicitly defined. This code here is just used
                    // for autogenerating cell widgets!
                    case $this->isHidden():
                        $fallbackWidgetType = 'Display';
                        break;
                    // If the column is editable, use the default editor widget
                    case $this->isEditable() === true:
                        $uxon = $attr->getDefaultEditorUxon();
                        $fallbackWidgetType = 'Input';
                        break;
                    // Otherwise use the default display widget
                    default:
                        $uxon = $attr->getDefaultDisplayUxon();
                        $fallbackWidgetType = 'Display';
                        break;
                }
                
                $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
                
            } else {
                // If the column is not based on an attribute, use generic input/display widgets
                // Again, remember, that this code is only taking care of autogenerating cell
                // widgets. If a widget is ecplicitly defined, it will be used as expected.
                $fallbackWidgetType = $this->isEditable() ? 'Input' : 'Display';
                
                // In older versions, formulas could be placed in `attribute_alias`. This is fallback
                // to support these older UXON models. Currently, this should never happen.
                if ($this->getAttributeAlias() && $this->cellWidget instanceof iShowSingleAttribute) {
                    $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
                }
            }
            
            $cellWidget = WidgetFactory::createFromUxon($this->getPage(), UxonObject::fromAnything($uxon), $this, $fallbackWidgetType);
            $this->cellWidget = $cellWidget;
            // Make sure, the cell widget knows, that it is hidden if the column is hidden
            // Do it only for hidden columns as optional ones can be made visible again
            // TODO not sure, if this is entirely a good idea, because hidden columns could
            // also have hidden_if theoretically. Theses would be sort of optional then.
            // The whole topic arose from the PivotSheet. The pivoting does not work, if the
            // sheet includes a UID column (which it does almost always). The pivot-algorithm
            // expcludes hidden data sheet columns, so the idea was to make the cell widget
            // knows, the column is hidden, thatn it will create a hidden sheet column an that
            // will be excluded from pivot.
            if ($this->isHidden()) {
                $cellWidget->setHidden(true);
            }
            if ($cellWidgetDefinedInUxon === true && ($cellWidget instanceof iTakeInput)) {
                $this->setEditable($cellWidget->isReadonly() === false);
            } elseif ($cellWidget instanceof Display) {
                $this->setEditable(false);
            }            

            if ($this->isBoundToDataColumn() && $cellWidget instanceof iShowDataColumn) {
                $cellWidget->setDataColumnName($this->getDataColumnName());
            }
            
            if ($this->isBoundToAttribute() && $cellWidget instanceof iShowSingleAttribute) {
                $cellWidget->setAttributeAlias($this->getAttributeAlias());
            }
            
            // Set the cell widget width to '100%' if no dimensions are defined for it.
            // We do this so it fills the entire column respecting padding and borders.
            // If instead we set teh width of the cell widget the same as the column (for example '200px')
            // it can lead to values not being shown (for example integer values in UI5 DataTables) because
            // the cell widget is too wide for the column.
            // This is NOT done if only the height is set to allow the widget to support a certain
            // aspect ratio if needed (e.g. for images)
            if ($cellWidget->getWidth()->isUndefined() && $cellWidget->getHeight()->isUndefined()) {
                $cellWidget->setWidth('100%');
            }
            
            if ($this->isCalculated()) {
                $expr = $this->getCalculationExpression();
                if (! $expr->isEmpty()) {
                    $cellWidget->setValue($expr);
                }
            }
            
            // Some data types require special treatment within a table to make all rows comparable.
            $type = $cellWidget->getValueDataType();
            if ($type instanceof NumberDataType) {
                // Numbers with a variable, but limited amount of fraction digits should
                // allways have the same amount of fraction digits in a table to ensure the
                // decimal separator is at the same place in every row.
                if (is_null($type->getPrecisionMin()) && ! is_null($type->getPrecisionMax())) {
                    $type->setPrecisionMin($type->getPrecisionMax());
                } elseif (is_null($type->getPrecisionMax())) {
                    $type->setPrecisionMax(3);
                }
            }
        }
        return $this->cellWidget;
    }

    /**
     * Returns TRUE if the column is editable and FALSE otherwise.
     * 
     * A DataColumn is concidered editable if it is either made editable explicitly
     * (`editable: true`) or belongs to an editable DataColumnGroup and represents
     * an editable attribute or is not bound to an attribute at all.
     *
     * @return boolean
     */
    public function isEditable()
    {
        if ($this->editable !== null) {
            return $this->editable;
        }
        
        $groupIsEditable = $this->getDataColumnGroup()->isEditable();
        if ($groupIsEditable === true) {
            if ($this->isBoundToAttribute()) {
                return $this->getAttribute()->isEditable();
            } else {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Makes this column editable if set to TRUE.
     * 
     * In particular, this will make the default editor of an attribute be used
     * as cell widget (instead of the default display widget).
     * 
     * If not set explicitly, the editable state of the column group will be inherited.
     * 
     * Explicitly definig an active editor as the cell widget will also set the
     * column editable automatically.
     * 
     * @uxon-property editable
     * @uxon-type boolean
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataColumn
     */
    public function setEditable(bool $true_or_false) : DataColumn
    {
        $this->editable = $true_or_false;
        if ($this->editable === true) {
            $this->getDataColumnGroup()->setEditable(true);
        }
        return $this;
    }
    
    /**
     * Return if a data coloumn is exportable or not. If it wasn't set explicitly for
     * the column the default value will be returned.
     *
     * @return bool
     */
    public function isExportable(bool $default = true) : bool
    {
        return $this->exportable ?? $default;
    }
    
    /**
     * Makes this column exportable if set to TRUE.
     *
     * By default all visible columns get exported.
     *
     * @uxon-property exportable
     * @uxon-type boolean
     *
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataColumn
     */
    public function setExportable(bool $true_or_false) : DataColumn
    {
        $this->exportable = $true_or_false;
        return $this;
    }

    /**
     * Defines the widget to be used in each cell of this column.
     *
     * Any value-widget can be used in a column cell (e.g. an Input or a Display).
     * Setting an active input-widget will automatically make the column `editable`.
     * Using a display-widget will, in-turn make it non-editable.
     *
     * Example for a standard display widget with an specific data type:
     * 
     * ```
     * {
     *  "attribute_alias": "MY_ATTRIBUTE",
     *  "cell_widget": {
     *      "widget_type": "Display",
     *      "value_data_type": "exface.Core.Date"
     *  }
     * }
     * 
     * ```
     * 
     * Example for a custom display widget:
     * 
     * ```
     * {
     *  "attribute_alias": "MY_ATTRIBUTE",
     *  "cell_widget": {
     *      "widget_type": "ProgressBar"
     *  }
     * }
     * 
     * ```
     *
     * Example for an editor:
     * 
     * ```
     * {
     *  "attribute_alias": "MY_ATTRIBUTE",
     *  "cell_widget": {
     *      "widget_type": "InputNumber"
     *  }
     * }
     * 
     * ```
     *
     * @uxon-property cell_widget
     * @uxon-type \exface\Core\Widgets\Value
     * @uxon-template {"widget_type": ""}
     *
     * @param UxonObject $uxon_object            
     * @return DataColumn
     */
    public function setCellWidget(UxonObject $uxon_object)
    {
        if ($this->cellWidget !== null) {
            throw new WidgetConfigurationError($this, 'Cannot set cell_widget property of a ' . $this->getWidgetType() . ': the cell widget was already initialized!');
        }
        $this->cellWidgetUxon = $uxon_object;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::getAlign()
     */
    public function getAlign()
    {
        $type = $this->getDataType();
        if (! $this->isAlignSet()) {
            switch (true) {
                case ($this->getCellWidget() instanceof iCanBeAligned) && EXF_ALIGN_DEFAULT !== $cellAlign = $this->getCellWidget()->getAlign():
                    $this->setAlign($cellAlign);
                    break;
                case $type instanceof NumberDataType && ! ($type instanceof EnumDataTypeInterface):
                case $type instanceof DateDataType:
                    $this->setAlign(EXF_ALIGN_OPPOSITE);
                    break;
                case $type instanceof BooleanDataType:
                    $this->setAlign(EXF_ALIGN_CENTER);
                    break;
                default:
                    $this->setAlign(EXF_ALIGN_DEFAULT);
                    break;
            }
        }
        return $this->getAlignDefault();
    }

    /**
     * Returns the data type of the column. 
     * 
     * The column's data_type can either be set explicitly by UXON, or is derived from the shown meta attribute.
     * If there is neither an attribute bound to the column, nor an explicit data_type, the base data type
     * is returned.
     *
     * @return DataTypeInterface
     */
    public function getDataType()
    {
        return $this->getCellWidget()->getValueDataType();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::getAttribute()
     */
    public function getAttribute() : ?MetaAttributeInterface
    {
        if ($this->attribute === null) {
            try {
                $attr = $this->getMetaObject()->getAttribute($this->getAttributeAlias());
                $this->attribute = $attr;
            } catch (MetaAttributeNotFoundError $e) {
                if ($this->getExpression()->isFormula()) {
                    $this->attribute = $this->getMetaObject()->getAttribute($this->getExpression()->getRequiredAttributes()[0]);
                } else {
                    throw new WidgetPropertyInvalidValueError($this, 'Attribute "' . $this->getAttributeAlias() . '" specified for widget ' . $this->getWidgetType() . ' not found for the widget\'s object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!', null, $e);
                }
            }
        }
        return $this->attribute;
    }

    public function getAggregator() : ?AggregatorInterface
    {
        if ($this->aggregate_function === null) {
            if ($this->isCalculated()) {
                return null;
            }
            if (! $this->isBoundToAttribute()) {
                return null;
            }
            if ($aggr = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $this->getAttributeAlias())) {
                $this->setAggregator($aggr);
            }
        }
        return $this->aggregate_function;
    }
    
    public function hasAggregator() : bool
    {
        return $this->getAggregator() !== null;
    }

    /**
     * 
     * @param AggregatorInterface|string $aggregator_or_string
     * @return \exface\Core\Widgets\DataColumn
     */
    public function setAggregator($aggregator_or_string)
    {
        if ($aggregator_or_string instanceof AggregatorInterface){
            $aggregator = $aggregator_or_string;
        } else {
            $aggregator = new Aggregator($this->getWorkbench(), $aggregator_or_string);
        }
        $this->aggregate_function = $aggregator;
        return $this;
    }

    public function getIncludeInQuickSearch()
    {
        return $this->include_in_quick_search;
    }

    /**
     * Set to TRUE to make the quick-search include this column (if the widget support quick search).
     *
     * @uxon-property include_in_quick_search
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return DataColumn
     */
    public function setIncludeInQuickSearch($value)
    {
        $this->include_in_quick_search = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getCellWidget();
    }

    /**
     *
     * @return string
     */
    public function getCellStylerScript()
    {
        return $this->cell_styler_script;
    }

    /**
     * Specifies a facade-specific script to style the column: e.g. JavaScript for HTML-facades.
     * 
     * You can use the following placeholders:
     * 
     * - `[#table_id#]` - id of the facade element of the table containing this column
     *
     * The exact effect of the cell_styler_script depends solemly on the implementation of the widget
     * in the specific facade.
     *
     * @uxon-property cell_styler_script
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Widgets\DataColumn
     */
    public function setCellStylerScript($value)
    {
        $this->cell_styler_script = $value;
        return $this;
    }

    /**
     *
     * @return ExpressionInterface
     */
    public function getExpression()
    {
        $exface = $this->getWorkbench();
        return ExpressionFactory::createFromString($exface, $this->getAttributeAlias());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::getDataColumnName()
     */
    public function getDataColumnName()
    {
        if ($this->data_column_name === null) {
            switch (true) {
                case $alias = $this->getAttributeAlias():
                    $this->data_column_name = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($alias);
                    break;
                case $this->isCalculated() && ! $this->getCalculationExpression()->isEmpty() && ! $this->getCalculationExpression()->isReference():
                    $this->data_column_name = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getCalculationExpression()->toString());
                    break;
            }
        }
        return $this->data_column_name ?? '';
    }

    /**
     * Set the name of the data column explicitly - only needed for non-attribute columns.
     * 
     * Internally all data is stored in excel-like sheets, where every column has a unique name.
     * These data sheets are passed around between widgets, actions, data sources, etc. 
     * 
     * If a data sheet represents a meta object, it's columns are attributes. Column names are
     * simply attribute aliases in most cases: this is why specifying `attribute_alias` is mostly
     * enough for a column in a data-wiget.
     * 
     * However, there are also cases, when the desired value does not represent an attribute: for
     * example, if you need to show/edit some action-parameter or display a calculated value.
     * If that value still needs to be handled by the server, a `data_column_name` must be set
     * explicitly, since there is no `attribute_alias`.
     * 
     * @uxon-property data_column_name
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::setDataColumnName()
     */
    public function setDataColumnName($value)
    {
        $this->data_column_name = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO add properties specific to this widget here
        if ($this->isBoundToAttribute()) {
            $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
        }
        if ($this->isCalculated()) {
            $uxon->setProperty('calculation', $this->getCalculationExpression()->toString());
        }
        return $uxon;
    }
    
    /**
     * 
     * @return \exface\Core\DataTypes\SortingDirectionsDataType
     */
    public function getDefaultSortingDirection()
    {
        if(is_null($this->default_sorting_direction)){
            return $this->getDataType()->getDefaultSortingDirection();
        }
        return $this->default_sorting_direction;
    }
    
    /**
     * Defines the default sorting direction for this column: ASC or DESC.
     * 
     * The default direction is used if sorting the column without a
     * direction being explicitly specified: e.g. when clicking on a
     * sortable table header.
     * 
     * If not set, the default sorting direction of the attribute will
     * be used for columns representing attributes or the default sorting
     * direction of the data type of the columns expression.
     * 
     * @uxon-property default_sorting_direction
     * @uxon-type [ASC,DESC]
     * 
     * @param SortingDirectionsDataType|string $asc_or_desc
     */
    public function setDefaultSortingDirection($asc_or_desc)
    {
        if ($asc_or_desc instanceof SortingDirectionsDataType){
            // Everything OK. Just proceed
        } elseif (SortingDirectionsDataType::isValidValue(strtoupper($asc_or_desc))){
            $asc_or_desc = DataTypeFactory::createFromPrototype($this->getWorkbench(), SortingDirectionsDataType::class)->withValue(strtoupper($asc_or_desc));
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $asc_or_desc . '" for default sorting direction in data column: use ASC or DESC');
        }
        $this->default_sorting_direction = $asc_or_desc;
        return $this;
    }

    /**
     * Returns TRUE if this widget references a meta attribute and FALSE otherwise.
     *
     * @return boolean
     */
    public function isBoundToAttribute() : bool
    {
        $alias = $this->getAttributeAlias();
        return $alias !== null 
            && $alias !== '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::isBoundToDataColumn()
     */
    public function isBoundToDataColumn() : bool
    {
        return $this->getDataColumnName() !== '';
    }
    
    /**
     * 
     * @return \exface\Core\Widgets\DataColumnGroup
     */
    public function getDataColumnGroup()
    {
        return $this->getParent();
    }
    
    /**
     * 
     * @return iHaveColumns
     */
    public function getDataWidget() : iHaveColumns
    {
        $parent = $this->getParent();
        if ($parent instanceof DataColumnGroup) {
            return $parent->getDataWidget();
        }
        if ($parent instanceof Data) {
            return $parent;
        }
        
        throw new WidgetLogicError($this, 'Invalid data widget structure: cannot find data widget for column "' . $this->getId() . "'");
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getWidth()
     */
    public function getWidth() : WidgetDimension
    {
        $ownWidth = parent::getWidth();
        if ($ownWidth->isUndefined()) {
            $cellWidth = $this->getCellWidget()->getWidth();
            if (! $cellWidth->isUndefined() && $cellWidth->getValue() !== '100%') {
                $this->setWidth($cellWidth->getValue());
                $this->getCellWidget()->setWidth('100%');
            }
        }
        return parent::getWidth();
    }
    
    /**
     * 
     * @return WidgetDimension
     */
    public function getWidthMax() : WidgetDimension
    {
        if ($this->widthMax === null) {
            $this->widthMax = WidgetDimensionFactory::createEmpty($this->getWorkbench());
        }
        return $this->widthMax;
    }
    
    /**
     * Sets the minimun width for a column.
     * 
     * **NOTE:** this property may not have effect on some facades: try it out first!
     * 
     * This property takes the same values as "width" or "height", but unlike "width" it
     * will allow the column to be wider, but never smaller, than the given value. "Width"
     * on the other hand, will make the column have a fixed width.
     * 
     * @uxon-property width_min
     * @uxon-type string
     * 
     * @param string|WidgetDimension $stringOrDimension
     * @return DataColumn
     */
    public function setWidthMin($stringOrDimension) : DataColumn
    {
        $this->widthMin = WidgetDimensionFactory::createFromAnything($this->getWorkbench(), $stringOrDimension);
        return $this;
    }
    
    /**
     *
     * @return WidgetDimension
     */
    public function getWidthMin() : WidgetDimension
    {
        if ($this->widthMin === null) {
            $this->widthMin = WidgetDimensionFactory::createEmpty($this->getWorkbench());
        }
        return $this->widthMin;
    }
    
    /**
     * Sets the maximum width for a column.
     *
     * **NOTE:** this property may not have effect on some facades: try it out first!
     * 
     * This property takes the same values as "width" or "height", but unlike "width" it
     * will allow the column to be smaller, but never wider, than the given value. "Width"
     * on the other hand, will make the column have a fixed width.
     *
     * @uxon-property width_max
     * @uxon-type string
     *
     * @param string|WidgetDimension $stringOrDimension
     * @return DataColumn
     */
    public function setWidthMax($stringOrDimension) : DataColumn
    {
        $this->widthMax = WidgetDimensionFactory::createFromAnything($this->getWorkbench(), $stringOrDimension);
        return $this;
    }
    
    /**
     * Place an expression here to calculate values for every cell of the column.
     * 
     * Examples:
     * 
     * - `=0` will make all cells display "0"
     * - `=NOW()` will place the current date in every cell
     * - `=some_widget_id` will place the current value of the widget with the given id in the cells
     * 
     * NOTE: `calculation` can be used used without an `attribute_alias` producing a calculated column,
     * that does not affect subsequent actions or in addition to an `attribute_alias`, which will place
     * the calculated value in the attribute's column for further processing.
     * 
     * @uxon-property calculation
     * @uxon-type metamodel:expression
     * 
     * @param string $expression
     * @return DataColumn
     */
    public function setCalculation(string $expression) : DataColumn
    {
        $this->calculationExpr = ExpressionFactory::createForObject($this->getMetaObject(), $expression);
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isCalculated() : bool
    {
        return $this->calculationExpr !== null;
    }
    
    /**
     * 
     * @return ExpressionInterface|NULL
     */
    public function getCalculationExpression() : ?ExpressionInterface
    {
        return $this->calculationExpr;
    }
    
    /**
     * @deprecated use setCalculation() instead!
     * @param string $value
     * @return DataColumn
     */
    protected function setValue($value) : DataColumn
    {
        return $this->setCalculation($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see AttributeCaptionTrait::getCaption()
     */
    public function getCaption() : ?string
    {
        $caption = $this->getCaptionViaTrait();
        
        // If there is no caption and its calculated column, try to derive a caption from
        // the calculation expression.
        if ($caption === null && $this->isCalculated()) {
            $expr = $this->getCalculationExpression();
            
            // If it's a data-based (non-static) formula, use the first attribute's name
            // if available
            if (! $expr->isStatic()) {
                $firstAlias = $expr->getRequiredAttributes()[0];
                if ($firstAlias && $this->getMetaObject()->hasAttribute($firstAlias)) {
                    $attr = $this->getMetaObject()->getAttribute($firstAlias);
                    $caption = $attr->getName();
                }
            }
            
            // Otherwise just use the entire expression as caption
            if ($caption === null) {
                $caption = $expr->toString();
            }
        }
        return $caption;
    }
    
    /**
     * 
     * @see iCanWrapText::getNowrap()
     */
    public function getNowrap() : bool
    {
        return $this->nowrap ?? ($this->getDataWidget() instanceof iCanWrapText ? $this->getDataWidget()->getNowrap() : true);
    }
    
    /**
     * Set to FALSE to enable text wrapping in this column only.
     * 
     * NOTE: This may not work for all widgets/facades. Just try it out.
     *
     * @uxon-property nowrap
     * @uxon-type boolean
     *
     * @param boolean $value
     * @return \exface\Core\Widgets\DataTable
     */
    public function setNowrap(bool $value) : iCanWrapText
    {
        $this->nowrap = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setHint()
     */
    public function setHint($value)
    {
        $this->customHint = $this->evaluatePropertyExpression($value);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getHint()
     */
    public function getHint()
    {
        return $this->customHint ?? $this->getCellWidget()->getHint();
    }
    
    /**
     * 
     * @return bool
     */
    public function isReadonly() : bool
    {
        return $this->readOnly;
    }

    /**
     * Set to TRUE to exclude the data of this column from action input
     * 
     * @uxon-property readonly
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return DataColumn
     */
    public function setReadonly(bool $value) : DataColumn
    {
        $this->readOnly = $value;
        return $this;
    }
}