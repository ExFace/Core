<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use axenox\GenAI\Interfaces\MarkdownPrinterInterface;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Builds a Markdown documentation for the given page and its widget.
 * 
 * 
 * @author Andrej Kabachnik
 */
class UiPageMarkdownPrinter extends AbstractMarkdownPrinter
{
    protected WorkbenchInterface $workbench;
    
    private UiPageInterface $page;
    private int $headingLevel = 1;

    /**
     * Creates a new object markdown printer for the given action.
     *
     */
    public function __construct(UiPageInterface $page, int $headingLevel = 1)
    {
        $this->workbench = $page->getWorkbench();
        $this->page = $page;
        $this->headingLevel = $headingLevel;
    }

    /**
     * Builds and returns the complete Markdown for the current action
     */
    public function getMarkdown(): string
    {
        $page = $this->page;
        $headingPage = MarkdownDataType::buildMarkdownHeader('Page "' . $page->getName() . '"', $this->headingLevel);
        $rootWidget = $page->getWidgetRoot();
        if ($rootWidget !== null) {
            $prototypeLink = DocsFacade::buildUrlToDocsForUxonPrototype($rootWidget);
            $rootWidgetLink = "[{$rootWidget->getWidgetType()}]($prototypeLink)";
            $chapterWidget = MarkdownDataType::buildMarkdownHeader('Page root widget', $this->headingLevel + 1) . <<<MD

```
{$page->exportUxonObject()->getProperty('contents')->toJson(true)}
```
MD;

        } else {
            $rootWidgetLink = 'No widgets defined';
            $chapterWidget = '';
        }
        
        $parentPage = $page->getParentPage();
        if ($parentPage !== null) {
            $parentLink = $parentPage->getName()  . "[{$parentPage->getAliasWithNamespace()}]";
        } else {
            $parentLink = 'No parent - the page is a root node in the menu';
        }
        
        return <<<MD
{$headingPage}

{$this->escapeMarkdownText($page->getIntro())}

- Alias: {$page->getAliasWithNamespace()}
- Menu parent: {$parentLink}
- Root widget: $rootWidgetLink

{$chapterWidget}

MD;
    }
}