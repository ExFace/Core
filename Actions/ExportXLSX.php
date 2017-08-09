<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class ExportXLSX extends ExportDataFile
{
    
    // Der Name des Sheets in der Excel-Datei
    private $excelSheetName = 'Sheet1';
    
    // Bildet alexa UI-Datentypen auf Excel-Datentypen ab
    private $typeMap = [
        'Boolean' => '',
        'Date' => 'DD.MM.YYYY',
        'FlagTreeFolder' => '',
        'Html' => 'string',
        'ImageUrl' => 'string',
        'Integer' => 'integer',
        'Json' => 'string',
        'Number' => '',
        'Price' => 'price',
        'PropertySet' => '',
        'Relation' => 'integer',
        'RelationHierarchy' => '',
        'String' => 'string',
        'Text' => 'string',
        'Timestamp' => 'DD.MM.YYYY HH:MM:SS',
        'Url' => 'string',
        'Uxon' => 'string'
    ];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeHeader()
     */
    protected function writeHeader(DataSheetInterface $dataSheet)
    {
        $header = [];
        foreach ($dataSheet->getColumns() as $col) {
            if (! $col->getHidden()) {
                $header[$col->getName()] = $this->typeMap[$col->getDataType()->getName()];
            }
        }
        $this->getWriter()->writeSheetHeader($this->getExcelSheetName(), $header);
        return array_keys($header);
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
            $this->getWriter()->writeSheetRow($this->getExcelSheetName(), $outRow);
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