<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;


use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Builds a Markdown documentation view for a meta object and its related objects.
 *
 * The printer renders a table of attributes for the given meta object and can
 * optionally walk through relation attributes to print child objects up to a
 * configurable depth.
 */
class ObjectMarkdownPrinter //implements MarkdownPrinterInterface
{

    protected WorkbenchInterface $workbench;
    
    private string $objectId;

    /**
     * Maximum depth of recursive relation traversal.
     *
     * Depth 0 would print only the root object, higher values include related objects.
     */
    private int $depth = 3;

    /**
     * Current recursion depth for this printer instance.
     */
    private int $currentDepth = 0;

    /**
     * Parent printer that created this instance while walking relations.
     *
     * Null for the root printer.
     */
    private ?ObjectMarkdownPrinter $parent = null;

    /**
     * Queue of relation target object identifiers that still need to be processed.
     *
     * @var string[]
     */
    private array $relations = [];

    /**
     * List of relation target object identifiers that have already been processed.
     *
     * This is kept only on the root printer and shared through the tree in order
     * to avoid infinite loops when relations form cycles.
     *
     * @var string[]
     */
    private array $finishedRelations = [];


    /**
     * Creates a new object markdown printer for the given meta object identifier.
     *
     * When called from a parent printer the depth and current depth are inherited
     * so that the whole tree respects the same maximum depth.
     */
    public function __construct(WorkbenchInterface $workbench, string $objectId, ObjectMarkdownPrinter $parent = null)
    {
        $this->workbench = $workbench;
        $this->objectId = $this->normalize($objectId);
        if($parent){
            $this->parent = $parent;
            $this->currentDepth = $parent->getCurrentDepth() + 1;
            $this->depth = $parent->getDepth();
        }
    }

    /**
     * Builds and returns the complete Markdown for the current object
     * and all related objects up to the configured depth.
     */
    public function getMarkdown(): string
    {
        if(!$this->objectId) return '';
        $metaObject = MetaObjectFactory::createFromString($this->workbench, $this->objectId);
        $this->addFinishedRelation($metaObject->getId());
        
        $markdown = '# ' . $metaObject->getAlias() . "\n\n";
        
        $markdown .= "| Name | Alias | Data Type | Required | Relation |\n";
        $markdown .= "|------|--------|------------|-----------|-----------|\n";
        
        $markdown.= implode("\n",$this->getAttributes($metaObject));
        $markdown .= "\n\n";
        
        if($this->depth >= $this->currentDepth){
            foreach ($this->relations as $relation){
                if(!in_array($relation, $this->getFinishedRelations(), true)){
                    $childPrinter = new ObjectMarkdownPrinter($this->workbench, $relation, $this);
                    $markdown .= $childPrinter->getMarkdown();
                    $this->addFinishedRelation($relation);
                }
                
            }
        }
        
        return $markdown;
        
    }
    /**
     * Returns a list of attribute strings in Markdown format.
     *| Name | Alias | Data Address | Data Type | Required | Relation |
     * 
     * @param MetaObjectInterface $metaObject
     * @return string[]  
     */
    protected function getAttributes(MetaObjectInterface $metaObject): array
    {
        
        $attributes = $metaObject->getAttributes();
        
        $list = [];

        foreach ($attributes->getAll() as  $attribute ) {
            $name = $this->escapeCell($attribute->getName());
            $alias = $this->escapeCell($attribute->getAlias());
            //$dataAddress = $this->escapeCell($attribute->getDataAddress());
            $dataType = $this->escapeCell($attribute->getDataType()->getName());
            $required = $attribute->isRequired();
            $relation = "";
            if ($attribute->isRelation()) {
                $relationObject = $attribute->getRelation();
                $rightObject = $relationObject->getRightObject();

                $this->addRelation($rightObject->getId());
                $relation = $this->createLink($rightObject);
            }


            $list[] = "| {$name} | {$alias}  | {$dataType} | {$required} | {$relation}  |";
        }

        return $list;
    }

    /**
     * Creates a Markdown link to the target meta object.
     *
     * The output format is:
     *   [Alias](Available_metaobjects.md?selector="ObjectName"[Namespace.Alias])
     *
     * @param MetaObjectInterface $metaObject  The target object on the right side of the relation
     * @return string  The formatted Markdown link
     */
    protected function createLink(MetaObjectInterface $metaObject): string
    {
        $alias = $this->escapeCell($metaObject->getAlias());

        $link = '"' . $metaObject->getName() . '"['
            . $metaObject->getNameSpace() . '.'
            . $metaObject->getAlias() . ']';

        return '[' . $alias . ']' .
            '(Available_metaobjects.md?selector=' . urlencode($link) . ')';
    }


    /**
     * Escapes a value so that it can be safely used inside a Markdown table cell.
     *
     * Line breaks are converted to HTML line breaks and pipe characters are escaped.
     */
    protected function escapeCell(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], '<br>', $value);
        $value = str_replace('|', '\|', $value);
        return $value;
    }

    /**
     * Normalizes a raw link selector by extracting the object ID or alias.
     *
     * The function looks for a pattern like:
     *   objectName [idOrAlias]
     * and returns only the part inside the brackets.
     *
     * Example:
     *   "AI agent" [axenox.GenAI.AI_AGENT]  →  axenox.GenAI.AI_AGENT
     *
     * If the selector does not follow this pattern, the original raw string is returned unchanged.
     */
    protected function normalize(string $raw): string
    {
        $decoded = urldecode($raw);

        $start = strpos($decoded, '[');
        $end   = strpos($decoded, ']');

        if ($start === false || $end === false || $end <= $start) {
            return $raw;
        }

        return substr($decoded, $start + 1, $end - $start - 1);
    }


    /**
     * Adds a relation target object identifier to the processing queue if it is not
     * already in the queue and has not been processed before.
     *
     * @return ObjectMarkdownPrinter Provides fluent interface.
     */
    protected function addRelation(string $relation) : ObjectMarkdownPrinter
    {
        if(!in_array($relation, $this->getFinishedRelations(), true)
            && !in_array($relation, $this->relations, true)){
            $this->relations[] = $relation;
        }
        return $this;
    }

    /**
     * Marks a relation target object identifier as processed.
     *
     * For child printers this call is delegated to the root printer so that
     * the finished list is shared for the whole recursion tree.
     *
     * @return ObjectMarkdownPrinter Provides fluent interface on the root printer.
     */
    public function addFinishedRelation(string $relation) : ObjectMarkdownPrinter
    {
        if($this->parent){
            return $this->parent->addFinishedRelation($relation);
        }else {
            if(!in_array($relation, $this->finishedRelations, true)){
                $this->finishedRelations[] = $relation;
            }
            return $this;
        }
    }

    /**
     * Marks multiple relation target object identifiers as processed.
     *
     * @param string[] $relations
     * @return ObjectMarkdownPrinter
     */
    protected function addFinishedRelations(array $relations) : ObjectMarkdownPrinter
    {
        foreach ($relations as $relation){
            $this->addFinishedRelation($relation);
        }
        return $this;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function setObjectId(?string $objectId): ObjectMarkdownPrinter
    {
        $this->objectId = $objectId;
        return $this;
    }

    /**
     * Returns the maximum traversal depth configured for this printer tree.
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Returns the current recursion depth of this printer instance.
     */
    public function getCurrentDepth(): int
    {
        return $this->currentDepth;
    }

    /**
     * Returns the parent printer or null if this is the root printer.
     */
    public function getParent(): ?ObjectMarkdownPrinter
    {
        return $this->parent;
    }

    /**
     * Returns the queue of relation target object identifiers that still need processing.
     *
     * @return string[]
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Returns the list of relation target object identifiers that have been processed.
     *
     * For child printers this list is always read from the root printer so that
     * all printers share the same finished set.
     *
     * @return string[]
     */
    public function getFinishedRelations(): array
    {
        if($this->parent){
            return $this->parent->getFinishedRelations();
        }else{
            return $this->finishedRelations;
        }
    }

    
    
    
}