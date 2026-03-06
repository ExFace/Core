<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Button;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Widgets\Dialog;

/**
 * Helps extract useful debug information from Widgets - in particular for debug widgets
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetDebugger implements iCanGenerateDebugWidgets
{
    private WidgetInterface $widget;
   
    public function __construct(WidgetInterface $widget)
    {
        $this->widget = $widget;
    }

    /**
     *
     * @param WidgetInterface $inputWidget
     * @return string
     */
    public static function getWidgetUiPath(WidgetInterface $inputWidget) : string
    {
        $inputName = $inputWidget->getCaption();
        switch (true) {
            case $inputWidget instanceof Dialog && $inputWidget->hasParent():
                $btn = $inputWidget->getParent();
                if ($btn instanceof Button) {
                    if ($btnCaption = $btn->getCaption()) {
                        $inputName = $btnCaption;
                    }
                    $btnInput = $btn->getInputWidget();
                    $inputName = self::getWidgetUiPath($btnInput) . ' > ' . $inputName;
                }
                break;
        }
        return $inputName ?? $inputWidget->getWidgetType();
    }
    
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        return $this->getWidget()->createDebugWidget($debug_widget);
    }
    
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
}