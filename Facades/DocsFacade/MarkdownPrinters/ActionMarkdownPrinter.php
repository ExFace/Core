<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Facades\MarkdownInstancePrinterInterface;
use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Builds a Markdown documentation view for an action
 * 
 * @author Brooklyn Fränzschky, Andrej Kabachnik
 */
class ActionMarkdownPrinter extends AbstractMarkdownPrinter implements MarkdownInstancePrinterInterface
{
    protected WorkbenchInterface $workbench;
    
    private ActionInterface $action;
    private int $headingLevel = 1;    


    /**
     * Creates a new object markdown printer for the given action.
     *
     */
    public function __construct(ActionInterface $action, int $headingLevel = 1)
    {
        $this->workbench = $action->getWorkbench();
        $this->action = $action;
        $this->headingLevel = $headingLevel;
    }



    /**
     * {@inheritDoc}
     * @see MarkdownInstancePrinterInterface::constructForInstance()
     */
    public static function constructForInstance(object $instance) : MarkdownPrinterInterface
    {
        return new self($instance);
    }

    /**
     * Builds and returns the complete Markdown for the current action
     */
    public function getMarkdown(): string
    {
        $action = $this->action;
        $heading = MarkdownDataType::buildMarkdownHeader('Action "' . $action->getName() . '"' . ($action->hasMetaObject() ? ' on object "' . $action->getMetaObject()->getName() . '"' : ''), $this->headingLevel);
        $prototypeClass = '\\' . get_class($action);
        $prototypeLink = DocsFacade::buildUrlToDocsForUxonPrototype($action);
        $uxonBlock = MarkdownDataType::escapeCodeBlock($action->exportUxonObject()->toJson(true), 'json');
        
        return <<<MD
{$heading}

{$this->escapeMarkdownText($action->getHint())}

- Alias: {$action->getAliasWithNamespace()}
- Prototype: [$prototypeClass]($prototypeLink)

{$uxonBlock}

MD;
    }
}