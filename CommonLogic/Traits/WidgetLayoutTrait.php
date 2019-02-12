<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\Interfaces\Widgets\iLayoutWidgets;

/**
 * Trait for widgets that implemenent the interface iLayoutWidgets.
 *
 * Primarily contains the method getColumnsInGrid which determines the number of columns
 * of the widget based on the number of columns of the parent layout-widget
 *
 * @author SFL
 *        
 */
trait WidgetLayoutTrait {

    private $number_of_columns = null;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getColumnsInGrid()
     */
    public function getColumnsInGrid() : ?int
    {
        return $this->number_of_columns;
    }

    /**
     * How many columns should the layout have if there is enough space.
     * 
     * @uxon-property columns_in_grid
     * @uxon-type integer
     * 
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setColumnsInGrid()
     */
    public function setColumnsInGrid(int $value) : iLayoutWidgets
    {
        $this->number_of_columns = $value;
        return $this;
    }
    
    /**
     * @deprecated use setColumnsInGrid() instead!
     * 
     * @param int $value
     * @return iLayoutWidgets
     */
    public function setNumberOfColumns(int $value) : iLayoutWidgets
    {
        return $this->setColumnsInGrid($value);
    }
}