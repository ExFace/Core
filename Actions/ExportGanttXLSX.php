<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Actions\XLSXHeaderGroups;
use exface\Core\CommonLogic\Actions\XLSXPrintSettings;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Utils\GanttXlsxBuilder;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Factories\FormulaFactory;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Gantt;

/**
 * Export all data matching a Gantt widget's current filters as a formatted XLSX timeline.
 *
 * Add this action directly to a Gantt button. The export uses the standard server-side export
 * lifecycle, so the browser only sends the active filters, sorters, and widget context. All rows
 * and nested task data are then read by the server.
 *
 * Use `header_groups` to divide the exported columns into formatted sections and
 * `id_attribute_alias` to select the column repeated before the Gantt timeline.
 *
 * @author Sergej Riel
 */
class ExportGanttXLSX extends ExportJSON
{
    private const TIME_STATUS_COLOR_COLUMN = 'VerortungStatus__ZeitlicherStatus__Farbe';

    private array $columns = [];
    /** @var array<string, string|null> */
    private array $columnAttributeAliases = [];
    private array $semanticColors = [];
    private bool $mergeCells = false;
    private float $textColorPreference = 0.5;
    private int $freezeColumns = 0;
    private ?XLSXPrintSettings $xlsxPrintSettings = null;
    /** @var list<XLSXHeaderGroups> */
    private array $headerGroups = [];
    private ?string $headingColor = null;
    private ?string $idAttributeAlias = null;

    /**
     * Initializes the action for a non-lazy XLSX export that requires the complete result set.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::FILE_EXCEL_O);
        $this->setLazyExport(false);
    }

    /**
     * Adds the temporal status color required by the workbook alongside the requested Gantt columns.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::getDataSheetToRead()
     */
    protected function getDataSheetToRead(TaskInterface $task): DataSheetInterface
    {
        $facade = $task->getFacade();
        if ($facade instanceof AbstractAjaxFacade) {
            $this->semanticColors = $facade->getSemanticColors();
            $this->textColorPreference = (float) $facade->getConfig()
                ->getOption('WIDGET.OBJECT_STATUS.TEXT_COLOR_PREFERENCE');
        }

        $dataSheet = parent::getDataSheetToRead($task);
        if (! $dataSheet->getColumns()->getByExpression(self::TIME_STATUS_COLOR_COLUMN)) {
            $dataSheet->getColumns()->addFromExpression(self::TIME_STATUS_COLOR_COLUMN);
        }
        return $dataSheet;
    }

    /**
     * Keeps every Gantt widget column available for mapping, including hidden nested task columns.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::isColumnExportable()
     */
    protected function isColumnExportable(WidgetInterface $col): bool
    {
        return true;
    }

    /**
     * Captures requested business columns in their export order for the spreadsheet builder.
     *
     * System columns and the nested task column are excluded.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader()
     */
    protected function writeHeader(array $exportedColumns): array
    {
        $this->columns = [];
        $this->columnAttributeAliases = [];

        foreach ($exportedColumns as $column) {
            if (! $column instanceof DataColumn
                || $column->isSystem()
                || $column->hasNestedData()) {
                continue;
            }

            $source = $column->getDataColumnName();
            if ($source === '') {
                continue;
            }

            $caption = $column->getCaption() ?? $source;
            if ($caption === '') {
                $caption = $source;
            }
            if (in_array($caption, $this->columns, true)) {
                throw new ActionConfigurationError(
                    $this,
                    'Cannot export multiple Gantt columns with the same caption "' . $caption . '".'
                );
            }

            $this->columns[$source] = $caption;
            $this->columnAttributeAliases[$source] = $column->getAttributeAlias();
        }

        return [];
    }

    /**
     * Defers row output until the complete data set can be mapped into Gantt lanes.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $columnNames)
    {
    }

    /**
     * Keep lazy export disabled so the timeline can use the complete result set.
     *
     * @uxon-property lazy_export
     * @uxon-type boolean
     * @uxon-default false
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::setLazyExport()
     * @param bool $value
     * @return ExportJSON
     */
    public function setLazyExport(bool $value): ExportJSON
    {
        if ($value === true) {
            throw new ActionConfigurationError($this, 'The Gantt XLSX export does not support `lazy_export: true` because its timeline requires the complete result set.');
        }
        return parent::setLazyExport(false);
    }

    /**
     * Maps the fully read Gantt DataSheet and writes the formatted workbook to the inherited path.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeFileResult()
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        if ($dataSheet->isEmpty()) {
            throw new ActionInputMissingError($this, 'Cannot export Gantt data: no rows match the current filters.');
        }

        $mappedData = ['Verortungen' => $this->mapGanttRows($dataSheet->getRows())];
        $idColumn = $this->resolveIdColumnCaption();
        try {
            (new GanttXlsxBuilder(
                $this->semanticColors,
                $this->getMergeCells(),
                $this->textColorPreference,
                $this->getFreezeColumns(),
                $this->getDefaultTaskDurationDays(),
                $this->getXLSXPrintSettings()->toArray(),
                $this->getWorkbookTranslations(),
                $this->getHeadingColors($dataSheet),
                array_map(
                    static fn(XLSXHeaderGroups $group): array => $group->toArray(),
                    $this->getHeaderGroups()
                ),
                $idColumn
            ))
                ->build($mappedData, $this->getFilePathAbsolute());
        } catch (\Throwable $e) {
            throw new ActionRuntimeError($this, 'Unable to create the Gantt XLSX export.', null, $e);
        }
    }

    /**
     * Returns the MIME type used by the inherited file path and download result handling.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::getMimeType()
     */
    public function getMimeType(): ?string
    {
        return 'application/vnd.openxmlformats-officedocument. spreadsheetml.sheet';
    }

    /**
     * Converts raw Gantt rows into generic column values and nested tasks for the builder.
     * 
     * @param array $rows
     * @return array
     */
    private function mapGanttRows(array $rows): array
    {
        $result = [];
        $taskColumns = $this->getTaskColumnNames();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result[] = [
                'Columns' => $this->mapColumns($row),
                'VerortungZuMassnahmeSichtbar' => [
                    'rows' => $this->mapTaskRows($row[$taskColumns['nested']] ?? [], $taskColumns),
                ],
            ];
        }

        if ($result === []) {
            throw new ActionInputMissingError($this, 'Cannot export Gantt data: no valid rows were read.');
        }

        return $result;
    }


    /**
     * Maps exported columns and their companion colors to generic spreadsheet fields.
     * 
     * @param array $row
     * @return array
     */
    private function mapColumns(array $row): array
    {
        $result = [];
        foreach ($this->columns as $source => $target) {
            $result[$target] = $row[$source] ?? null;
            $colorSource = '_' . $source . 'Farbe';
            if (array_key_exists($colorSource, $row)) {
                $result[$target . '_Farbe'] = $row[$colorSource];
            }
        }

        $timeStatusColor = self::TIME_STATUS_COLOR_COLUMN;
        if (isset($this->columns['VerortungStatus__ZeitlicherStatus__WertAggregation'])
            && array_key_exists($timeStatusColor, $row)) {
            $target = $this->columns['VerortungStatus__ZeitlicherStatus__WertAggregation'];
            $result[$target . '_Farbe'] = $row[$timeStatusColor];
        }

        return $result;
    }

    /**
     * Converts a nested Gantt task sheet into the builder's stable task field names.
     *
     * @param mixed $nestedData
     * @param array{nested:string,start:string,end:string,title:string,color:string} $columns
     * @return list<array<string, mixed>>
     */
    private function mapTaskRows($nestedData, array $columns): array
    {
        if (! is_array($nestedData)) {
            return [];
        }
        $rows = $nestedData['rows'] ?? $nestedData;
        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $result[] = [
                'DurchfuehrungVon' => $row[$columns['start']] ?? null,
                'DurchfuehrungBis' => $row[$columns['end']] ?? null,
                'LABEL' => $row[$columns['title']] ?? '',
                'FarbeAnzeige' => $row[$columns['color']] ?? null,
            ];
        }
        return $result;
    }

    /**
     * Resolves nested task column names from the defining Gantt with payload-compatible fallbacks.
     *
     * @return array{nested:string,start:string,end:string,title:string,color:string}
     */
    private function getTaskColumnNames(): array
    {
        $columns = [
            'nested' => 'VerortungZuMassnahmeSichtbar',
            'start' => 'Massnahme__DurchfuehrungVon',
            'end' => 'Massnahme__DurchfuehrungBis',
            'title' => 'Massnahme__MassnahmeTyp__LABEL',
            'color' => 'Massnahme__MassnahmeTyp__FarbeAnzeige',
        ];

        $inputWidget = $this->getInputGantt();
        if ($inputWidget === null) {
            return $columns;
        }

        $tasks = $inputWidget->getTasksConfig();
        if ($tasks->getNestedDataColumn() !== null) {
            $columns['nested'] = $tasks->getNestedDataColumn()->getDataColumnName();
        }
        $columns['start'] = $tasks->getStartTimeColumn()->getDataColumnName();
        if ($tasks->getEndTimeColumn() !== null) {
            $columns['end'] = $tasks->getEndTimeColumn()->getDataColumnName();
        }
        $columns['title'] = $tasks->getTitleColumn()->getDataColumnName();
        if ($tasks->getColorColumn() !== null) {
            $columns['color'] = $tasks->getColorColumn()->getDataColumnName();
        }
        return $columns;
    }

    /**
     * Returns the task fallback duration in whole days using the same calculation as UI5Gantt.
     */
    private function getDefaultTaskDurationDays(): int
    {
        $inputWidget = $this->getInputGantt();
        $defaultDurationHours = $inputWidget === null
            ? 48
            : $inputWidget->getTasksConfig()->getDefaultDurationHours(48);

        return (int) ceil($defaultDurationHours / 24);
    }

    /**
     * Translates every user-visible workbook label with the active Core locale.
     *
     * @return array<string, string>
     */
    private function getWorkbookTranslations(): array
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $prefix = 'WIDGET.GANTT_CHARD.EXCEL.';
        $translations = [];
        foreach ([
            'SHEET_TITLE',
            'GANTT',
            'YEAR',
            'QUARTER',
            'MONTH',
            'CALENDAR_WEEK',
            'EXECUTION_YEAR',
        ] as $key) {
            $translations[$key] = $translator->translate($prefix . $key);
        }
        for ($month = 1; $month <= 12; $month++) {
            $key = 'MONTH_' . str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $translations[$key] = $translator->translate($prefix . $key);
        }
        return $translations;
    }

    /**
     * Returns the Gantt widget that supplies the action input.
     *
     * @return Gantt|null
     */
    private function getInputGantt(): ?Gantt
    {
        $trigger = $this->getWidgetDefinedIn();
        if (! $trigger instanceof iUseInputWidget) {
            return null;
        }
        $inputWidget = $trigger->getInputWidget();
        return $inputWidget instanceof Gantt ? $inputWidget : null;
    }

    /**
     * Merge location information vertically when overlapping tasks occupy multiple rows.
     *
     * Keep this disabled to repeat exported column values in every task lane instead.
     *
     * @uxon-property merge_cells
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return ExportGanttXLSX
     */
    public function setMergeCells(bool $value): ExportGanttXLSX
    {
        $this->mergeCells = $value;
        return $this;
    }

    /**
     * Returns whether location information is merged across overlapping task lanes.
     */
    public function getMergeCells(): bool
    {
        return $this->mergeCells;
    }

    /**
     * Define how many columns from the left remain visible while scrolling through the workbook.
     *
     * Set this to `0` to freeze only the five header rows.
     *
     * @uxon-property freeze_columns
     * @uxon-type integer
     * @uxon-default 0
     *
     * @param int $value
     * @return ExportGanttXLSX
     */
    public function setFreezeColumns(int $value): ExportGanttXLSX
    {
        if ($value < 0) {
            throw new ActionConfigurationError($this, '`freeze_columns` cannot be negative.');
        }
        $this->freezeColumns = $value;
        return $this;
    }

    /**
     * Returns the number of columns frozen from the left.
     */
    public function getFreezeColumns(): int
    {
        return $this->freezeColumns;
    }

    /**
     * Configure page layout settings for the generated XLSX workbook.
     *
     * @uxon-property xlsx_print_settings
     * @uxon-type \exface\Core\CommonLogic\Actions\XLSXPrintSettings
     * @uxon-template {"orientation":"landscape","paper_size":9,"page_order":"downThenOver","scale":100,"page_margins":{"left":0.25,"right":0.25,"top":0.75,"bottom":0.75,"header":0.3,"footer":0.3}}
     *
     * @param UxonObject $uxon
     * @return $this
     */
    public function setXLSXPrintSettings(UxonObject $uxon): ExportGanttXLSX
    {
        $this->xlsxPrintSettings = new XLSXPrintSettings($this, $uxon);
        return $this;
    }

    /**
     * Returns the XLSX page layout settings, creating their defaults when not configured.
     *
     * @return XLSXPrintSettings
     */
    public function getXLSXPrintSettings(): XLSXPrintSettings
    {
        if ($this->xlsxPrintSettings === null) {
            $this->xlsxPrintSettings = new XLSXPrintSettings($this);
        }
        return $this->xlsxPrintSettings;
    }

    /**
     * Define consecutive visual groups for the exported columns.
     *
     * Group sizes are adjusted to the columns currently selected by the user. Missing trailing
     * columns shorten or omit groups, while additional columns are assigned to the last group.
     *
     * @uxon-property header_groups
     * @uxon-type \exface\Core\CommonLogic\Actions\XLSXHeaderGroups[]
     * @uxon-template [{"name":"","column_count":1,"column_width":13,"orientation":"horizontal"}]
     *
     * @param UxonObject $value
     * @return $this
     */
    public function setHeaderGroups(UxonObject $value): ExportGanttXLSX
    {
        $this->headerGroups = [];
        foreach ($value->toArray() as $groupUxon) {
            if (! is_array($groupUxon)) {
                throw new ActionConfigurationError($this, 'Every `header_groups` entry must be an object.');
            }
            $this->headerGroups[] = new XLSXHeaderGroups($this, new UxonObject($groupUxon));
        }
        return $this;
    }

    /**
     * Returns the configured header groups in their declared order.
     *
     * @return list<XLSXHeaderGroups>
     */
    public function getHeaderGroups(): array
    {
        return $this->headerGroups;
    }

    /**
     * Select the exported attribute copied into the dedicated column before the Gantt timeline.
     *
     * The selected attribute must be part of the columns included in the export. Leave this
     * property unset to use the first exported column.
     *
     * @uxon-property id_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return $this
     */
    public function setIdAttributeAlias(string $value): ExportGanttXLSX
    {
        $this->idAttributeAlias = $value;
        return $this;
    }

    /**
     * Returns the optional attribute alias used for the dedicated ID column.
     */
    public function getIdAttributeAlias(): ?string
    {
        return $this->idAttributeAlias;
    }

    /**
     * Resolves the dedicated ID column to its exported caption.
     */
    private function resolveIdColumnCaption(): string
    {
        if ($this->columns === []) {
            throw new ActionConfigurationError($this, 'The Gantt XLSX export requires at least one exported column.');
        }
        if ($this->idAttributeAlias === null) {
            return (string) reset($this->columns);
        }

        foreach ($this->columnAttributeAliases as $source => $attributeAlias) {
            if ($attributeAlias === $this->idAttributeAlias) {
                return $this->columns[$source];
            }
        }

        throw new ActionConfigurationError(
            $this,
            'The `id_attribute_alias` "' . $this->idAttributeAlias . '" is not included in the exported columns.'
        );
    }

    /**
     * Define the background color of exported column heading cells.
     *
     * Use placeholders to resolve a different color for each exported status column, for example
     * `=Lookup('ExcelHeadingColor', 'my.App.KPIDefinition', 'Code == [#~column:attribute_alias#]')`.
     *
     * @uxon-property heading_color
     * @uxon-type string
     *
     * @param string $colorOrFormula
     * @return ExportGanttXLSX
     */
    protected function setHeadingColor(string $colorOrFormula): ExportGanttXLSX
    {
        $this->headingColor = $colorOrFormula;
        return $this;
    }

    /**
     * Resolves the configured heading color for a specific exported DataSheet column.
     *
     * @param DataColumnInterface $dataSheetColumn
     * @return string|null
     */
    protected function getHeadingColor(DataColumnInterface $dataSheetColumn): ?string
    {
        if ($this->headingColor === null) {
            return null;
        }

        $color = $this->headingColor;
        if (! Expression::detectFormula($color)) {
            return $color;
        }

        $placeholders = [
            '~column:attribute_alias' => $dataSheetColumn->getAttributeAlias(),
            '~column:name' => $dataSheetColumn->getName(),
            '~column:formula' => $dataSheetColumn->getFormula(),
        ];
        $formulaString = StringDataType::replacePlaceholders($color, $placeholders);
        $formula = FormulaFactory::createFromString($this->getWorkbench(), $formulaString);
        $result = $formula->evaluate();

        return $result === null ? null : (string) $result;
    }

    /**
     * Resolves heading colors in the same order as the exported columns.
     *
     * @param DataSheetInterface $dataSheet
     * @return list<string|null>
     */
    private function getHeadingColors(DataSheetInterface $dataSheet): array
    {
        $colors = [];
        foreach (array_keys($this->columns) as $source) {
            $column = $dataSheet->getColumns()->get($source);
            $colors[] = $column instanceof DataColumnInterface
                ? $this->getHeadingColor($column)
                : null;
        }
        return $colors;
    }
}