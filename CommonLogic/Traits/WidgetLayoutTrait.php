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

    private $searchedForNumberOfColumns = false;

    private $column_stack_on_smartphones = null;

    private $column_stack_on_tablets = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getNumberOfColumns()
     */
    public function getNumberOfColumns()
    {
        if (! $this->searchedForNumberOfColumns) {
            if ($layoutWidget = $this->getLayoutWidget()) {
                $this->number_of_columns = $layoutWidget->getNumberOfColumns();
            }
            
            // Es ist moeglich, dass number_of_columns null ist, wenn es nirgendwo
            // spezifiziert wurde. Ist fuer dieses Widget explizit eine Spaltenzahl als
            // Breite gesetzt, dann wird diese uebernommen, sonst wird sie nur ueber-
            // nommen, wenn sie kleiner als die vorher ermittelte Spaltenzahl ist.
            $dimension = $this->getWidth();
            if ($dimension->isRelative()) {
                $width = $dimension->getValue();
                if (is_null($this->number_of_columns)) {
                    if (is_numeric($width)) {
                        $this->number_of_columns = $width;
                    }
                } else {
                    if ($width === 'max') {
                        $width = $this->number_of_columns;
                    }
                    if ($width < 1) {
                        $width = 1;
                    }
                    $this->number_of_columns = $width;
                }
            }
            
            $this->searchedForNumberOfColumns = true;
        }
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

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getStackColumnsOnTabletsSmartphones()
     */
    public function getStackColumnsOnTabletsSmartphones()
    {
        return $this->column_stack_on_smartphones;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setStackColumnsOnTabletsSmartphones()
     */
    public function setStackColumnsOnTabletsSmartphones($value)
    {
        $this->column_stack_on_smartphones = BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getStackColumnsOnTabletsTablets()
     */
    public function getStackColumnsOnTabletsTablets()
    {
        return $this->column_stack_on_tablets;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setStackColumnsOnTabletsTablets()
     */
    public function setStackColumnsOnTabletsTablets($value)
    {
        $this->column_stack_on_tablets = BooleanDataType::parse($value);
        return $this;
    }
}