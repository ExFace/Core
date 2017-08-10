<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Widgets\DataTable;
use exface\Core\Exceptions\Actions\ActionExportDataError;

/**
 * Exports data to an xlsx file.
 * 
 * @author SFL
 *
 */
class ExportXLSX extends ExportDataFile
{

    // Der Name des Sheets in der Excel-Datei
    private $excelSheetName = 'Sheet1';

    // Bildet alexa UI-Datentypen auf Excel-Datentypen ab
    private $typeMap = [
        'Boolean' => 'integer',
        'Date' => 'DD.MM.YYYY',
        'FlagTreeFolder' => 'string',
        'Html' => 'string',
        'ImageUrl' => 'string',
        'Integer' => 'integer',
        'Json' => 'string',
        'Number' => '', // Komma wird automatisch angezeigt oder ausgeblendet
        'Price' => 'price',
        'PropertySet' => 'string',
        'Relation' => 'string',
        'RelationHierarchy' => 'string',
        'String' => 'string',
        'Text' => 'string',
        'Timestamp' => 'DD.MM.YYYY HH:MM:SS',
        'Url' => 'string',
        'Uxon' => 'string'
    ];

    private $rowNumberWritten = 0;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportData::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIconName(Icons::FILE_EXCEL_O);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeHeader()
     */
    protected function writeHeader(DataSheetInterface $dataSheet)
    {
        /** @var DataTable $inputWidget */
        $inputWidget = $this->getCalledByWidget()->getInputWidget();
        
        $header = [];
        $headerTypes = [];
        $output = [];
        
        foreach ($dataSheet->getColumns() as $col) {
            if (! $col->getHidden()) {
                // determine name
                if ($this->getWriteReadableHeader()) {
                    if (($dataTableCol = $inputWidget->getColumnByAttributeAlias($col->getAttributeAlias())) || ($dataTableCol = $inputWidget->getColumnByDataColumnName($col->getName()))) {
                        $colName = $dataTableCol->getCaption();
                    } else {
                        $colName = $col->getAttribute()->getName();
                    }
                } else {
                    $colName = $col->getName();
                }
                
                // determine datatype
                switch ($col->getDataType()->getName()) {
                    case 'Number':
                        if ($col->getName() == 'UID') {
                            // UIDs sind hexadecimal und werden als String ausgegeben.
                            $colDataType = $this->typeMap['String'];
                        } else {
                            $colDataType = $this->typeMap[$col->getDataType()->getName()];
                        }
                        break;
                    default:
                        $colDataType = $this->typeMap[$col->getDataType()->getName()];
                }
                
                $header[] = $colName;
                $headerTypes[] = $colDataType;
                $output[] = $col->getName();
            }
        }
        
        // Schreibe erst die Headertypen ohne Headerzeile, dann die Headerzeile. Auch
        // beides zusammen waere moeglich gibt aber Probleme bei mehreren identischen
        // (z.B. mehreren leeren) Headern.
        $this->getWriter()->writeSheetHeader($this->getExcelSheetName(), $headerTypes, true);
        $this->getWriter()->writeSheetRow($this->getExcelSheetName(), $header, '');
        return $output;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $headerKeys)
    {
        foreach ($dataSheet->getRows() as $row) {
            $rowKeys = array_keys($row);
            $outRow = [];
            foreach ($headerKeys as $key) {
                if (! (array_search($key, $rowKeys) === false)) {
                    $outRow[] = $row[$key];
                } else {
                    $outRow[] = null;
                }
            }
            if ($this->rowNumberWritten >= $this->getWriter()::EXCEL_2007_MAX_ROW) {
                throw new ActionExportDataError($this, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.EXPORTDATA.ROWOVERFLOW', array(
                    '%number%' => $this->getWriter()::EXCEL_2007_MAX_ROW
                )));
            }
            $this->getWriter()->writeSheetRow($this->getExcelSheetName(), $outRow);
            $this->rowNumberWritten ++;
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeFileResult()
     */
    protected function writeFileResult()
    {
        $this->getWriter()->writeToFile($this->getPathname());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::getWriter()
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = new \XLSXWriter();
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
     *
     * @return string
     */
    protected function getExcelSheetName()
    {
        return $this->excelSheetName;
    }
}
?>