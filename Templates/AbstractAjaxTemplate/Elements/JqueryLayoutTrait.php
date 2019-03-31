<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Widgets\iLayoutWidgets;

/**
 * This trait helps build grid-widgets.
 * 
 * In particular, it can be used to determine the number of columns in the grid.
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryLayoutTrait {

    private $number_of_columns = null;
    
    private $searched_for_number_of_columns = false;
    
    /**
     * Returns an inline JavaScript-Snippet to create/refresh the layout of the widget.
     *
     * @return string
     */
    abstract public function buildJsLayouter() : string;
    
    /**
     * Determines the number of columns of a layout-widget, based on the width of widget, the
     * number of columns of the parent layout-widget and the default number of columns of the
     * widget.
     *
     * @return number
     */
    public function getNumberOfColumns() : int
    {
        if (! $this->searched_for_number_of_columns) {
            $widget = $this->getWidget();
            if ($widget instanceof iLayoutWidgets) {
                if (! is_null($widget->getColumnsInGrid())) {
                    $this->number_of_columns = $widget->getColumnsInGrid();
                } elseif ($widget->getWidth()->isRelative() && ! $widget->getWidth()->isMax()) {
                    $width = $widget->getWidth()->getValue();
                    if ($width < 1) {
                        $width = 1;
                    }
                    $this->number_of_columns = $width;
                } else {
                    if ($this->inheritsNumberOfColumns()) {
                        if ($layoutWidget = $widget->getParentByType('exface\\Core\\Interfaces\\Widgets\\iLayoutWidgets')) {
                            $parentElement = $this->getFacade()->getElement($layoutWidget);
                            if (true === method_exists($parentElement, 'getNumberOfColumns')) {
                                $parentColumnNumber = $parentElement->getNumberOfColumns();
                            }
                        }
                        if (null !== $parentColumnNumber) {
                            $this->number_of_columns = $parentColumnNumber;
                        } else {
                            $this->number_of_columns = $this->getNumberOfColumnsByDefault();
                        }
                    } else {
                        $this->number_of_columns = $this->getNumberOfColumnsByDefault();
                    }
                }
            }
            $this->searched_for_number_of_columns = true;
        }
        return $this->number_of_columns;
    }
    
    /**
     * Returns if the the number of columns of this widget depends on the number of columns
     * of the parent layout widget.
     *
     * @return boolean
     */
    abstract public function getNumberOfColumnsByDefault() : int;
    
    /**
     * Returns the default number of columns to layout this widget.
     *
     * @return integer
     */
    abstract public function inheritsNumberOfColumns() : bool;
}