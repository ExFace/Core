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
use Respect\Validation\Rules\Length;

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
    
    private string $objectIdOrAlias;
    private int $headingLevel = 1;

    /**
     * Maximum depth of recursive relation traversal.
     *
     * Depth 0 would print only the root object, higher values include related objects.
     */
    private int $relationDepth = 0;
    private ?string $relationType = RelationTypeDataType::REGULAR;
    
    private static array $printedObjects = [];


    /**
     * Creates a new object markdown printer for the given meta object identifier.
     *
     * When called from a parent printer the depth and current depth are inherited
     * so that the whole tree respects the same maximum depth.
     */
    public function __construct(WorkbenchInterface $workbench, string $objectId, int $relationDepth = 1, int $headingLevel = 1)
    {
        $this->workbench = $workbench;
        $this->objectIdOrAlias = $this->normalize($objectId);
        $this->headingLevel = $headingLevel;
        $this->relationDepth = $relationDepth;
    }

    /**
     * Builds and returns the complete Markdown for the current object
     * and all related objects up to the configured depth.
     */
    public function getMarkdown(): string
    {
        $metaObject = MetaObjectFactory::createFromString($this->workbench, $this->objectIdOrAlias);
        static::$printedObjects[] = $metaObject;
        
        $headingLevel = $this->headingLevel;
        $heading = MarkdownDataType::buildMarkdownHeader('Metaobject "' . $metaObject->getName() . '"', $headingLevel);
        
        $description = $metaObject->getShortDescription();
        
        $connectorClass = PhpClassDataType::findClassNameWithoutNamespace($metaObject->getDataConnection());
        $connectorLink = DocsFacade::buildUrlToDocsForUxonPrototype($metaObject->getDataConnection());
        $queryBuilder = QueryBuilderFactory::createForObject($metaObject);
        $queryBuilderClass = PhpClassDataType::findClassNameWithoutNamespace($queryBuilder);
        $queryBuilderLink = DocsFacade::buildUrlToDocsForUxonPrototype($queryBuilder);
        
        $importantAttributes = '';
        if ($metaObject->hasUidAttribute()) {
            $importantAttributes .= PHP_EOL . "- UID attribute: **{$metaObject->getUidAttributeAlias()}**"; 
        }
        if ($metaObject->hasLabelAttribute()) {
            $importantAttributes .= PHP_EOL . "- Label attribute: **{$metaObject->getLabelAttributeAlias()}**";
        }
        $importantAttributes = trim($importantAttributes);
        
        $attributesHeading = MarkdownDataType::buildMarkdownHeader("Attributes of \"{$metaObject->getName()}\"", $headingLevel + 1);

        $actionHeading = MarkdownDataType::buildMarkdownHeader("Actions of \"{$metaObject->getName()}\"", $headingLevel + 1);

        $groupHeading = MarkdownDataType::buildMarkdownHeader("Attributegroups of \"{$metaObject->getName()}\"", $headingLevel + 1);

        $markdown = <<<MD

{$heading} 

- Alias: **{$metaObject->getAliasWithNamespace()}**
- Data Source: **{$metaObject->getDataSource()->getName()}**, query builder: [{$queryBuilderClass}]($queryBuilderLink), connector: [{$connectorClass}]({$connectorLink})
{$importantAttributes}

{$description}

{$attributesHeading}

{$this->buildMdAttributesTable($metaObject->getAttributes())}

{$this->buildMdAttributesSections($metaObject, $headingLevel+2)}

{$this->buildMdActionSection($actionHeading, $metaObject, $headingLevel+2 )}

{$this->buildMdAttributeGroupSection($groupHeading, $metaObject, $headingLevel+2 )}

{$this->buildMdBehaviorsSections($metaObject, 'Behaviors of "' . $metaObject->getName() . '"', $headingLevel+1)}

{$this->buildMdRelatedObjects($metaObject->getRelations(), 'Related objects', $headingLevel)}
MD;        
        return $markdown;
    }

    /**
     * @param MetaRelationInterface[] $relations
     * @param string $heading
     * @param int $headingLevel
     * @return string
     */
    protected function buildMdRelatedObjects(array $relations, string $heading, int $headingLevel = 1) : string
    {
        $markdown = '';
        $depth = $this->getRelationDepth();
        if($depth > 0){
            $onlyType = $this->getRelationType();
            foreach ($relations as $relation) {
                if ($onlyType !== null && $relation->getType()->__toString() !== $onlyType) {
                    continue;
                }
                if (in_array($relation->getRightObject(), static::$printedObjects, true)) {
                    continue;
                }
                $relObj = $relation->getRightObject();
                static::$printedObjects[] = $relObj;
                $childPrinter = new ObjectMarkdownPrinter($this->workbench, $relObj, ($depth - 1), $headingLevel+1);
                $markdown .= $childPrinter->getMarkdown();
            }
        }
        if ($markdown !== '') {
            $markdown = MarkdownDataType::buildMarkdownHeader($heading, $headingLevel) . "\n\n" . $markdown;
        }
        return $markdown;
    }
    
    protected function buildMdAttributesSections(MetaObjectInterface $obj, int $headingLevel = 3) : string
    {
        $markdown = '';
        foreach ($obj->getAttributes() as $attr) {
            $markdown .= $this->buildMarkDownAttributeSection($attr, $headingLevel);
        }
        return $markdown;
    }
    
    protected function buildMarkDownAttributeSection(MetaAttributeInterface $attr, int $headingLevel = 3) : string
    {
        $heading = MarkdownDataType::buildMarkdownHeader($attr->getName(), $headingLevel);
        $dataType = $attr->getDataType();
        $dataTypeLink = DocsFacade::buildUrlToDocsForUxonPrototype($dataType);
        return <<<MD

{$heading}

{$attr->getShortDescription()}

Alias: **{$attr->getAlias()}**

Properties: {$this->buildMdAttributeProperties($attr)}

{$this->buildMdCodeblock($attr->getDataAddress(), 'Data address:')}

{$this->buildMdUxonCodeblock($attr->getDataAddressProperties(), 'Data address properties:')}

{$this->buildMdUxonCodeblock($attr->getCustomDataTypeUxon(), 'Configuration of data type [' . $dataType->getAliasWithNamespace() . '](' . $dataTypeLink . '):')}

MD;

    }

    protected function buildMdActionSection(string $header, MetaObjectInterface $obj, int $headingLevel = 3) : string
    {
       
        $markdown = '';
        try{
            foreach ($obj->getActions() as $act) {
                $actionPrinter = new ActionMarkdownPrinter($this->workbench, $act, $headingLevel);
                $markdown .= $actionPrinter->getMarkdown();
            } 
        }catch (\Exception $e){
            
        }
        
        return <<<MD
{$header}

{$markdown}
MD;

    }
    
    protected function buildMdAttributeGroupSection(string $header, MetaObjectInterface $obj, int $headingLevel = 3) : string
    {
        $markdown = '';
        
        $groups = $obj->getAttributeGroups();
        foreach ($groups as $group) {
            $groupPrinter = new AttributeGroupMarkdownPrinter($this->workbench, $group, $headingLevel);
            $markdown .= $groupPrinter->getMarkdown();
        }
        
        return <<<MD
{$header}

{$markdown}
MD;

    }
    
    protected function buildMdCodeblock(string $code, string $caption = '', int $maxCharsForInlineBlock = 60) : string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        if (mb_strlen($code) <= $maxCharsForInlineBlock) {
            $markdown = "$caption `{$code}`";
        } else {
            $markdown = <<<MD

{$caption}

```uxon
{$code}
```

MD;
        }
        return $markdown;
    }
    
    protected function buildMdUxonCodeblock(UxonObject $uxon, string $caption = '', int $maxCharsForInlineBlock = 60) : string
    {
        if ($uxon->isEmpty()) {
            return '';
        }
        $json = $uxon->toJson(false);
        if (mb_strlen($json) > $maxCharsForInlineBlock) {
            $json = $uxon->toJson(true);
        }
        return $this->buildMdCodeblock($json, $caption, $maxCharsForInlineBlock);
    }
    
    protected function buildMdBehaviorsSections(MetaObjectInterface $obj, ?string $heading = null, int $headingLevel = 2) : string
    {
        $behaviors = $obj->getBehaviors();
        if ($behaviors->isEmpty()) {
            return '';
        }
        $heading = MarkdownDataType::buildMarkdownHeader($heading, $headingLevel);
        $subsections = '';
        foreach ($behaviors as $behavior) {
            $subsections .= $this->buildMdBehaviorSection($behavior, $headingLevel+1);
        }
        return <<<MD
{$heading}

{$subsections}
MD;

    }
    
    protected function buildMdBehaviorSection(BehaviorInterface $behavior, int $headingLevel = 3) : string
    {
        $heading = MarkdownDataType::buildMarkdownHeader($behavior->getName(), $headingLevel);
        $prototypeClass = '\\' . get_class($behavior);
        $prototypeLink = DocsFacade::buildUrlToDocsForUxonPrototype($behavior);
        return <<<MD

{$heading}

- Prototype: [$prototypeClass]($prototypeLink)

```
{$behavior->exportUxonObject()->toJson(true)}
```
MD;
    }
    
    protected function buildMdAttributeProperties(MetaAttributeInterface $attr) : string
    {
        $properties = [];
        if ($attr->isReadable()) {
            $properties[] = 'readable';
        }
        if ($attr->isWritable()) {
            $properties[] = 'writable';
        }
        if ($attr->isEditable()) {
            $properties[] = 'editable';
        }
        if ($attr->isRequired()) {
            $properties[] = 'required';
        }
        if ($attr->isHidden()) {
            $properties[] = 'hidden';
        }
        if ($attr->isFilterable()) {
            $properties[] = 'filterable';
        }
        if ($attr->isSortable()) {
            $properties[] = 'sortable';
        }
        if ($attr->isAggregatable()) {
            $properties[] = 'aggregatable';
        }
        $propertiesPills = '`' . implode('`, `', $properties) . '`';
        return $propertiesPills;
    }
    
    /**
     * Returns a list of attribute strings in Markdown format.
     *| Name | Alias | Data Address | Data Type | Required | Relation |
     * 
     * @param MetaObjectInterface $metaObject
     * @return string[]  
     */
    protected function buildMdAttributesTable(MetaAttributeListInterface $attributes): string
    {        
        $list = [];
        foreach ($attributes->getAll() as  $attribute ) {
            $name = $this->escapeCell($attribute->getName());
            $alias = $this->escapeCell($attribute->getAlias());
            //$dataAddress = $this->escapeCell($attribute->getDataAddress());
            $dataTypeLink = DocsFacade::buildUrlToDocsForUxonPrototype($attribute->getDataType());
            $dataType = $this->escapeCell("[{$attribute->getDataType()->getAliasWithNamespace()}]($dataTypeLink)");
            $relationText = "";
            if ($attribute->isRelation()) {
                $rel = $attribute->getRelation();
                try {
                    $relationText = $this->createLink($rel->getRightObject());
                } catch (MetaRelationBrokenError $e) {
                    $relationText = 'Related object `' . $rel->getRightObjectId() . '` not found!';
                }
            }


            $list[] = "| {$name} | {$alias}  | {$dataType} | {$relationText}  |";
        }

        $rows = implode("\n", $list);
        return <<<MD

| Name | Alias | Data Type | Relation to |
|------|-------|-----------|-------------|
{$rows}

MD;        
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
        $link = DocsFacade::buildUrlToDocsForMetaObject($metaObject);
        return "[$alias]({$link})";
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

    protected function getObjectSelectorString(): ?string
    {
        return $this->objectIdOrAlias;
    }

    /**
     * Returns the maximum traversal depth configured for this printer tree.
     */
    protected function getRelationDepth(): int
    {
        return $this->relationDepth;
    }
    
    protected function getRelationType() : string
    {
        return $this->relationType;
    }

    /**
     * Only include the following relation type (e.g. regular or reverse) or all relations (pass `null` here)
     * @param string|null $relaitonType
     * @return $this
     */
    public function includeRelationsOfType(?string $relaitonType) : ObjectMarkdownPrinter
    {
        $this->relationType = RelationTypeDataType::cast($relaitonType);
        return $this;
    }

    /**
     * @param int $depth
     * @return $this
     */
    public function includeRelationDepth(int $depth) : ObjectMarkdownPrinter
    {
        $this->relationDepth = $depth;
        return $this;
    }
}