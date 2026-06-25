<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Formulas\FormulaInterface;
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
    private string $prototypeClass;
    private string $filepathRelative;
    private string $componentType;
    private ?string $propertyObject;
    private array $classInfo = [];
    private array $propertyRows = [];
    
    /**
     * Examples:
     * - `\exface\Core\CommonLogic\DataSheets\DataSheet`
     * - `exface/core/CommonLogic/DataSheets/DataSheet.php`
     * 
     * @param string $prototypeClassOrFilepath
     */
    public function __construct(WorkbenchInterface $workbench, string $prototypeClassOrFilepath, int $headingLevel = 1)
    {
        $this->selector = new UxonPrototypeSelector($workbench, $prototypeClassOrFilepath);
        $this->headingLevel = $headingLevel;
        $this->init();
    }

    /**
     * Loads and caches all metadata required to render markdown and to expose class/property details.
     *
     * @return $this
     */
    public function init(): self
    {
        $selector = $this->selector;
        if ($selector->isFilepath()) {
            $this->filepathRelative = $selector->toString();
            $this->prototypeClass = PhpFilePathDataType::findClassInFile($this->filepathRelative);
        } else {
            $this->prototypeClass = $selector->toString();
            $this->filepathRelative = PhpFilePathDataType::findFileOfClass($this->prototypeClass);
        }

        switch (true) {
            case is_a($this->prototypeClass, iCanBeConvertedToUxon::class, true):
                $entityObject = 'exface.Core.UXON_ENTITY_ANNOTATION';
                $schemaClass = $this->prototypeClass::getUxonSchemaClass() ?? UxonSchema::class;
                $schema = new $schemaClass($selector->getWorkbench());
                $this->componentType = mb_ucfirst($schema::getSchemaName());
                if ($schema instanceof QueryBuilderSchema) {
                    $this->propertyObject = 'exface.Core.UXON_QUERY_BUILDER_ANNOTATION';
                } else {
                    $this->propertyObject = 'exface.Core.UXON_PROPERTY_ANNOTATION';
                }
                break;
            case is_a($this->prototypeClass, FormulaInterface::class, true):
                $entityObject = 'exface.Core.UXON_ENTITY_ANNOTATION';
                $this->propertyObject = null;
                $this->componentType = 'Formula';
                break;
            default:
                throw new RuntimeException('Class "' . $this->prototypeClass . '" does not exist or is not a UXON prototype');
        }

        // Read prototype class annotations.
        $dsClass = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $entityObject);
        $dsClass->getColumns()->addMultiple([
            'CLASSNAME',
            'NAME',
            'TITLE',
            'DESCRIPTION'
        ]);
        $dsClass->getFilters()->addConditionFromString('FILE', $this->filepathRelative);
        $dsClass->dataRead();
        $this->classInfo = $dsClass->getRow(0) ?? [];

        // Read property annotations.
        if ($this->propertyObject !== null) {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->propertyObject);
            $ds->getColumns()->addMultiple([
                'PROPERTY',
                'TYPE',
                'TEMPLATE',
                'DEFAULT',
                'TITLE',
                'REQUIRED',
                'DESCRIPTION'
            ]);
            $ds->getFilters()->addConditionFromString('FILE', $this->filepathRelative, ComparatorDataType::EQUALS);
            $ds->getSorters()->addFromString('PROPERTY', SortingDirectionsDataType::ASC);

            if ($schema instanceof QueryBuilderSchema) {
                $ds->getColumns()->addFromExpression('TARGET');
                $ds->getFilters()->addConditionFromString('TARGET', $schema->getLevel(), ComparatorDataType::EQUALS);
            }

            try {
                $ds->dataRead();
            } catch (\Throwable $e) {
                // Keep existing behavior: do not fail rendering if property annotations cannot be read.
            }

            $this->propertyRows = $ds->getRows();
        }

        return $this;
    }
    
    public function getMarkdown(): string
    {
        $prototypeClass = $this->getPrototypeClass();
        $filepathRelative = $this->getFilepathRelative();
        $alias = $this->getAlias();
        $title = $this->getTitle();
        $description = $this->getDescription();
        $propertyRows = $this->getPropertyRows();
        $componentType = $this->getComponentType();
        
        $markdown = <<<MD
{$this->buildMarkdownHeading($componentType . ' ' . $alias, $this->headingLevel)}

{$title}

- PHP class: `{$prototypeClass}`
- File path: `{$filepathRelative}`

{$description}

{$this->buildMarkdownTableForProperties($propertyRows, $this->headingLevel + 2)}

{$this->buildMarkdownPresets([], $this->headingLevel + 2)}

MD;
        return $markdown;
    }

    public function getPrototypeClass(): string
    {
        return $this->prototypeClass;
    }

    public function getFilepathRelative(): string
    {
        return $this->filepathRelative;
    }

    public function getComponentType(): string
    {
        return $this->componentType;
    }

    public function getClassInfo(): array
    {
        return $this->classInfo;
    }

    public function getPropertyRows(): array
    {
        return $this->propertyRows;
    }

    public function getAlias(): string
    {
        $className = $this->classInfo['CLASSNAME'] ?? null;
        if ($className !== null && $className !== '') {
            return $className;
        }
        return StringDataType::substringAfter($this->prototypeClass, '\\', '', false, true);
    }

    public function getTitle(): string
    {
        return $this->classInfo['TITLE'] ?? ($this->classInfo['NAME'] ?? '');
    }

    public function getDescription(): string
    {
        return $this->classInfo['DESCRIPTION'] ?? '';
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
        if ($md !== '') {
            $md = $this->buildMarkdownHeading('Properties', $this->headingLevel + 1) . "\n" . $md;
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
`Type : {$propertyRow['TYPE']}`

{$propertyRow['TITLE']}{$links}
{$propertyRow['DESCRIPTION']}
MD;
    }
    
    protected function buildMarkdownPresets(array $presetRows, int $headingLevel) : string
    {
        // TODO
        // {$this->buildMarkdownHeading('Presets', $this->headingLevel + 1)}
        return '';
    }
    
    public function getWorkbench()
    {
        return $this->selector->getWorkbench();
    }
}