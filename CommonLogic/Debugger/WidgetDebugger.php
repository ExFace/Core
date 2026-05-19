<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\Contexts\DebugContext;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Widgets\Button;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Widgets\Dialog;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\Popup;
use exface\Core\Widgets\Tab;
use exface\Core\Widgets\WidgetConfigurator;
use exface\Core\Widgets\WizardStep;

/**
 * Helps extract useful debug information from Widgets - in particular for debug widgets
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetDebugger implements iCanGenerateDebugWidgets
{
    private WidgetInterface $widget;
    private bool $useMarkdown;

    /**
     * @param WidgetInterface $widget
     * @param bool $useMarkdown
     */
    public function __construct(WidgetInterface $widget, bool $useMarkdown = true)
    {
        $this->widget = $widget;
        $this->useMarkdown = $useMarkdown;
    }

    /**
     * @deprecated use getBreadcrumbs() instead
     * 
     * @param WidgetInterface $widget
     * @return string
     */
    public static function findWidgetUiPath(WidgetInterface $widget) : string
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
                    $path = self::findWidgetUiPath($btnInput) . ' > ' . $path;
                }
                break;
        }
        return $path ?? $widget->getWidgetType();
    }

    /**
     * @deprecated use getBreadcrumbs() instead
     * 
     * @param WidgetInterface $widget
     * @param bool $startWithPage
     * @param bool $includeLastWidgetType
     * @return string
     */
    public static function findWidgetUiPathMarkdown(WidgetInterface $widget, bool $startWithPage = false, bool $includeLastWidgetType = true) : string
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
                    $innerPath = self::findWidgetUiPath($btnInput) . ' > ' . $innerPath;
                }
                break;
        }
        return $path . ($path ? ' > ' : '') . $innerPath;
    }

    /**
     * Returns an array of parent widgets defining the click path to the main widget in the UI
     * 
     * @param bool $compact
     * @return WidegetInterface[]
     */
    public function getBreadcrumbsWidgets(bool $compact = false) : array
    {
        $importantContainers = [];
        $widget = $this->widget;
        while (null !== $widget = $widget->getParent()) {
            switch (true) {
                // Widgets like Chart or Map need to be in the crumbs because otherwise they cannot be distinguished
                // from tables. And very often they are located next to tables, so it is difficult to see, which one
                // of them is in the path.
                case $widget instanceof iShowData:
                // Filters need to be in the crumbs because otherwise people keep looking for inputs, but do not realize,
                // that the input is actually a filter. However, this is only important for detailed crumbs. Compact
                // crumbs are fine without this information
                case $widget instanceof Filter && $compact === false:
                // We need to know the tab or (wizard) step to find out, where we are
                case $widget instanceof Tab && ! ($widget->getParent() instanceof WidgetConfigurator):
                case $widget instanceof WizardStep:
                // We need to know, what buttons where pressed
                case $widget instanceof Button:
                    $importantContainers[] = $widget;
                    break;
            }
        }
        return array_reverse($importantContainers);
    }

    /**
     * Returns an array of the buttons pressed to reach the main widget
     * 
     * @return Button[]
     */
    public function getBreadcrumbsButtons() : array
    {
        $dialogsBottomUp = $this->widget->getParents(function(WidgetInterface $parent) {
            return $parent instanceof Button;
        });
        return array_reverse($dialogsBottomUp);
    }

    /**
     * Returns an array of the dialogs opened to reach the main widget
     * 
     * @return Dialog[]
     */
    public function getBreadcrumbsDialogs() : array
    {
        $buttonsBottomUp = $this->widget->getParents(function(WidgetInterface $parent) {
            return $parent instanceof Dialog
                || $parent instanceof Popup;
        });
        return array_reverse($buttonsBottomUp);
    }

    /**
     * Renders text or markdown breadcrumbs to show how the widget was reached in the UI
     * 
     * @param bool $startWithPage
     * @param bool $linkPage
     * @param bool $linkLastWidget
     * @param string $delimiter
     * @return string
     */
    public function getBreadcrumbs(bool $startWithPage = true, bool $linkPage = false, bool $linkLastWidget = false, string $delimiter = ' > ') : string
    {
        $path = '';
        if ($startWithPage === true) {
            $pageName = $this->widget->getPage()->getName();
            if ($this->useMarkdown === true && $linkPage === true) {
                $pageName = "[{$pageName}]({$this->widget->getPage()->getAliasWithNamespace()}.html)";
            }
            $path .= 'Page "' . $pageName . '"';
        }
        $containersInPath = $this->getBreadcrumbsWidgets();
        $clickPath = [];
        $inputWidgetsProcessed = [];
        foreach ($containersInPath as $widget) {
            switch (true) {
                case $widget instanceof Button:
                    if (null !== $inputWidget = $widget->getInputWidget()) {
                        if (! $inputWidget->hasParent() && ! in_array($inputWidget, $inputWidgetsProcessed, true)) {
                            $inputWidgetsProcessed[] = $inputWidget;
                            $clickPath[] = $this->buildBreadcrumb($inputWidget);
                        }
                    }
                    $clickPath[] = $this->buildBreadcrumb($widget);
                    break;
                default:
                    $clickPath[] = $this->buildBreadcrumb($widget);
            }
        }
        if (! empty($clickPath)) {
            $path .= $delimiter . implode($delimiter, $clickPath);
        }
        $lastCrumb = $this->buildBreadcrumb($this->widget);
        if ($this->useMarkdown === true && $linkLastWidget === true) {
            $lastCrumb = "[{$lastCrumb}](" . DebugContext::buildUrlWidgetInfo($this->widget) . ")";
        }
        return $path . $delimiter . $lastCrumb;
    }

    /**
     * @param WidgetInterface $widget
     * @param bool $includeType
     * @return string|NULL
     */
    protected function buildBreadcrumb(WidgetInterface $widget, bool $includeType = true) {
        $caption = $widget->getCaption();
        if (! $caption) {
            $caption = $widget->getWidgetType();
        } elseif ($includeType) {
            if ($this->useMarkdown) {
                $caption = '**' . $caption . '**';
            }
            $caption = $widget->getWidgetType() . ' "' . $caption . '"';
        }
        return $caption;
    }

    /**
     * Returns the action, that renders this widget or NULL if no specific action found.
     * 
     * Null means, there is no button, that triggers rendering the widget - e.g. root widgets of pages are not rendered
     * by a specific action. Well, technically they are - by the `ShowWidget`, but that action is not explicitly defined
     * in any UXON.
     * 
     * @return ActionInterface|null
     */
    public function getRenderingAction() : ?ActionInterface
    {
        $actionTrigger = $this->widget->getParentByClass(iTriggerAction::class);
        if ($actionTrigger && $actionTrigger->hasAction()) {
            return $actionTrigger->getAction();
        }
        return null;
    }

    /**
     * @return WidgetInterface
     */
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