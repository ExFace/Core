<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Utils\GanttXlsxBuilder;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\WidgetInterface;
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
    private const DEFAULT_BASIC_INFO_COLUMNS = [
        'Mast__MastZuBauabschnitt__Bauabschnitt__LABEL_LIST_DISTINCT' => 'Leitungsabschnitt',
        'LABEL' => 'Verortung',
        'Mast__MastZuLeitungsabschnitt__Leitungsabschnitt__LABEL_LIST_DISTINCT' => 'Bauabschnitt',
        'Mast__Bautyp__LABEL' => 'Bautyp',
        'DatumStartGruendung' => 'Start 1IA',
        'VerortungStatus__ZeitlicherStatus__WertAggregation' => 'Zeitlicher Status',
    ];

    private const DEFAULT_STATUS_INFO_COLUMNS = [
        'FS_GBMDB' => 'Gesamtfortschritt',
        'BAM_SMF' => 'Stellen Mastfuß (Stahl Unterteil)',
        'BAM_SMO' => 'Stocken Mast (Stahl Oberteil & Ketten)',
        'BAM_VMM' => 'Vormontage Mast (Stahl Oberteil)',
        'BAM_EZA' => 'Errichtung Zuwegung & Arbeitsflächen (erstmalig)',
        'BAM_AS1' => 'Armatur & Seilzug 1. SK',
        'BAM_REA' => 'LWL / ES / Restarbeiten',
        'BAM_FUN' => 'Fundament(-sanierung)',
        'KN_DFS' => 'Dingliche Flächensicherung',
        'KN_TFS' => 'Temporäre Flächensicherung',
        'KN_PL' => 'Planung',
    ];

    private array $basicInfoColumns = [];
    private array $statusInfoColumns = [];

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
     * Suppresses the generic JSON header because the specialized builder writes all XLSX headers.
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader()
     */
    protected function writeHeader(array $exportedColumns): array
    {
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
            (new GanttXlsxBuilder())->build($mappedData, $this->getFilePathAbsolute());
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
        $basicMapping = array_replace(self::DEFAULT_BASIC_INFO_COLUMNS, $this->basicInfoColumns);
        $statusMapping = array_replace(self::DEFAULT_STATUS_INFO_COLUMNS, $this->statusInfoColumns);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result[] = [
                'BasicInfo' => $this->mapSection($row, $basicMapping),
                'VerortungZuMassnahmeSichtbar' => [
                    'rows' => $this->mapTaskRows($row[$taskColumns['nested']] ?? [], $taskColumns),
                ],
                'StatusInfo' => $this->mapSection($row, $statusMapping),
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

        $timeStatusColor = 'VerortungStatus__ZeitlicherStatus__Farbe';
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

        $trigger = $this->getWidgetDefinedIn();
        if (! $trigger instanceof iUseInputWidget || ! ($inputWidget = $trigger->getInputWidget()) instanceof Gantt) {
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
     * Map Gantt columns to the basic-information captions in the workbook.
     * Use Gantt data column names as keys and the desired workbook captions as values. Supplied
     * entries override the built-in mapping, so the mapping can be completed incrementally.
     *
     * @uxon-property basic_info_columns
     * @uxon-type {string => string}
     *
     * @param UxonObject $mapping
     * @return ExportGanttXLSX
     */
    public function setBasicInfoColumns(UxonObject $mapping): ExportGanttXLSX
    {
        $this->basicInfoColumns = $mapping->toArray();
        return $this;
    }

    /**
     * Map Gantt columns to the status captions in the workbook.
     * Use Gantt data column names as keys and the desired workbook captions as values. A companion
     * color column named `_<data column name>Farbe` is applied automatically when available.
     *
     * @uxon-property status_info_columns
     * @uxon-type {string => string}
     *
     * @param UxonObject $mapping
     * @return ExportGanttXLSX
     */
    public function setStatusInfoColumns(UxonObject $mapping): ExportGanttXLSX
    {
        $this->statusInfoColumns = $mapping->toArray();
        return $this;
    }
}
