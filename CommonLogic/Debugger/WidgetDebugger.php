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
     * @param WidgetInterface $widget
     * @return string
     */
    public static function getWidgetUiPath(WidgetInterface $widget) : string
    {
        $path = $widget->getCaption();
        switch (true) {
            case $widget instanceof Dialog && $widget->hasParent():
                $btn = $widget->getParent();
                if ($btn instanceof Button) {
                    if ($btnCaption = $btn->getCaption()) {
                        $path = $btnCaption;
                    }
                    $btnInput = $btn->getInputWidget();
                    $path = self::getWidgetUiPath($btnInput) . ' > ' . $path;
                }
                break;
        }
        return $path ?? $widget->getWidgetType();
    }
    
    public static function getWidgetUiPathMarkdown(WidgetInterface $widget, bool $startWithPage = false, bool $includeLastWidgetType = true) : string
    {
        $path = '';
        if ($startWithPage) {
            $page = $widget->getPage();
            $path .= "Page [{$page->getName()}]({$page->getAliasWithNamespace()}.html)";
        }
        $innerPath = $widget->getWidgetType() . ' "' . $widget->getCaption() . '"';
        switch (true) {
            case $widget instanceof Dialog && $widget->hasParent():
                $btn = $widget->getParent();
                if ($btn instanceof Button) {
                    if ($btnCaption = $btn->getCaption()) {
                        $innerPath = $btnCaption;
                    }
                    $btnInput = $btn->getInputWidget();
                    $innerPath = self::getWidgetUiPath($btnInput) . ' > ' . $innerPath;
                }
                break;
        }
        return $path . ($path ? ' > ' : '') . $innerPath;
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