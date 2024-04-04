<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\CommonLogic\UxonObject;

/**
 * A responsive table column collapses it's columns into vertical lists on small screens.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataColumnResponsive extends DataColumn
{
    private $visibilityOnSmartphone = null;
    
    private $visibilityOnTablet = null;
    
    private $visibilityOnDesktop = null;
    
    private $hideCaptionOnSmartphone = null;
    
    /**
     * 
     * @return WidgetVisibilityDataType
     */
    public function getVisibilityOnSmartphone() : WidgetVisibilityDataType
    {
        if ($this->visibilityOnSmartphone === null) {
            $v = $this->getVisibility();
            if (! ($v instanceof WidgetVisibilityDataType)) {
                $v = WidgetVisibilityDataType::fromValue($this->getWorkbench(), $v);
            }
            $this->visibilityOnSmartphone = $v;
        }
        return $this->visibilityOnSmartphone;
    }

    /**
     * 
     * @param WidgetVisibilityDataType|string $visibilityOnSmartphone
     * @return DataColumnResponsive
     */
    public function setVisibilityOnSmartphone($visibility) : DataColumnResponsive
    {
        $this->visibilityOnSmartphone = $visibility;
        return $this;
    }

    /**
     * 
     * @return WidgetVisibilityDataType
     */
    public function getVisibilityOnTablet() : WidgetVisibilityDataType
    {
        if ($this->visibilityOnTablet === null) {
            $v = $this->getVisibility();
            if (! ($v instanceof WidgetVisibilityDataType)) {
                $v = WidgetVisibilityDataType::fromValue($this->getWorkbench(), $v);
            }
            $this->visibilityOnTablet = $v;
        }
        return $this->visibilityOnTablet;
    }

    /**
     * 
     * @param WidgetVisibilityDataType|string $visibilityOnTablet
     * @return DataColumnResponsive
     */
    public function setVisibilityOnTablet($visibility) : DataColumnResponsive
    {
        $this->visibilityOnTablet = $visibility;
        return $this;
    }

    /**
     * 
     * @return WidgetVisibilityDataType
     */
    public function getVisibilityOnDesktop() : WidgetVisibilityDataType
    {
        if ($this->visibilityOnDesktop === null) {
            $v = $this->getVisibility();
            if (! ($v instanceof WidgetVisibilityDataType)) {
                $v = WidgetVisibilityDataType::fromValue($this->getWorkbench(), $v);
            }
            $this->visibilityOnDesktop = $v;
        }
        return $this->visibilityOnDesktop;
    }

    /**
     * visibilityOnDesktop
     * @param WidgetVisibilityDataType|string $visibility
     * @return DataColumnResponsive
     */
    public function setVisibilityOnDesktop($visibility) : DataColumnResponsive
    {
        $this->visibilityOnDesktop = $visibility;
        return $this;
    }

    /**
     * 
     * @return bool|NULL
     */
    public function getHideCaptionOnSmartphone() : ?bool
    {
        return $this->hideCaptionOnSmartphone;
    }
    
    /**
     * Set to TRUE to hide caption on small screens only
     * 
     * @uxon-property hide_caption_on_smartphone
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return DataColumnResponsive
     */
    public function setHideCaptionOnSmartphone(bool $value) : DataColumnResponsive
    {
        $this->hideCaptionOnSmartphone = $value;
        return $this;
    }
    
    /**
     * Defines the columns to display: each element of the array can be a `DataColumn` or a `DataColumnGroup` widget.
     *
     * To create a column showing an attribute of the Data's meta object, it is sufficient to only set
     * the `attribute_alias` for each column object. Other properties like caption, align, editor, etc.
     * are optional. If not set, they will be determined from the properties of the attribute.
     *
     * The widget type (`DataColumn` or `DataColumnGroup`) can be omitted: it can be determined automatically:
     * E.g. adding `{"attribute_group_alias": "~VISIBLE"}` as a column is enough to generate a column group
     * with all visible attributes of the object.
     *
     * Column groups with captions will produce grouped columns with mutual headings (s. example below).
     *
     * Example:
     *
     * ```
     *  {
     *      "columns": [
     *          {"attribute_alias": "PRODUCT__LABEL", "caption": "Product"},
     *          {"attribute_alias": "PRODUCT__BRAND__LABEL"},
     *          {"caption": "Sales", "columns": [
     *              {"attribute_alias": "QUANTITY:SUM", "caption": "Qty."},
     *              {"attribute_alias": "VALUE:SUM", "caption": "Sum"}
     *          ]}
     *      }
     *  }
     *
     * ```
     *
     * @uxon-property columns
     * @uxon-type \exface\Core\Widgets\DataColumnResponsive[]|\exface\Core\Widgets\DataColumnGroup[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @see \exface\Core\Widgets\Traits\iHaveColumnsAndColumnGroupsTrait::setColumns()
     */
    public function setColumns(UxonObject $columns) : iHaveColumns
    {
        return parent::setColumns($columns);
    }
}