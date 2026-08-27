<?php
namespace exface\Core\Actions;

use exface\Core\Actions\Traits\iHaveXLSXPrintSettings;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Utils\GanttXlsxBuilder;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
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
 * Use `basic_info_columns` and `status_info_columns` to map Gantt data column names to the captions
 * expected by the spreadsheet.
 *
 * @author Sergej Riel
 */
class ExportGanttXLSX extends ExportJSON
{
    use iHaveXLSXPrintSettings;

    private const TIME_STATUS_COLOR_COLUMN = 'VerortungStatus__ZeitlicherStatus__Farbe';

    private array $basicInfoColumns = [];
    private array $statusInfoColumns = [];
    private array $basicInfoColumnOverrides = [];
    private array $statusInfoColumnOverrides = [];
    private array $semanticColors = [];
    private bool $mergeCells = false;
    private float $textColorPreference = 0.5;
    private int $freezeColumns = 0;
    private int $basicInfoColumnCount = 6;

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
     * Splits requested business columns into basic and status mappings for the spreadsheet builder.
     *
     * System columns and the nested task column are excluded before the configured number of
     * columns is assigned to `BasicInfo`; all remaining columns are assigned to `StatusInfo`.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader()
     */
    protected function writeHeader(array $exportedColumns): array
    {
        $this->basicInfoColumns = [];
        $this->statusInfoColumns = [];
        $basicInfoColumnOverrides = $this->getBasicInfoColumns();
        $statusInfoColumnOverrides = $this->getStatusInfoColumns();
        $basicInfoColumnCount = $this->getBasicInfoColumnCount();
        $businessColumnIndex = 0;

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

            $isBasicInfo = $businessColumnIndex < $basicInfoColumnCount;
            $overrides = $isBasicInfo ? $basicInfoColumnOverrides : $statusInfoColumnOverrides;
            $caption = $overrides[$source] ?? $column->getCaption() ?? $source;
            if ($caption === '') {
                $caption = $source;
            }

            if ($isBasicInfo) {
                $this->basicInfoColumns[$source] = $caption;
            } else {
                $this->statusInfoColumns[$source] = $caption;
            }
            $businessColumnIndex++;
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
        try {
            (new GanttXlsxBuilder(
                $this->semanticColors,
                $this->getMergeCells(),
                $this->textColorPreference,
                $this->getFreezeColumns(),
                $this->getDefaultTaskDurationDays(),
                $this->getXLSXPrintSettings()
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
     * Converts raw Gantt rows into the three sections consumed by the spreadsheet builder.
     *
     * @param array<int|string, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
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
                'BasicInfo' => $this->mapSection($row, $this->basicInfoColumns),
                'VerortungZuMassnahmeSichtbar' => [
                    'rows' => $this->mapTaskRows($row[$taskColumns['nested']] ?? [], $taskColumns),
                ],
                'StatusInfo' => $this->mapSection($row, $this->statusInfoColumns),
            ];
        }

        if ($result === []) {
            throw new ActionInputMissingError($this, 'Cannot export Gantt data: no valid rows were read.');
        }

        return $result;
    }

    /**
     * Maps configured source columns and companion color columns to spreadsheet fields.
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $mapping
     * @return array<string, mixed>
     */
    private function mapSection(array $row, array $mapping): array
    {
        $result = [];
        foreach ($mapping as $source => $target) {
            $result[$target] = $row[$source] ?? null;
            $colorSource = '_' . $source . 'Farbe';
            if (array_key_exists($colorSource, $row)) {
                $result[$target . '_Farbe'] = $row[$colorSource];
            }
        }

        $timeStatusColor = self::TIME_STATUS_COLOR_COLUMN;
        if (isset($mapping['VerortungStatus__ZeitlicherStatus__WertAggregation'])
            && array_key_exists($timeStatusColor, $row)) {
            $target = $mapping['VerortungStatus__ZeitlicherStatus__WertAggregation'];
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
     * Map Gantt columns to the basic-information captions in the workbook.
     * Use requested Gantt data column names as keys and the desired workbook captions as values.
     * Supplied entries override captions in the configured number of BasicInfo columns.
     *
     * @uxon-property basic_info_columns
     * @uxon-type {string => string}
     *
     * @param UxonObject $mapping
     * @return ExportGanttXLSX
     */
    public function setBasicInfoColumns(UxonObject $mapping): ExportGanttXLSX
    {
        $this->basicInfoColumnOverrides = $mapping->toArray();
        return $this;
    }

    /**
     * Returns the configured caption overrides for BasicInfo columns.
     *
     * @return array<string, string>
     */
    public function getBasicInfoColumns(): array
    {
        return $this->basicInfoColumnOverrides;
    }

    /**
     * Map Gantt columns to the status captions in the workbook.
     * Use requested Gantt data column names as keys and the desired workbook captions as values.
     * Supplied entries override captions after the configured number of BasicInfo columns. A
     * companion color column named `_<data column name>Farbe` is applied automatically when
     * available.
     *
     * @uxon-property status_info_columns
     * @uxon-type {string => string}
     *
     * @param UxonObject $mapping
     * @return ExportGanttXLSX
     */
    public function setStatusInfoColumns(UxonObject $mapping): ExportGanttXLSX
    {
        $this->statusInfoColumnOverrides = $mapping->toArray();
        return $this;
    }

    /**
     * Returns the configured caption overrides for StatusInfo columns.
     *
     * @return array<string, string>
     */
    public function getStatusInfoColumns(): array
    {
        return $this->statusInfoColumnOverrides;
    }

    /**
     * Define how many requested business columns form the BasicInfo section.
     *
     * All subsequent business columns form the StatusInfo section.
     *
     * @uxon-property basic_info_column_count
     * @uxon-type integer
     * @uxon-default 6
     *
     * @param int $value
     * @return ExportGanttXLSX
     */
    public function setBasicInfoColumnCount(int $value): ExportGanttXLSX
    {
        if ($value < 0) {
            throw new ActionConfigurationError($this, '`basic_info_column_count` cannot be negative.');
        }
        $this->basicInfoColumnCount = $value;
        return $this;
    }

    /**
     * Returns the number of requested business columns assigned to BasicInfo.
     */
    public function getBasicInfoColumnCount(): int
    {
        return $this->basicInfoColumnCount;
    }

    /**
     * Merge location information vertically when overlapping tasks occupy multiple rows.
     *
     * Keep this disabled to repeat the BasicInfo and StatusInfo values in every task lane instead.
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
}