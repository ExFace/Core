<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;


use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Exceptions\Model\MetaRelationBrokenError;
use exface\Core\Facades\DocsFacade;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaAttributeListInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Builds a Markdown documentation view for a meta object and its related objects.
 *
 * The printer renders a table of attributes for the given meta object and can
 * optionally walk through relation attributes to print child objects up to a
 * configurable depth.
 */
class ActionMarkdownPrinter //implements MarkdownPrinterInterface
{
    protected WorkbenchInterface $workbench;
    
    private ActionInterface $action;
    private int $headingLevel = 1;

    /**
     * Maximum depth of recursive relation traversal.
     *
     * Depth 0 would print only the root object, higher values include related objects.
     */
    private int $relationDepth = 0;
    private ?string $relationType = RelationTypeDataType::REGULAR;
    


    /**
     * Creates a new object markdown printer for the given action.
     *
     */
    public function __construct(WorkbenchInterface $workbench, ActionInterface $action, int $headingLevel = 1)
    {
        $this->workbench = $workbench;
        $this->action = $action;
        $this->headingLevel = $headingLevel;
    }

    /**
     * Builds and returns the complete Markdown for the current action
     */
    public function getMarkdown(): string
    {
        $heading = MarkdownDataType::buildMarkdownHeader($this->action->getName(), $this->headingLevel);

        $uxon = $this->action->exportUxonObject();
        
        $uxon = $uxon->toJson();
        //Uxon

        //TODO Einbauen Uxon im Codeblock, mögliche Description
        
        return <<<MD
{$heading}

Uxon:
´´´
{$uxon}
´´´
MD;
    }

    
}