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
        $this->objectId = $objectId;
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
        
        $markdown .= "| Name | Alias | Data Address | Data Type | Required | Relation |\n";
        $markdown .= "|------|--------|--------------|------------|-----------|-----------|\n";
        
        $markdown.= implode("\n",$this->getAttributes($metaObject));
        $markdown .= "\n";
        
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
            $name = $attribute->getName();
            $alias = $attribute->getAlias();
            $dataAddress = $attribute->getDataAddress();
            $dataType = $attribute->getDataType()->getName();
            $required = $attribute->isRequired();
            $relation = "";
            if($attribute->isRelation()){
                $relationObject = $attribute->getRelation();
                $relation = $relationObject->getAlias();
                $this->addRelation($relationObject->getRightObject()->getId());
            }

            

            $list[] = "| {$name} | {$alias} | {$dataAddress} | {$dataType} | {$required} | {$relation} |\n";
        }
        return $list;
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