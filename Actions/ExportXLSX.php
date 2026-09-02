<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Actions\ActionExportDataError;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\DataTypes\HexadecimalNumberDataType;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\CommonLogic\Utils\XLSXWriter;
use exface\Core\DataTypes\PriceDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\NumberEnumDataType;

/**
 * Exports data to an Excel file (XLSX).
 * 
 * The file will contain two sheets: 
 * 
 * - The first sheet contains data
 * - The second sheet contains context information like username, export time, filters used, etc.
 * 
 * The data will have captions as headers (alternatively attribute aliases if `use_attribute_alias_as_header` = TRUE).
 * By default, filtering will be enabled for all columns and the first row (headers) will be frozen. These features
 * are controlled by the properties `enable_column_filters` and `freeze_header_row` respectively.
 * 
 * ## What data will be exported?
 * 
 * You can explicitly define the columns to be exported via `columns`. If you don't and the action is placed in a data
 * widget (e.g. a `DataTable`), it will take all exportable columns of that data widget. Thus, you can exclude table 
 * columns from the export by setting `exportable` to `false` in the column configuration. 
 * 
 * Unlike the other export formats, hidden columns are still written to the file here - they are only marked as
 * hidden in the spreadsheet. Only columns with `exportable` = `false` are excluded entirely.
 * 
 * ## How the data is read (and why it may take several steps)
 * 
 * The export always contains ALL rows that match the current filters - not just the rows that
 * are currently on screen. To stay fast and avoid running out of memory on large tables,
 * the data is fetched in several smaller batches ("requests") instead of one huge read. The
 * batches are then combined into a single Excel file. As a designer you normally don't have to think about this:
 * The action exports all matching rows, no matter how many there are, and the user gets one complete file.
 * 
 * If you do want to influence this behaviour, two settings let you do so:
 * 
 * - `limit_rows_per_request` - how many rows are fetched per batch (default 10000).
 * - `limit_time_per_request` - how long a single batch may take before it is aborted (default 300 seconds).
 * 
 * On top of the per-batch time limit, there is an overall time budget for the whole export. It is
 * not an action property, but the core config option `EXPORT.MAX_PROCESSING_TIME` (in seconds,
 * default 300). Before reading starts, the action samples a few rows, extrapolates how long the
 * full export would take and aborts up-front with a readable error if that estimate exceeds the
 * budget - so users get quick feedback instead of waiting for a request to time out. Set the
 * option to raise or lower this budget installation-wide. If you remove the option or set it to null the guard
 *  is disabled.
 * 
 * Whether the data can be split into batches depends on the exported object:
 * 
 * - Objects WITH a unique identifier (UID) - almost all business objects - are read batch by
 *   batch. The UID is used to reliably continue where the previous batch stopped, so no row is
 *   ever exported twice or skipped. This is the normal, memory-friendly case.
 * - Objects WITHOUT a UID cannot be split safely (there is no reliable way to tell the batches
 *   apart), so all their rows are read in one single request. For such objects `limit_rows_per_request`
 *   has no effect and very large exports may hit memory limits.
 * 
 * ### When should I change these settings?
 * 
 * You usually do not need to. Only adjust them if an export fails:
 * 
 * - "Allowed memory size exhausted" -> LOWER `limit_rows_per_request` (e.g. to 5000 or 1000) so
 *   each batch is smaller and uses less memory.
 * - "Maximum execution time exceeded" -> RAISE `limit_time_per_request` to give each batch more
 *   time to finish.
 * 
 * ## Data type handling
 * 
 * If the exported data uses custom data types, they can be mapped to Excel format expressions manually
 * using `data_type_map`.
 * 
 * ## Filename Placeholders
 * 
 * You can dynamically generate filenames based on aggregated data, by using placeholders in the property `filename`.
 * For example `"filename":"[#=Now('yyyy-MM-dd')#]_[#~data:Materialkategorie:LIST_DISTINCT#]"` could be used to include both
 * the current date and some information about the categories present in the export and result in a filename like `2024-09-10_Muffen`.
 * 
 * ### Supported placeholders:
 * 
 * - `[#=Formula()#]` Allows the use of formulas.
 * - `[#~data:attribute_alias:AGGREGATOR#]` Aggregates the data column for the given alias by applying the specified aggregator. See below for
 * a list of supported aggregators.
 * 
 * ### Supported aggregators:
 * 
 * - `SUM` Sums up all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 * - `AVG` Calculates the arithmetic mean of all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 * - `MIN` Gets the lowest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 * non-numeric values will be read as numerics or as 0, if they cannot be converted.
 * - `MAX` Gets the highest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *  non-numeric values will be read as numerics or as 0, if they cannot be converted.
 * - `COUNT` Counts the total number of rows in the column.
 * - `COUNT_DISTINCT` Counts the number of unique entries in the column, excluding empty rows.
 * - `LIST` Lists all non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 * - `LIST_DISTINCT` Lists all unique, non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 * 
 * ## Examples
 * 
 * Here is an example of the configuration for a machine-friendly export (no filters, no frozen rows, aliases as headers):
 * 
 * ```
 * 
 * {
 *     "alias": "exface.Core.ExportXLSX",
 *     "use_attribute_alias_as_header": true,
 *     "enable_column_filters": false,
 *     "freeze_header_row": false
 * }
 * 
 * ```
 * 
 * @author SFL
 * 
 */
class ExportXLSX extends ExportJSON
{
    const DATA_TYPE_STRING = 'string';
    
    private $dataTypeMap = [];

    private $rowNumberWritten = 0;
    
    private $enableColumnFilters = true;
    
    private $freezeHeaderRow = true;

    private $writer = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::FILE_EXCEL_O);
    }

    /**
     * {@inheritDoc}
     * 
     * XLSX still writes hidden columns (when exportable) - they are only marked as hidden in
     * the spreadsheet. So only explicitly non-exportable columns are excluded.
     * 
     * @see \exface\Core\Actions\ExportJSON::isColumnExportable()
     */
    protected function isColumnExportable(WidgetInterface $col) : bool
    {
        if ($col instanceof DataColumn) {
            return $col->isExportable(true);
        }
        return true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader()
     */
    protected function writeHeader(array $exportedColumns) : array
    {
        $headersAndTypes = [];
        $columnOptions = [];
        $colNames = [];
        $indexes = [];
        foreach ($exportedColumns as $widget) {
            if (! $this->isColumnExportable($widget)) {
                continue;
            }
            $colOptions = [];
            // Name der Spalte
            if ($this->getUseAttributeAliasAsHeader() === true && ($widget instanceof iShowDataColumn) && $widget->isBoundToDataColumn()) {
                $colHeader = $widget->getAttributeAlias();
            } else {
                $colHeader = $widget->getCaption();
            }
            $colId = $widget->getDataColumnName();

            if ($colHeader === '' || $colHeader === null) {
                $colHeader = $colId;
            }
            
            // Der Name muss einzigartig sein, sonst werden zu wenige Headerspalten
            // geschrieben.
            $idx = ($indexes[$colHeader] ?? 0) + 1;
            $indexes[$colHeader] = $idx;
            if ($idx > 1) {
                $colHeader .= ' (' . $idx . ')';
            }
            
            // Datentyp der Spalte
            switch (true) {
                case $widget instanceof iHaveValue:
                    $dataType = $widget->getValueDataType();
                    break;
                case $widget instanceof DataColumn:
                    $dataType = $widget->getDataType();
                    break;
                case ($widget instanceof iShowSingleAttribute) && $widget->isBoundToAttribute():
                    $dataType = $widget->getAttribute()->getDataType();
                    break;
                default:
                    $dataType = DataTypeFactory::createBaseDataType($this->getWorkbench());
                    break;
            }
            $headersAndTypes[$colHeader] = $this->getExcelDataType($dataType);
            
            // Width
            if ($dataType instanceof TimestampDataType || $dataType instanceof DateTimeDataType) {
                $colOptions['width'] = '19';
            } elseif ($dataType instanceof StringDataType || $dataType instanceof NumberEnumDataType) {
                $colOptions['width'] = '25';
            }
            
            // Visibility
            // if the column is hidden and wasn't explicitly set to be exportable it will be hidden in the xlsx
            if ($widget->isHidden() === true && ($widget instanceof DataColumn && $widget->isExportable(false) === false)) {
                $colOptions['hidden'] = true;
            }
            
            $columnOptions[] = $colOptions;
            
            $colNames[] = $colId;
        }
        
        $options =  [
            'font-style' => 'bold',
            'auto_filter' => $this->getEnableColumnFilters()
        ];
        
        if ($this->getFreezeHeaderRow() === true) {
            $options['freeze_rows'] = 1;
        }
        
        $this->getWriter()->writeSheetHeader($this->getExcelDataSheetName(), $headersAndTypes, $options, $columnOptions);
        return $colNames;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $headerKeys)
    {
        $rowCnt = $this->rowNumberWritten;
        foreach ($dataSheet->getRows() as $row) {
            $outRow = [];
            foreach ($headerKeys as $key) {
                $outRow[$key] = $row[$key];
            }
            if ($rowCnt >= $this->getWriter()::EXCEL_2007_MAX_ROW) {
                throw new ActionExportDataError($this, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.EXPORTDATA.ROWOVERFLOW', array(
                    '%number%' => $this->getWriter()::EXCEL_2007_MAX_ROW
                )));
            }
            $this->getWriter()->writeSheetRow($this->getExcelDataSheetName(), $outRow);
            $rowCnt++;
        }
        $this->rowNumberWritten = $rowCnt;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeFileResult()
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        $this->writeInfoExcelSheet($dataSheet);
        $this->getWriter()->writeToFile($this->getFilePathAbsolute());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::getWriter()
     * 
     * @return XLSXWriter
     */
    protected function getWriter()
    {
        if ($this->writer === null) {
            $this->writer = new XLSXWriter();
        }
        return $this->writer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::getMimeType()
     */
    public function getMimeType() : ?string
    {
        return 'application/vnd.openxmlformats-officedocument. spreadsheetml.sheet';
    }

    /**
     * Returns the name of the excel sheet containing the data.
     * 
     * @return string
     */
    protected function getExcelDataSheetName()
    {
        return $this->getApp()->getTranslator()->translate('ACTION.EXPORTXLSX.SHEET_DATA');
    }

    /**
     * Returns the name of the excel sheet containing general information.
     * 
     * @return string
     */
    protected function getExcelInfoSheetName()
    {
        return $this->getApp()->getTranslator()->translate('ACTION.EXPORTXLSX.SHEET_LEGEND');
    }

    /**
     * Write Excel Sheet2 with general information.
     * 
     * @param DataSheetInterface $dataSheet
     */
    protected function writeInfoExcelSheet(DataSheetInterface $dataSheet)
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        
        // Datentypen festlegen. Da in jeder Spalte verschiedene Datentypen vor-
        // kommem koennen werden alle verwendeten Spalten auf String gesetzt.
        $this->getWriter()->writeSheetHeader($this->getExcelInfoSheetName(), [
            $this->getExcelDataTypeDefault(),
            $this->getExcelDataTypeDefault(),
            $this->getExcelDataTypeDefault()
        ], ['suppress_row' => true], [['width' => '40'], ['width' => '40']]);
        
        // Benutzername
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.USERNAME'),
            $this->getWorkbench()->getContext()->getScopeUser()->getUsername()
        ]);
        
        // Zeitpunkt des Exports
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.TIMESTAMP'),
            DateTimeDataType::formatDateLocalized(new \DateTime(), $this->getWorkbench())
        ]);
        
        // Exportiertes Objekt
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.OBJECT'),
            $dataSheet->getMetaObject()->getName() . ' (' . $dataSheet->getMetaObject()->getAliasWithNamespace() . ')'
        ]);
        
        // Verwendete Filter
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.FILTER') . ':'
        ]);
        // Filter mit Captions von der DataTable auslesen
        $filters = $this->getFilterData($dataSheet);
        foreach ($filters as $key => $value) {
            $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
                $key,
                $value,
            ]);
        }
    }
    
    /**
     * 
     * @param DataTypeInterface $dataType
     * @return string
     */
    protected function getExcelDataType(DataTypeInterface $dataType) : string
    {
        $customType = $this->dataTypeMap[$dataType->getAliasWithNamespace()];
        if ($customType !== null) {
            return $customType;
        }
        
        switch (true) {
            case ($dataType instanceof BooleanDataType): 
                return $this->willFormatEnumsAsLabels() ? 'string' : 'integer';
            case ($dataType instanceof TimestampDataType):
            case ($dataType instanceof DateTimeDataType):
                return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT_EXCEL');
            case ($dataType instanceof DateDataType):
                return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT_EXCEL');
            case ($dataType instanceof HexadecimalNumberDataType):
                return 'string';
            case ($dataType instanceof PriceDataType):
                return 'price';
            case ($dataType instanceof IntegerDataType):
                return 'integer';
            case ($dataType instanceof NumberEnumDataType):
                return 'string';
            case ($dataType instanceof NumberDataType):
                return '';
            default:
                return 'string';
        }
    }
    
    /**
     *
     * @return string[]
     */
    protected function getDataTypeMap() : array
    {
        return $this->dataTypeMap;
    }
    
    /**
     * Map custom data types to the Excel cell format they should be exported with.
     * 
     * Provide a list of `data type alias (incl. namespace)` to `Excel cell format` pairs. Use this
     * whenever a column's values should appear in a specific format in the spreadsheet. You can use
     * any Excel cell format notation or one of the following simple formats:
     * 
     * | simple formats | format code                               |
     * | -------------- | ----------------------------------------- |
     * | string         | @                                         |
     * | integer        | 0                                         |
     * | date           | YYYY-MM-DD                                |
     * | datetime       | YYYY-MM-DD HH:MM:SS                       |
     * | price          | #,##0.00                                  |
     * | dollar         | [$$-1009]#,##0.00;[RED]-[$$-1009]#,##0.00 |
     * | euro           | #,##0.00 [$€-407];[RED]-#,##0.00 [$€-407] |
     * 
     * @uxon-property data_type_map
     * @uxon-type array
     * 
     * @param array $value
     * @return ExportXLSX
     */
    public function setDataTypeMap(array $value) : ExportXLSX
    {
        $this->dataTypeMap = $value;
        return $this;
    }
    
    protected function getExcelDataTypeDefault() : string
    {
        return static::DATA_TYPE_STRING;
    }
    
    /**
     *
     * @return bool
     */
    public function getEnableColumnFilters() : bool
    {
        return $this->enableColumnFilters;
    }
    
    /**
     * Set to FALSE to disable autofiltering (filter icon) on columns.
     * 
     * @uxon-property enable_column_filters
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return ExportXLSX
     */
    public function setEnableColumnFilters($value) : ExportXLSX
    {
        $this->enableColumnFilters = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getFreezeHeaderRow() : bool
    {
        return $this->freezeHeaderRow;
    }
    
    /**
     * Set to FALSE in order not to freeze the first row (header row).
     * 
     * @uxon-property freeze_header_row
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return ExportXLSX
     */
    public function setFreezeHeaderRow($value) : ExportXLSX
    {
        $this->freezeHeaderRow = BooleanDataType::cast($value);
        return $this;
    }
}
?>