<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\PriceDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\TextStylesDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

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
 * DataColumns can also be made sortable. This is usefull for template features like changing the sort
 * order via mouse click on the colum header.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataColumn extends AbstractWidget implements iShowDataColumn, iShowSingleAttribute, iShowText
{
    use iCanBeAlignedTrait {
        getAlign as getAlignDefault;
    }
    
    private $attribute_alias = null;

    private $sortable = true;

    private $footer = false;

    private $fixed_width = false;

    private $cellWidget = null;

    private $editable = null;
    
    private $default_sorting_direction = null;

    private $aggregate_function = null;

    private $data_type = null;

    private $include_in_quick_search = false;

    private $cell_styler_script = null;

    private $size = null;

    private $style = null;
    
    private $color = null;

    private $data_column_name = null;
    
    private $disableFormatters = false;

    public function hasFooter()
    {
        if (! empty($this->footer))
            return true;
        else
            return false;
    }

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
     * WARNING: This field currently also accepts formulas and strings. However, this feature
     * is not quite stable and it is not guaranteed for it to remain in future (it is more
     * likely that formulas and widget links will be moved to a new generalized property of the
     * DataColumn - presumabely "expression")
     *
     * @uxon-property attribute_alias
     * @uxon-type string
     *
     * @param string $value            
     */
    public function setAttributeAlias($value)
    {
        $this->attribute_alias = $value;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getSortable()
    {
        if (is_null($this->sortable)) {
            if ($attr = $this->getAttribute()) {
                $this->sortable = $attr->isSortable();
            }
        }
        return $this->sortable;
    }

    /**
     * Set to FALSE to disable sorting data via this column.
     *
     * If the column represents a meta attribute, the sortable property of that attribute will be used.
     *
     * @uxon-property sortable
     * @uxon-type boolean
     *
     * @param
     *            boolean
     */
    public function setSortable($value)
    {
        $this->sortable = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getFooter()
    {
        return $this->footer;
    }

    /**
     * Makes the column display summary information in the footer.
     * The value can be SUM, AVG, MIN, MAX, LIST and LIST_DISTINCT.
     *
     * @uxon-property footer
     * @uxon-type string
     *
     * @param string $value            
     * @return DataColumn
     */
    public function setFooter($value)
    {
        $this->footer = $value;
        return $this;
    }

    public function getFixedWidth()
    {
        return $this->fixed_width;
    }

    public function setFixedWidth($value)
    {
        $this->fixed_width = $value;
        return $this;
    }

    /**
     * Returns the cell widget widget instance for this column
     *
     * @return iHaveValue
     */
    public function getCellWidget()
    {
        if (is_null($this->cellWidget)) {
            if ($this->editable === true) {
                // TODO
            } else {
                $this->cellWidget = WidgetFactory::create($this->getPage(), 'Display', $this);
            }
            $this->cellWidget
                ->setAttributeAlias($this->getAttributeAlias())
                ->setHideCaption(true);
        }
        return $this->cellWidget;
    }

    /**
     * Returns TRUE if the column is editable and FALSE otherwise
     *
     * @return boolean
     */
    public function isEditable()
    {
        if (is_null($this->editable)) {
            return $this->getCellWidget() instanceof iTakeInput ? true : false;
        } 
        return $this->editable;
    }
    
    /**
     * 
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
        return $this;
    }

    /**
     * Defines an cell widget widget for the column making each row in it editable.
     *
     * The cell widget is a UXON widget description object. Any input widget (Input, Combo, etc.)
     * can be used. An cell widget can even be placed on non-attribute columns. This is very
     * usefull if the action, that will receive the data, expects some input not related
     * to the meta object.
     *
     * Example:
     * {
     *  "attribute_alias": "MY_ATTRIBUTE",
     *  "cell_widget": {
     *      "widget_type": "InputNumber"
     *  }
     * }
     *
     * @uxon-property cell_widget
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     *
     * @param UxonObject $uxon_object            
     * @return DataColumn
     */
    public function setCellWidget(UxonObject $uxon_object)
    {
        // TODO Fetch the default cell widget from data type. Probably need a editable attribute for the DataColumn,
        // wich would be the easiest way to set it editable and the cell widget would be optional then.
        try {
            $cellWidget = WidgetFactory::createFromUxon($this->getPage(), UxonObject::fromAnything($uxon_object), $this);
            $cellWidget->setAttributeAlias($this->getAttributeAlias());
            $this->cellWidget = $cellWidget;
            $this->editable = true;
        } catch (\Throwable $e) {
            throw new WidgetConfigurationError($this, 'Cannot set cell widget for ' . $this->getWidgetType() . ': see details below!', null, $e);
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
        if (! $this->isAlignSet()) {
            if ($this->getDataType() instanceof NumberDataType || $this->getDataType() instanceof PriceDataType || $this->getDataType() instanceof DateDataType) {
                $this->setAlign(EXF_ALIGN_OPPOSITE);
            } elseif ($this->getDataType() instanceof BooleanDataType) {
                $this->setAlign(EXF_ALIGN_CENTER);
            } else {
                $this->setAlign(EXF_ALIGN_DEFAULT);
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
     * @param DataTypeInterface|string $data_type_or_string
     * @return \exface\Core\Widgets\DataColumn
     */
    public function setDataType($data_type_or_string)
    {
        // TODO check if the cell widget really has a data type setter
        $this->getCellWidget()->setValueDataType($data_type_or_string);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::getAttribute()
     */
    function getAttribute()
    {
        try {
            return $this->getMetaObject()->getAttribute($this->getAttributeAlias());
        } catch (MetaAttributeNotFoundError $e) {
            if ($this->getExpression()->isFormula()) {
                return $this->getMetaObject()->getAttribute($this->getExpression()->getRequiredAttributes()[0]);
            }
            throw new WidgetPropertyInvalidValueError($this, 'Attribute "' . $this->getAttributeAlias() . '" specified for widget ' . $this->getWidgetType() . ' not found for the widget\'s object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!', null, $e);
        }
    }

    public function getAggregator()
    {
        return $this->aggregate_function;
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

    public function getChildren()
    {
        return [$this->getCellWidget()];
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
     * Specifies a template-specific script to style the column: e.g.
     * JavaScript for HTML-templates.
     *
     * The exact effect of the cell_styler_script depends solemly on the implementation of the widget
     * in the specific template.
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

    public function getSize()
    {
        return $this->size;
    }

    /**
     * Sets the font size for the values in this column: BIG, NORMAL or SMALL.
     *
     * @uxon-property size
     * @uxon-type string
     *
     * @see \exface\Core\Interfaces\Widgets\iShowText::setSize()
     */
    public function setSize($value)
    {
        $this->size = $value;
        return $this;
    }

    public function getStyle()
    {
        return $this->style;
    }

    /**
     * Sets the font style for the values in this column: NORMAL, BOLD, ITALIC, STRIKETHROUGH, UNDERLINE
     *
     * @uxon-property style
     * @uxon-type string
     *
     * @see \exface\Core\Interfaces\Widgets\iShowText::setStyle()
     */
    public function setStyle($value)
    {
        $this->style = TextStylesDataType::cast(strtoupper($value));
        return $this;
    }

    /**
     *
     * {@inheritdoc} By default the caption of a DataColumn will be set to the name of the displayed attribute or the name of the first attribute
     *               required for the formula (if the contents of the column is a formula).
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption()
    {
        if (! parent::getCaption()) {
            $attr = $this->getAttribute();
            if ($attr) {
                $this->setCaption($attr->getName());
            }
        }
        return parent::getCaption();
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
        if (is_null($this->data_column_name)) {
            $this->data_column_name = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getAttributeAlias());
        }
        return $this->data_column_name;
    }

    /**
     * 
     * {@inheritDoc}
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
            $asc_or_desc = new SortingDirectionsDataType($this->getWorkbench(), strtoupper($asc_or_desc));
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $asc_or_desc . '" for default sorting direction in data column: use ASC or DESC');
        }
        $this->default_sorting_direction = $asc_or_desc;
        return $this;
    }
    
    /**
     * Returns TRUE if formatters are disabled for this column and FALSE otherwise.
     * @return boolean
     */
    public function getDisableFormatters()
    {
        return $this->disableFormatters;
    }

    /**
     * Set to TRUE to disable all formatters for this column (including data type specific ones!) - FALSE by default.
     * 
     * @uxon-property disable_formatters
     * @uxon-type boolean
     * 
     * @param boolean $disableFormatters
     * @return DataColumn
     */
    public function setDisableFormatters($disableFormatters)
    {
        $this->disableFormatters = $disableFormatters;
        return $this;
    }
    /**
     * 
     * @return string $color
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Sets the color to use for this data column (a CSS color code or anything else supported by your template).
     * 
     * @uxon-property color
     * @uxon-type string
     * 
     * @param string $color
     * @return DataColumn
     */
    public function setColor($color)
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Returns TRUE if this widget references a meta attribute and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasAttributeReference()
    {
        return $this->getAttributeAlias() ? true : false;
    }
}
?>