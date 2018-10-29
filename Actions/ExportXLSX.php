<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Actions\ActionExportDataError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
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
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * Exports data to an xlsx file.
 * 
 * @author SFL
 *
 */
class ExportXLSX extends ExportDataFile
{
    const DATA_TYPE_STRING = 'string';
    
    private $dataTypeMap = [];

    private $rowNumberWritten = 0;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportData::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::FILE_EXCEL_O);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeHeader()
     */
    protected function writeHeader(iShowData $dataWidget)
    {
        $headerTypes = [];
        $columnOptions = [];
        $output = [];
        $indexes = [];
        foreach ($dataWidget->getColumns() as $col) {
            $colOptions = [];
            // Name der Spalte
            if ($this->getWriteReadableHeader()) {
                $colName = $col->getCaption();
            } else {
                $colName = $col->getAttributeAlias();
            }
            $colId = $col->getDataColumnName();
            
            // Der Name muss einzigartig sein, sonst werden zu wenige Headerspalten
            // geschrieben.
            $idx = $indexes[$colId] ?? 0;
            $indexes[$colId] = $idx + 1;
            if ($idx > 1) {
                $colName = $idx;
            }
            
            // Datentyp der Spalte
            $headerTypes[$colName] = $this->getExcelDataType($col->getDataType());
            
            // Width
            if ($col->getDataType() instanceof TimestampDataType) {
                $colOptions['width'] = '19';
            } elseif ($col->getDataType() instanceof StringDataType) {
                $colOptions['width'] = '25';
            }
            
            // Visibility
            if ($col->isHidden() === true) {
                $colOptions['hidden'] = true;
            }
            
            $columnOptions[] = $colOptions;
            
            $output[] = $colId;
        }
        
        $this->getWriter()->writeSheetHeader($this->getExcelDataSheetName(), $headerTypes, ['font-style' => 'bold', 'auto_filter' => true], $columnOptions);
        return $output;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeRows()
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
     * @see \exface\Core\Actions\ExportDataFile::writeFileResult()
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        $this->writeInfoExcelSheet($dataSheet);
        $this->getWriter()->writeToFile($this->getPathname());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::getWriter()
     * 
     * @return \XLSXWriter
     */
    protected function getWriter()
    {
        if ($this->writer === null) {
            //$this->writer = new \XLSXWriter();
            $this->writer = new XLSXWriter();
        }
        return $this->writer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportData::getMimeType()
     */
    public function getMimeType()
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
            date($translator->translate('LOCALIZATION.DATE.DATETIME_FORMAT'))
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
        $dataTableFilters = [];
        foreach ($this->getWidgetDefinedIn()->getInputWidget()->getFilters() as $filter) {
            $dataTableFilters[$filter->getInputWidget()->getAttributeAlias()] = $filter->getInputWidget()->getCaption();
        }
        // Gesetzte Filter am DataSheet durchsuchen
        foreach ($dataSheet->getFilters()->getConditions() as $condition) {
            if (! is_null($filterValue = $condition->getValue()) && $filterValue !== '') {
                // Name
                if (array_key_exists(($filterExpression = $condition->getExpression())->toString(), $dataTableFilters)) {
                    $filterName = $dataTableFilters[$filterExpression->toString()];
                } else if ($filterExpression->isMetaAttribute()) {
                    $filterName = $dataSheet->getMetaObject()->getAttribute($filterExpression->toString())->getName();
                } else {
                    $filterName = '';
                }
                
                // Comparator
                $filterComparator = $condition->getComparator();
                if (substr($filterComparator, 0, 1) == '=') {
                    // Wird sonst vom XLSX-Writer in eine Formel umgewandelt.
                    $filterComparator = ' ' . $filterComparator;
                }
                
                // Wert, gehoert der Filter zu einer Relation soll das Label und nicht
                // die UID geschrieben werden
                if ($filterExpression->isMetaAttribute()) {
                    if (($metaAttribute = $dataSheet->getMetaObject()->getAttribute($filterExpression->toString())) && $metaAttribute->isRelation()) {
                        $relatedObject = $metaAttribute->getRelation()->getRightObject();
                        $filterValueRequestSheet = DataSheetFactory::createFromObject($relatedObject);
                        $uidColName = $filterValueRequestSheet->getColumns()->addFromAttribute($relatedObject->getUidAttribute())->getName();
                        if ($relatedObject->hasLabelAttribute()) {
                            $labelColName = $filterValueRequestSheet->getColumns()->addFromAttribute($relatedObject->getLabelAttribute())->getName();
                        } else {
                            $labelColName = $uidColName;
                        }
                        $filterValueRequestSheet->getFilters()->addCondition(ConditionFactory::createFromExpression($this->getWorkbench(), ExpressionFactory::createFromAttribute($relatedObject->getUidAttribute()), $filterValue, $condition->getComparator()));
                        $filterValueRequestSheet->dataRead();
                        
                        if ($requestValue = implode(', ', $filterValueRequestSheet->getColumnValues($labelColName))) {
                            $filterValue = $requestValue;
                        }
                    }
                }
                
                // Zeile schreiben
                $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
                    $filterName,
                    $filterComparator,
                    $filterValue
                ]);
            }
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
            case ($dataType instanceof TimestampDataType);
                return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT_EXCEL');
            case ($dataType instanceof DateDataType):
                return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATE_FORMAT_EXCEL');
            case ($dataType instanceof HexadecimalNumberDataType):
                return 'string';
            case ($dataType instanceof PriceDataType):
                return 'price';
            case ($dataType instanceof IntegerDataType):
                return 'integer';
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
     * | simple formats | format code |
     * | ---------- | ---- |
     * | string   | @ |
     * | integer  | 0 |
     * | date     | YYYY-MM-DD |
     * | datetime | YYYY-MM-DD HH:MM:SS |
     * | price    | #,##0.00 |
     * | dollar   | [$$-1009]#,##0.00;[RED]-[$$-1009]#,##0.00 |
     * | euro     | #,##0.00 [$€-407];[RED]-#,##0.00 [$€-407] |
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
    
}
?>