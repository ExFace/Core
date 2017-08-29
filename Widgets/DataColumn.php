<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Traits\iCanBeAlignedTrait;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * The DataColumn represents a column in Data-widgets a DataTable.
 *
 * DataColumns are not always visible as columns. But they are always there, when tabular data is needed
 * for a widget. A DataColumn has a caption (header), an expression for it's contents (an attribute alias,
 * a formula, etc.) and an optional footer, where the contents can be summarized (e.g. summed up).
 *
 * Many widgets support inline-editing. Their columns can be made editable by defining an editor widget
 * for the column. Any input widget (Inputs, Combos, etc.) can be used as an editor.
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

    private $editor = null;

    private $editable = false;
    
    private $default_sorting_direction = null;

    private $aggregate_function = null;

    private $data_type = null;

    private $include_in_quick_search = false;

    private $cell_styler_script = null;

    private $size = null;

    private $style = null;

    private $data_column_name = null;

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
     * WARNING: This field currently also accepts formulas an string. However, this feature
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
        $this->sortable = \exface\Core\DataTypes\BooleanDataType::parse($value);
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
     * Returns the editor widget instance for this column
     *
     * @return WidgetInterface
     */
    public function getEditor()
    {
        return $this->editor;
    }

    /**
     * Returns TRUE if the column is editable and FALSE otherwise
     *
     * @return boolean
     */
    public function isEditable()
    {
        return $this->editable;
    }

    /**
     * Defines an editor widget for the column making each row in it editable.
     *
     * The editor is a UXON widget description object. Any input widget (Input, Combo, etc.)
     * can be used. An editor can even be placed on non-attribute columns. This is very
     * usefull if the action, that will receive the data, expects some input not related
     * to the meta object.
     *
     * Example:
     * {
     * "attribute_alias": "MY_ATTRIBUTE",
     * "editor": {
     * "widget_type": "InputNumber"
     * }
     * }
     *
     * @uxon-property editor
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     *
     * @param UxonObject $uxon_object            
     * @return boolean
     */
    public function setEditor($uxon_object)
    {
        // TODO Fetch the default editor from data type. Probably need a editable attribute for the DataColumn,
        // wich would be the easiest way to set it editable and the editor would be optional then.
        $page = $this->getPage();
        $editor = WidgetFactory::createFromUxon($page, UxonObject::fromAnything($uxon_object), $this);
        if ($uxon_object->widget_type && $editor) {
            $editor->setAttributeAlias($this->getAttributeAlias());
            $this->editor = $editor;
            $this->editable = true;
        } else {
            return false;
        }
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
            if ($this->getDataType()->is(EXF_DATA_TYPE_NUMBER) || $this->getDataType()->is(EXF_DATA_TYPE_PRICE) || $this->getDataType()->is(EXF_DATA_TYPE_DATE)) {
                $this->setAlign(EXF_ALIGN_OPPOSITE);
            } elseif ($this->getDataType()->is(EXF_DATA_TYPE_BOOLEAN)) {
                $this->setAlign(EXF_ALIGN_CENTER);
            } else {
                $this->setAlign(EXF_ALIGN_DEFAULT);
            }
        }
        return $this->getAlignDefault();
    }

    /**
     * Returns the data type of the column as a constant (e.g.
     * EXF_DATA_TYPE_NUMBER). The column's
     * data_type can either be set explicitly by UXON, or is derived from the shown meta attribute.
     * If there is neither an attribute bound to the column, nor an explicit data_type EXF_DATA_TYPE_STRING
     * is returned.
     *
     * @return AbstractDataType
     */
    public function getDataType()
    {
        if ($this->data_type) {
            return $this->data_type;
        } elseif ($attr = $this->getAttribute()) {
            return $attr->getDataType();
        } else {
            $exface = $this->getWorkbench();
            return DataTypeFactory::createFromAlias($exface, EXF_DATA_TYPE_STRING);
        }
    }

    public function setDataType($exface_data_type)
    {
        $this->data_type = $exface_data_type;
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
            $attr = $this->getMetaObject()->getAttribute($this->getAttributeAlias());
            return $attr;
        } catch (MetaAttributeNotFoundError $e) {
            return false;
        }
    }

    public function getAggregateFunction()
    {
        return $this->aggregate_function;
    }

    public function setAggregateFunction($value)
    {
        $this->aggregate_function = $value;
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
        $this->include_in_quick_search = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getChildren()
    {
        if ($this->isEditable() && $editor = $this->getEditor()) {
            return array(
                $editor
            );
        } else {
            return array();
        }
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
        $this->style = $value;
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
            try {
                $attr = $this->getAttribute();
            } catch (MetaAttributeNotFoundError $e) {
                if ($this->getExpression()->isFormula()) {
                    $attr = $this->getMetaObject()->getAttribute($this->getExpression()->getRequiredAttributes()[0]);
                }
            }
            
            if ($attr) {
                $this->setCaption($attr->getName());
            }
        }
        return parent::getCaption();
    }

    /**
     *
     * @return Expression
     */
    public function getExpression()
    {
        $exface = $this->getWorkbench();
        return ExpressionFactory::createFromString($exface, $this->getAttributeAlias());
    }

    public function getDataColumnName()
    {
        if (is_null($this->data_column_name)) {
            $this->data_column_name = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getAttributeAlias());
        }
        return $this->data_column_name;
    }

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
     * @return \exface\Core\CommonLogic\Constants\SortingDirections
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
     * @param SortingDirections|string $asc_or_desc
     */
    public function setDefaultSortingDirection($asc_or_desc)
    {
        if ($asc_or_desc instanceof SortingDirections){
            // Everything OK. Just proceed
        } elseif (SortingDirections::isValid(strtolower($asc_or_desc))){
            $asc_or_desc = new SortingDirections(strtolower($asc_or_desc));
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $asc_or_desc . '" for default sorting direction in data column: use ASC or DESC');
        }
        $this->default_sorting_direction = $asc_or_desc;
        return $this;
    }
}
?>