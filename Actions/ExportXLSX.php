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
 * As all export actions do, this action will read all data matching the current filters (no pagination), eventually
 * splitting it into multiple requests. You can use `limit_rows_per_request` and `limit_time_per_request` to control this.
 * 
 * ## Data type handling
 * 
 * If the exported data uses custom data types, they can be mapped to Excel format expressions manually
 * using `data_type_map`.
 * 
 * ## Examples
 * 
 * Here is an example of the configuration for a machine-friendly export (no filters, no frozen rows, aliases as headers):
 * 
 * ```
 * {
 *  "alias": "exface.Core.ExportXLSX",
 *  "use_attiribute_alias_as_header": true,
 *  "enable_column_filters": false,
 *  "freeze_header_row": false
 * }
 * 
 * ```
 * 
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportJSON::writeHeader()
     */
    protected function writeHeader(array $exportedColumns) : array
    {
        $headerTypes = [];
        $columnOptions = [];
        $output = [];
        $indexes = [];
        foreach ($exportedColumns as $widget) {
            if ($widget instanceof iShowDataColumn && $widget->isExportable(true) === false) {
                continue;
            }
            $colOptions = [];
            // Name der Spalte
            if ($this->getUseAttributeAliasAsHeader() === true && ($widget instanceof iShowDataColumn) && $widget->isBoundToDataColumn()) {
                $colName = $widget->getAttributeAlias();
            } else {
                $colName = $widget->getCaption();
            }
            $colId = $widget->getDataColumnName();
            
            // Der Name muss einzigartig sein, sonst werden zu wenige Headerspalten
            // geschrieben.
            $idx = $indexes[$colId] ?? 0;
            $indexes[$colId] = $idx + 1;
            if ($idx > 1) {
                $colName = $idx;
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
            $headerTypes[$colName] = $this->getExcelDataType($dataType);
            
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
            
            $output[] = $colId;
        }
        
        $options =  [
            'font-style' => 'bold',
            'auto_filter' => $this->getEnableColumnFilters()
        ];
        
        if ($this->getFreezeHeaderRow() === true) {
            $options['freeze_rows'] = 1;
        }
        
        $this->getWriter()->writeSheetHeader($this->getExcelDataSheetName(), $headerTypes, $options, $columnOptions);
        return $output;
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
                return 'integer';
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
     * Maps a UXON data type alias (incl. namespace) to an Excel cell format.
     * 
     * You can use any Excel cell type notation or the following simple types:
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
     * Set to FALSE to disable autofiltering (filter icon) on columns
     * 
     * @uxon-property enable_column_filters
     * @uxon-type boolean 
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
     * Set to FALSE in order not to freeze the first row (header row)
     * 
     * @uxon-property freeze_header_row
     * @uxon-type boolean
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