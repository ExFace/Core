<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class ExportXLSX extends ExportData
{

    private $requestRows = 30000;

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

    protected function perform()
    {
        $dataSheetMaster = $this->getInputDataSheet()->copy();
        if ($this->getCalledByWidget() && $this->getCalledByWidget()->is('Button')) {
            $this->getCalledByWidget()->getInputWidget()->prepareDataSheetToRead($dataSheetMaster);
        }
        $dataSheetMaster->removeRows();
        
        // $this->setAffectedRows($dataSheet->removeRows()->dataRead());
        // $this->setResultDataSheet($dataSheet);
        
        $rowsOnPage = $this->getRequestRows();
        $rowOffset = 0;
        do {
            $dataSheet = $dataSheetMaster->copy();
            $dataSheet->setRowsOnPage($rowsOnPage);
            $dataSheet->setRowOffset($rowOffset);
            $dataSheet->dataRead();
            
            $rowOffset += $rowsOnPage;
        } while (count($dataSheet->getRows()) == $rowsOnPage);
        
        $url = $this->export($this->getResultDataSheet());
        $this->setResult($url);
        $this->setResultMessage('Download ready. If not id does not start automatically, click <a href="' . $url . '">here</a>.');
    }

    protected function export(DataSheetInterface $dataSheet)
    {
        file_put_contents('C:\test.txt', 'Write XLSX: ' . date('Y-m-d H:i:s', time()) . "\n", FILE_APPEND);
        require_once MODX_BASE_PATH . 'exface' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'exface' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Actions' . DIRECTORY_SEPARATOR . 'xlsxwriter.class.php';
        $xlsxWriter = new XLSXWriter();
        
        // Header schreiben
        $header = [];
        foreach ($dataSheet->getColumns() as $col) {
            if (! $col->getHidden()) {
                $header[$col->getName()] = $this->typeMap[$col->getDataType()->getName()];
            }
        }
        $xlsxWriter->writeSheetHeader('Sheet1', $header);
        
        // Zeilen schreiben
        $headerKeys = array_keys($header);
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
            $xlsxWriter->writeSheetRow('Sheet1', $outRow);
        }
        
        // Excel-Sheet erzeugen
        $contents = $xlsxWriter->writeToString();
        
        // Zum Download bereitstellen
        $this->setMimeType('application/vnd.openxmlformats-officedocument. spreadsheetml.sheet');
        file_put_contents('C:\test.txt', 'Write XLSX: ' . date('Y-m-d H:i:s', time()) . "\n", FILE_APPEND);
        return $this->createDownload($contents);
    }

    public function getRequestRows()
    {
        return $this->requestRows;
    }

    public function setRequestRows($value)
    {
        $this->requestRows = $value;
        return $this;
    }
}
?>