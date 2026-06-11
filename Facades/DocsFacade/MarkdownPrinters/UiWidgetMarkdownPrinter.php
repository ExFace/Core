<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\Actions\ShowWidget;
use exface\Core\CommonLogic\Debugger\WidgetDebugger;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Builds a Markdown documentation for the given page and its widget.
 * 
 * 
 * @author Andrej Kabachnik
 */
class UiWidgetMarkdownPrinter implements MarkdownPrinterInterface
{
    protected WorkbenchInterface $workbench;
    
    private WidgetInterface $widget;
    private int $headingLevel = 1;

    /**
     * Creates a new object markdown printer for the given action.
     *
     */
    public function __construct(WidgetInterface $widget, int $headingLevel = 1)
    {
        $this->workbench = $widget->getWorkbench();
        $this->widget = $widget;
        $this->headingLevel = $headingLevel;
    }

    /**
     * Builds and returns the complete Markdown for the current action
     */
    public function getMarkdown(): string
    {
        $widget = $this->widget;
        $widgetDebugger = new WidgetDebugger($widget);
        // Global heading
        $headingWidget = MarkdownDataType::buildMarkdownHeader($widget->getWidgetType() . ' "' . $widget->getCaption() . '"', $this->headingLevel);
        // Widget summary
        $prototypeLink = "[{$widget->getWidgetType()}](" . DocsFacade::buildUrlToDocsForUxonPrototype($widget) . ")";
        
        // Action, that rendered the widget
        if (null !== $renderingAction = $widgetDebugger->getRenderingAction()) {
            $actionAlias = $renderingAction->getAliasWithNamespace();
            $protoytypeAlias = $renderingAction->getAliasOfPrototype();
            if ($protoytypeAlias !== $actionAlias) {
                $renderingActionInfo = "`{$actionAlias}` ({$renderingAction->getName()}), based on prototype [{$protoytypeAlias}](" . DocsFacade::buildUrlToDocsForUxonPrototype($renderingAction) . ')';
            } else {
                $renderingActionInfo = "[$actionAlias](" . DocsFacade::buildUrlToDocsForUxonPrototype($renderingAction) . ") ({$renderingAction->getName()})";
            }
        } else {
            $renderingActionInfo = '[exface.Core.ShowWidget](' . DocsFacade::buildUrlToDocsForUxonPrototype(ShowWidget::class) . ') (root)';
        }
        
        // UXON of the main widget
        // But only show it if the widget is NOT the root of its page because the page will be rendered later anyway
        // and the root widget UXON would be exactly the same as the page widget UXON.
        if ($widget->hasParent()) {
            $widgetChapters = <<<MD

```json
{$widget->exportUxonObjectOriginal()?->toJson(true)}
```

MD;
        }
        
        // UXONs of all parent dialogs
        foreach ($widgetDebugger->getBreadcrumbsDialogs() as $dialog) {
            $widgetChapters .= "\n" 
                . MarkdownDataType::buildMarkdownHeader($dialog->getWidgetType() . ' "' . $dialog->getCaption() . '"', $this->headingLevel + 1) 
                . <<<MD

```json
{$dialog->exportUxonObjectOriginal()?->toJson(true)}
```

MD;

        }

        // Page chapter
        $pagePrinter = new UiPageMarkdownPrinter($widget->getPage(), $this->headingLevel + 1);
        
        // Put it all together
        return <<<MD
{$headingWidget}

{$widgetDebugger->getBreadcrumbs(true, true, false)}

- Widget type: {$prototypeLink}
- Widget ID: `{$widget->getId()}`
- Rendered by action: {$renderingActionInfo}

{$widgetChapters}

{$pagePrinter->getMarkdown()}

MD;
    }
}