<?php
namespace exface\Core\CommonLogic\Traits;

/**
 * Trait for widgets that implemenent the interface iLayoutWidgets.
 *
 * Primarily contains the method getNumberOfColumns which determines the number of columns
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
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getNumberOfColumns()
     */
    public function getNumberOfColumns()
    {
        return $this->number_of_columns;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setNumberOfColumns()
     */
    public function setNumberOfColumns($value)
    {
        $this->number_of_columns = intval($value);
        return $this;
    }
}