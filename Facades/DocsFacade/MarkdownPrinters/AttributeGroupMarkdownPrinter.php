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
use exface\Core\Interfaces\Model\MetaAttributeGroupInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaAttributeListInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 */
class AttributeGroupMarkdownPrinter //implements MarkdownPrinterInterface
{
    protected WorkbenchInterface $workbench;
    
    private MetaAttributeGroupInterface $group;
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
    public function __construct(WorkbenchInterface $workbench, MetaAttributeGroupInterface $group, int $headingLevel = 1)
    {
        $this->workbench = $workbench;
        $this->group = $group;
        $this->headingLevel = $headingLevel;
    }

    /**
     * Builds and returns the complete Markdown for the current group
     */
    public function getMarkdown(): string
    {
        $heading = MarkdownDataType::buildMarkdownHeader($this->group->getAlias(), $this->headingLevel);

        $rows = [];

        try {
            $attributes = $this->group->getAttributes();

            foreach ($attributes as $attribute) {
                if (!$attribute instanceof MetaAttributeInterface) {
                    continue;
                }

                $name  = (string) $attribute->getName();
                $alias = (string) $attribute->getAlias();

                // Minimaler Hinweis, ohne Relation auszubauen/aufzulösen
                $note = '';
                if (method_exists($attribute, 'isRelation') && $attribute->isRelation()) {
                    $note = ' (Relation)';
                }

                // Pipes escapen, damit die Tabelle nicht kaputt geht
                $name  = str_replace('|', '\|', $name);
                $alias = str_replace('|', '\|', $alias);

                $rows[] = "| {$name} | {$alias}{$note} |";
            }
        } catch (\Throwable $e) {
            // bewusst still; alternativ: Fehlerzeile in die Tabelle
        }

        $table = "| Name | Alias |\n|---|---|\n";
        $table .= $rows ? implode("\n", $rows) . "\n" : "| _keine_ | _keine_ |\n";

        return <<<MD
{$heading}

{$table}
MD;
    }



}