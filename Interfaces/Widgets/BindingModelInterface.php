<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataPointerInterface;

/**
 * Binding models contain data pointers, that point from widgets to the data sheet cells, rows, etc., used by them
 * 
 * @author Andrej Kabachnik
 *
 */
interface BindingModelInterface
{
    /**
     * 
     * @return WidgetInterface
     */
    public function getWidget() : WidgetInterface;
    
    /**
     * 
     * @return string[]
     */
    public function getBoundWidgetIds(): array;
    
    /**
     * 
     * @param string $widgetId
     * @return DataPointerInterface[]
     */
    public function getBindingsForWidgetId(string $widgetId) : array;
    
    /**
     * 
     * @param WidgetInterface $widget
     * @return DataPointerInterface[]
     */
    public function getBindingsForWidget(WidgetInterface $widget) : array;
    
    /**
     *
     * @param WidgetInterface $widget
     * @param string $bindingName
     * @param DataPointerInterface $pointer
     * @return PrefillModelInterface
     */
    public function addBindingPointer(WidgetInterface $widget, string $bindingName, DataPointerInterface $pointer) : PrefillModelInterface;
    
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param string $bindingName
     * @return DataPointerInterface|NULL
     */
    public function getBinding(WidgetInterface $widget, string $bindingName) : ?DataPointerInterface;
    
    /**
     *
     * @param WidgetInterface $widget
     * @param string $bindingName
     * @return bool
     */
    public function hasBinding(WidgetInterface $widget, string $bindingName) : bool;
    
    /**
     * 
     * @return DataPointerInterface[]
     */
    public function getBindings() : array;
}