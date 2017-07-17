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