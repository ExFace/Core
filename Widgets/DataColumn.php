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
class DataColumn extends AbstractWidget implements iShowDataColumn, iShowSingleAttribute, iCanBeAligned
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
    
    private $footer = null;
    
    private $widthMax = null;

    /**
     * 
     * @var iHaveValue
     */
    private $cellWidget = null;

    private $editable = null;
    
    private $default_sorting_direction = null;

    private $aggregate_function = null;

    private $include_in_quick_search = false;

    private $cell_styler_script = null;

    private $data_column_name = null;
    
    private $calculationExpr = null;

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
            // If the column is based on an attribute, use it's default editor/display widget to render
            // the cells.
            if ($this->isBoundToAttribute() === true) {
                $attr = $this->getAttribute();
                switch (true) {
                    // If the column is hidden, always use InputHidden widgets to avoid instantiating
                    // complex widgets that would actually never be used. This can still be overridden
                    // manually if a `cell_widget` is explicitly defined. This code here is just used
                    // for autogenerating cell widgets!
                    case $this->isHidden():
                        $uxon = new UxonObject([
                            'attribute_alias' => $this->getAttributeAlias()
                        ]);
                        $this->cellWidget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Display');
                        break;
                    // If the column is editable, use the default editor widget
                    case $this->isEditable() === true:
                        $uxon = $attr->getDefaultEditorUxon();
                        $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
                        $this->cellWidget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Input');
                        break;
                    // Otherwise use the default display widget
                    default:
                        $uxon = $attr->getDefaultDisplayUxon();
                        $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
                        $this->cellWidget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Display');
                        break;
                }
            } else {
                // If the column is not based on an attribute, use generic input/display widgets
                // Again, remember, that this code is only taking care of autogenerating cell
                // widgets. If a widget is ecplicitly defined, it will be used as expected.
                $this->cellWidget = WidgetFactory::create($this->getPage(), ($this->isEditable() ? 'Input' : 'Display'), $this);
                
                // In older versions, formulas could be placed in `attribute_alias`. This is fallback
                // to support these older UXON models. Currently, this should never happen.
                if ($this->getAttributeAlias() && $this->cellWidget instanceof iShowSingleAttribute) {
                    $this->cellWidget->setAttributeAlias($this->getAttributeAlias());
                }
            }
            
            if ($this->isBoundToDataColumn() && $this->cellWidget instanceof iShowDataColumn) {
                $this->cellWidget->setDataColumnName($this->getDataColumnName());
            }
            
            if ($this->cellWidget->getWidth()->isUndefined()) {
                $this->cellWidget->setWidth($this->getWidth());
            }
            
            if ($this->isCalculated()) {
                $expr = $this->getCalculationExpression();
                if (! $expr->isEmpty()) {
                    $this->cellWidget->setValue($expr);
                }
            }
            
            // Some data types require special treatment within a table to make all rows comparable.
            $type = $this->cellWidget->getValueDataType();
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
    public function setEditable($true_or_false)
    {
        $this->editable = BooleanDataType::cast($true_or_false);
        if ($this->editable === true) {
            $this->getDataColumnGroup()->setEditable(true);
        }
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
        try {
            $cellWidget = WidgetFactory::createFromUxon($this->getPage(), UxonObject::fromAnything($uxon_object), $this);
            $cellWidget->setAttributeAlias($this->getAttributeAlias());
            $this->cellWidget = $cellWidget;
            if ($cellWidget instanceof iTakeInput) {
                $this->setEditable($cellWidget->isReadonly() === false);
            } elseif ($cellWidget instanceof Display) {
                $this->setEditable(false);
            }
        } catch (\Throwable $e) {
            throw new WidgetConfigurationError($this, 'Cannot set cell widget for ' . $this->getWidgetType() . '. ' . $e->getMessage() . ' See details below.', null, $e);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::getAlign()
     */
    public function getAlign()
    {
        $type = $this->getDataType();
        if (! $this->isAlignSet()) {
            switch (true) {
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
     * Specifies a facade-specific script to style the column: e.g.
     * JavaScript for HTML-facades.
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
     * Sets the maximum width for a column.
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
}