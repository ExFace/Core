<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Uxon\QueryBuilderSchema;
use exface\Core\Uxon\UxonSchema;

/**
 * Generates a Markdown documentation page for a UXON prototype class.
 *
 * The printer accepts either a fully qualified PHP class name or a relative PHP
 * file path to a prototype class. During initialization it resolves the matching
 * class/file pair, detects the UXON component type and reads the annotation
 * metadata from the configured workbench via DataSheets.
 *
 * Supported input examples:
 * - `\exface\Core\CommonLogic\DataSheets\DataSheet`
 * - `exface/core/CommonLogic/DataSheets/DataSheet.php`
 *
 * The generated markdown contains:
 * - the UXON component type and alias as heading
 * - the annotation title
 * - the resolved PHP class
 * - the resolved relative file path
 * - the annotation description
 * - a generated properties section based on UXON property annotations
 *
 * Example output:
 *
 * ```md
 * # DataSheet DataSheet
 *
 * A data sheet is...
 *
 * - PHP class: `\exface\Core\CommonLogic\DataSheets\DataSheet`
 * - File path: `exface/core/CommonLogic/DataSheets/DataSheet.php`
 *
 * ## Properties
 *
 * ### Property `meta_object`
 * `Type : string`
 *
 * The object of the Data Sheet
 *
 * It is important because...
 *
 * ### Presets
 * ```
 *
 * The class keeps resolved prototype metadata as scalar properties so it can be
 * accessed after initialization through getters:
 * - getPrototypeClass()
 * - getFilepathRelative()
 * - getComponentType()
 * - getAlias()
 * - getTitle()
 * - getDescription()
 *
 * Property annotation data is kept internally as a DataSheet instead of cached
 * row arrays. The markdown renderer extracts the rows only when the properties
 * section is built.
 *
 * Extension points:
 * - buildMarkdownHeading() can be overridden to change heading formatting.
 * - buildMarkdownTableForProperties() can be overridden to change property rendering.
 * - buildMarkdownTableRowForProperties() can be overridden to change one property block.
 * - buildMarkdownPresets() is currently a placeholder for future preset rendering.
 *
 * Editable output parts:
 * - the base heading level via constructor argument `$headingLevel`
 * - property section rendering via protected build methods
 * - preset rendering via buildMarkdownPresets()
 *
 * Output:
 * - getMarkdown() returns the complete markdown document as string.
 */
class UxonPrototypeMarkdownPrinter
{
    private PrototypeSelectorInterface $selector;
    private int $headingLevel;
    private string $prototypeClass;
    private string $filepathRelative;
    private string $componentType;
    private ?string $propertyObject = null;
    private string $alias = '';
    private string $title = '';
    private string $description = '';

    /**
     * @var mixed|null
     */
    private $classDataSheet = null;

    /**
     * @var mixed|null
     */
    private $propertyDataSheet = null;

    public function __construct(WorkbenchInterface $workbench, string $prototypeClassOrFilepath, int $headingLevel = 1)
    {
        $this->selector = new UxonPrototypeSelector($workbench, $prototypeClassOrFilepath);
        $this->headingLevel = $headingLevel;
        $this->init();
    }

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
                $this->propertyObject = $schema instanceof QueryBuilderSchema
                    ? 'exface.Core.UXON_QUERY_BUILDER_ANNOTATION'
                    : 'exface.Core.UXON_PROPERTY_ANNOTATION';
                break;

            case is_a($this->prototypeClass, FormulaInterface::class, true):
                $entityObject = 'exface.Core.UXON_ENTITY_ANNOTATION';
                $schema = null;
                $this->propertyObject = null;
                $this->componentType = 'Formula';
                break;

            default:
                throw new RuntimeException('Class "' . $this->prototypeClass . '" does not exist or is not a UXON prototype');
        }

        $this->classDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $entityObject);
        $this->classDataSheet->getColumns()->addMultiple([
            'CLASSNAME',
            'NAME',
            'TITLE',
            'DESCRIPTION'
        ]);
        $this->classDataSheet->getFilters()->addConditionFromString('FILE', $this->filepathRelative);
        $this->classDataSheet->dataRead();

        $classInfo = $this->classDataSheet->getRow(0) ?? [];
        $className = $classInfo['CLASSNAME'] ?? null;

        $this->alias = $className !== null && $className !== ''
            ? $className
            : StringDataType::substringAfter($this->prototypeClass, '\\', '', false, true);
        $this->title = $classInfo['TITLE'] ?? ($classInfo['NAME'] ?? '');
        $this->description = $classInfo['DESCRIPTION'] ?? '';

        if ($this->propertyObject !== null) {
            $this->propertyDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->propertyObject);
            $this->propertyDataSheet->getColumns()->addMultiple([
                'PROPERTY',
                'TYPE',
                'TEMPLATE',
                'DEFAULT',
                'TITLE',
                'REQUIRED',
                'DESCRIPTION'
            ]);
            $this->propertyDataSheet->getFilters()->addConditionFromString('FILE', $this->filepathRelative, ComparatorDataType::EQUALS);
            $this->propertyDataSheet->getSorters()->addFromString('PROPERTY', SortingDirectionsDataType::ASC);

            if ($schema instanceof QueryBuilderSchema) {
                $this->propertyDataSheet->getColumns()->addFromExpression('TARGET');
                $this->propertyDataSheet->getFilters()->addConditionFromString('TARGET', $schema->getLevel(), ComparatorDataType::EQUALS);
            }

            try {
                $this->propertyDataSheet->dataRead();
            } catch (\Throwable $e) {
                // Keep existing behavior: do not fail rendering if property annotations cannot be read.
            }
        }

        return $this;
    }

    public function getMarkdown(): string
    {
        $markdown = <<<MD
{$this->buildMarkdownHeading($this->getComponentType() . ' ' . $this->getAlias(), $this->headingLevel)}

{$this->getTitle()}

- PHP class: `{$this->getPrototypeClass()}`
- File path: `{$this->getFilepathRelative()}`

{$this->getDescription()}

{$this->buildMarkdownTableForProperties($this->getPropertyDataSheet(), $this->headingLevel + 2)}

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

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function getClassDataSheet()
    {
        return $this->classDataSheet;
    }

    protected function getPropertyDataSheet()
    {
        return $this->propertyDataSheet;
    }

    protected function buildMarkdownHeading(string $title, int $level): string
    {
        $headingHashes = str_pad('#', $level, '#', STR_PAD_RIGHT);
        return $headingHashes . ' ' . $title;
    }

    protected function buildMarkdownTableForProperties(?DataSheetInterface $propertyDataSheet, int $headingLevel): string
    {
        if ($propertyDataSheet === null) {
            return '';
        }

        $md = '';
        foreach ($propertyDataSheet->getRows() as $propertyRow) {
            $md .= "\n" . $this->buildMarkdownTableRowForProperties(
                    $propertyRow['PROPERTY'] ?? '',
                    $propertyRow['TYPE'] ?? '',
                    $propertyRow['TITLE'] ?? '',
                    $propertyRow['DESCRIPTION'] ?? '',
                    $headingLevel
                );
        }

        if ($md !== '') {
            $md = $this->buildMarkdownHeading('Properties', $this->headingLevel + 1) . "\n" . $md;
        }

        return $md;
    }

    protected function buildMarkdownTableRowForProperties(
        string $property,
        string $type,
        string $title,
        string $description,
        int $headingLevel
    ): string {
        $links = '';

        if ($type !== '' && str_starts_with($type, '\\')) {
            $linkedClass = rtrim($type, '[]');
            $linkName = PhpClassDataType::findClassNameWithoutNamespace($linkedClass);
            $linkUrl = 'UXON_prototypes.md?selector=' . urlencode($linkedClass);
            $links = <<<MD

Read more about [{$linkName}]({$linkUrl})
MD;
        }

        return <<<MD


{$this->buildMarkdownHeading('Property `' . $property . '`', $headingLevel + 1)}
`Type : {$type}`

{$title}{$links}
{$description}
MD;
    }

    protected function buildMarkdownPresets(array $presetRows, int $headingLevel): string
    {
        // TODO
        // {$this->buildMarkdownHeading('Presets', $this->headingLevel + 1)}
        return '';
    }

    public function getWorkbench(): WorkbenchInterface
    {
        return $this->selector->getWorkbench();
    }
}