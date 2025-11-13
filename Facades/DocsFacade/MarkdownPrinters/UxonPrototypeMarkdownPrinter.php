<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use axenox\GenAI\Interfaces\MarkdownPrinterInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Uxon\QueryBuilderSchema;
use exface\Core\Uxon\UxonSchema;

/**
 * Generates a Markdown description of a UXON prototype class
 * 
 * ## Example
 * 
 * ```
 * # DataSheet
 * 
 * A data sheet is...
 * 
 * ## Properties
 * 
 * ### meta_object
 * 
 * The object of the Data Sheet 
 * 
 * It is important because...
 * 
 * ### Presets
 * 
 * ```
 */
class UxonPrototypeMarkdownPrinter
{
    private PrototypeSelectorInterface $selector;
    private int $headingLevel;
    
    /**
     * Examples:
     * - `\exface\Core\CommonLogic\DataSheets\DataSheet`
     * - `exface/Core/CommonLogic/DataSheets/DataSheet.php`
     * 
     * @param string $prototypeClassOrFilepath
     */
    public function __construct(WorkbenchInterface $workbench, string $prototypeClassOrFilepath, int $headingLevel = 1)
    {
        $this->selector = new UxonPrototypeSelector($workbench, $prototypeClassOrFilepath);
        $this->headingLevel = $headingLevel;
    }
    
    public function getMarkdown(): string
    {
        $selector = $this->selector;
        if ($selector->isFilepath()) {
            $filepathRelative = $selector->toString();
            $prototypeClass = PhpFilePathDataType::findClassInFile($filepathRelative);
        } else {
            $prototypeClass = $selector->toString();
            $filepathRelative = PhpFilePathDataType::findFileOfClass($prototypeClass);
        }
        
        if (! is_a($prototypeClass, iCanBeConvertedToUxon::class, true)) {
            throw new RuntimeException('Invalid UXON prototype "' . $prototypeClass . '"');
        }
        $schemaClass = $prototypeClass::getUxonSchemaClass() ?? UxonSchema::class;
        $schema = new $schemaClass($selector->getWorkbench());

        if ($schema instanceof QueryBuilderSchema) {
            $metaObject = 'exface.Core.UXON_QUERY_BUILDER_ANNOTATION';
        } else {
            $metaObject = 'exface.Core.UXON_PROPERTY_ANNOTATION';
        }

        // Read prototypeClass annotations
        $dsClass = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_ENTITY_ANNOTATION');
        $dsClass->getColumns()->addMultiple([
            'CLASSNAME',
            'TITLE',
            'DESCRIPTION'
        ]);
        $dsClass->getFilters()->addConditionFromString('FILE', $filepathRelative);
        try {
            $dsClass->dataRead();
        } catch (\Throwable $e) {
            throw $e;
        }
        $classInfo = $dsClass->getRow(0);

        // Read property annotations
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $metaObject);
        $ds->getColumns()->addMultiple([
            'PROPERTY',
            'TYPE',
            'TEMPLATE',
            'DEFAULT',
            'TITLE',
            'REQUIRED',
            'DESCRIPTION'

        ]);
        $ds->getFilters()->addConditionFromString('FILE', $filepathRelative, ComparatorDataType::EQUALS);
        $ds->getSorters()->addFromString('PROPERTY', SortingDirectionsDataType::ASC);

        if ($schema instanceof QueryBuilderSchema) {
            $ds->getColumns()->addFromExpression('TARGET');
            $ds->getFilters()->addConditionFromString('TARGET', $schema->getLevel(), ComparatorDataType::EQUALS);
        }

        try {
            $ds->dataRead();
        } catch (\Throwable $e) {
            // TODO
        }

        $propertyRows = $ds->getRows();
        
        $alias = $classInfo['CLASSNAME'] ? $classInfo['CLASSNAME'] : StringDataType::substringAfter($prototypeClass, '\\', '', false, true);
        $prototype_schema = $schema::getSchemaName();
        $title = $classInfo['TITLE'];
        $description = $classInfo['DESCRIPTION'];
        
        $headingHashes = str_pad('#', $this->headingLevel, '#', STR_PAD_RIGHT);
        $markdown = <<<MD
{$this->buildMarkdownHeading($title, $this->headingLevel)}

PHP class: {$prototypeClass}
File path: {$filepathRelative}

{$description}

{$this->buildMarkdownHeading('Properties', $this->headingLevel + 1)}

{$this->buildMarkdownTableForProperties($propertyRows, $this->headingLevel + 2)}

{$this->buildMarkdownHeading('Presets', $this->headingLevel + 1)}

MD;
        return $markdown;
    }
    
    protected function buildMarkdownHeading(string $title, int $level) : string
    {
        $headingHashes = str_pad('#', $level, '#', STR_PAD_RIGHT);
        return $headingHashes . ' ' . $title;
    }
    
    protected function buildMarkdownTableForProperties(array $propertyRows, int $headingLevel) : string
    {
        $md = '';
        foreach ($propertyRows as $propertyRow) {
            $md .= "\n" . $this->buildMarkdownTableRowForProperties($propertyRow, $headingLevel);
        }
        return $md;
    }
    
    protected function buildMarkdownTableRowForProperties(array $propertyRow, int $headingLevel) : string
    {
        $links = '';
        
        // \exface\Core\Widgets\DataColumn[]
        // \exface\Core\Widgets\DataColumn
        $type = $propertyRow['TYPE'];
        if (str_starts_with($type, '\\')) {
            $linkedClass = rtrim($type, "[]");
            $linkName = PhpClassDataType::findClassNameWithoutNamespace($linkedClass);
            $linkUrl = 'UXON_prototypes.md?selector=' . urlencode($linkedClass);
            $links = <<<MD

Read more about [{$linkName}]({$linkUrl})
MD;
        }
        return <<<MD

{$this->buildMarkdownHeading('Property `' . $propertyRow['PROPERTY'] . '`', $headingLevel + 1)}
{$propertyRow['TITLE']}{$links}

{$propertyRow['DESCRIPTION']}
MD;
    }
    
    public function getWorkbench()
    {
        return $this->selector->getWorkbench();
    }
}