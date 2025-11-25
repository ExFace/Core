<?php

namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;


use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class ObjectMarkdownPrinter //implements MarkdownPrinterInterface
{

    protected WorkbenchInterface $workbench;
    
    private string $objectId;
    
    private int $depth = 3;
    
    private int $currentDepth = 0;
    
    private ?ObjectMarkdownPrinter $parent = null;
    
    private array $relations = [];
    
    private array $finishedRelations = [];

    

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
                $relation = $this->escapeCell($rightObject->getAlias());
                $link = '"'. $rightObject->getName() .'"['. $rightObject->getNameSpace() ."." .$rightObject->getAlias() ."]";
                $this->addRelation($rightObject->getId());
                $relation = "[" . $relation . "]"; 
                $relation .= "(Available_metaobjects.md?selector=". urlencode($link) .")";
            }

            $list[] = "| {$name} | {$alias}  | {$dataType} | {$required} | {$relation}  |";
        }

        return $list;
    }

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



    protected function addRelation(string $relation) : ObjectMarkdownPrinter
    {
        if(!in_array($relation, $this->getFinishedRelations(), true)
            && !in_array($relation, $this->relations, true)){
            $this->relations[] = $relation;
        }
        return $this;
    }
    
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

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getCurrentDepth(): int
    {
        return $this->currentDepth;
    }

    public function getParent(): ?ObjectMarkdownPrinter
    {
        return $this->parent;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }
    
    public function getFinishedRelations(): array
    {
        if($this->parent){
            return $this->parent->getFinishedRelations();
        }else{
            return $this->finishedRelations;
        }
    }

    
    
    
}