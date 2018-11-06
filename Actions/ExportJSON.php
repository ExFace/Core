<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Actions\iExportData;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * This action exports data as a JSON array of key-value-pairs.
 * 
 * By default, captions will be used for keys. Alternatively you can use attribute aliases by setting
 * `use_attribute_alias_as_header` = TRUE.
 * 
 * As all export actions do, this action will read all data matching the current filters (no pagination), eventually
 * splitting it into multiple requests. You can use `limit_request_rows` and `limit_request_time` to control this.
 * 
 * @author Andrej Kabachnik
 *
 */
class ExportJSON extends ReadData implements iExportData
{
    private $downloadable = true;
    
    private $filename = null;
    
    private $mimeType = null;
    
    private $pathname = null;
    
    private $writer = null;
    
    private $useAttributeAliasAsHeader = false;
    
    private $limitRowsPerRequest = 10000;
    
    private $limitTimePerRequest = 300;
    
    private $firstRowWritten = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::DOWNLOAD);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @return DataSheetInterface
     */
    protected function getDataSheetToRead(TaskInterface $task) : DataSheetInterface
    {
        $dataSheet = $this->getInputDataSheet($task);
        // Make sure, the input data has all the columns required for the widget
        // we export from. Generally this will not be the case, because the
        // widget calling the action is a button and it normally does not know
        // which columns to export.
        $widget = $this->getWidgetToReadFor($task);
        if ($widget){
            $widget->prepareDataSheetToRead($dataSheet);
        }
        
        $dataSheet->removeRows();
        return $dataSheet;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::isDownloadable()
     */
    public function isDownloadable() : bool
    {
        return $this->downloadable;
    }

    /**
     * Set to FALSE to prevent direct downloading of the exported file (i.e. just export, no download).
     * 
     * @uxon-property downloadable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setDownloadable()
     */
    public function setDownloadable($true_or_false) : iExportData
    {
        $this->downloadable = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getFilename()
     */
    public function getFilename() : string
    {
        if ($this->filename === null){
            return 'export_' . date('Y-m-d_his', time());
        }
        return $this->filename;
    }

    /**
     * Explicitly sets a fixed name for the export file.
     * 
     * If no file name is specified, it will be generated from the export time: e.g. `export_2018-10-22 162259`.
     * 
     * @uxon-property filename
     * @uxon-type string
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setFilename()
     */
    public function setFilename(string $filename) : iExportData
    {
        $this->filename = $filename;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getFileExtension() : string
    {
        switch ($this->getMimeType()){
            case 'application/json': return 'json';
            case 'text/xml': return 'xml';
            case 'text/csv': return 'csv';
            case 'text/plain': return 'txt';
            case 'application/vnd.openxmlformats-officedocument. spreadsheetml.sheet': return 'xlsx';
            // TODO add more from https://wiki.selfhtml.org/wiki/MIME-Type/%C3%9Cbersicht#X
        }
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getMimeType()
     */
    public function getMimeType() : ?string
    {
        if ($this->mimeType === null && $this::class === ExportJSON::class) {
            return 'application/json';
        }
        return $this->mimeType;
    }
    
    /**
     * Explicitly specifies a mime type for the download.
     * 
     * @uxon-property mime_type
     * @uxon-type string
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setMimeType()
     */
    public function setMimeType(string $mimeType) : iExportData
    {
        $this->mimeType = $mimeType;
        return $this;
    }    
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        // DataSheet vorbereiten
        $dataSheetMaster = $this->getDataSheetToRead($task);
        
        $widget = $this->getWidgetToReadFor($task);
        /* @var $widget \exface\Core\Interfaces\Widgets\iShowData */
        if (! ($widget instanceof iShowData)) {
            $page = $task->getPageTriggeredOn();
            $widget = WidgetFactory::create($page, 'Data');
            foreach ($dataSheetMaster->getColumns() as $col) {
                if ($col->getHidden()) {
                    continue;
                }
                $colWidget = WidgetFactory::create($page, 'DataColumn', $widget);
                $colWidget->setAttributeAlias($col->getAttributeAlias());
                $widget->addColumn($colWidget);
            }
        }
        
        // Datei erzeugen und schreiben
        $columnNames = $this->writeHeader($widget);
        $rowsOnPage = $this->getLimitRowsPerRequest();
        $rowOffset = 0;
        $errorMessage = null;
        
        set_time_limit($this->getLimitTimePerRequest());
        do {
            $dataSheet = $dataSheetMaster->copy();
            $dataSheet->setRowsOnPage($rowsOnPage);
            $dataSheet->setRowOffset($rowOffset);
            $dataSheet->dataRead();
            
            $this->writeRows($dataSheet, $columnNames);
            
            $rowOffset += $rowsOnPage;
            // Das Zeitlimit wird bei jedem Schleifendurchlauf neu gesetzt, so dass es immer
            // nur fuer einen Durchlauf gilt. Sonst kommt es bei groesseren Abfragen schnell
            // zu einem fatal error: maximum execution time exceeded.
            set_time_limit($this->getLimitTimePerRequest());
        } while (count($dataSheet->getRows()) === $rowsOnPage);
        
        // Speicher frei machen
        $dataSheet = null;
        
        // Datei abschliessen und zum Download bereitstellen
        $this->writeFileResult($dataSheetMaster);
        $result = ResultFactory::createFileResult($task, $this->getFilePathAbsolute());
        
        if ($errorMessage !== null) {
            $result->setMessage($errorMessage);
        }
        
        return $result;
    }
    
    /**
     * Generates an array of column names from the passed DataSheet and writes it as headers
     * to the file.
     *
     * The column name array is returned.
     *
     * @param iShowData $dataWidget
     * @return string[]
     */
    protected function writeHeader(iShowData $dataWidget) : array
    {
        $header = [];
        foreach ($dataWidget->getColumns() as $col) {
            if (! $col->getHidden()) {
                $header[$col->getDataColumnName()] = $this->getUseAttributeAliasAsHeader() === true ? $col->getAttributeAlias() : $col->getCaption();
            }
        }
        return $header;
    }
    
    /**
     * Generates rows from the passed DataSheet and writes them to the file.
     *
     * The cells of the row are added in the order specified by the passed columnNames array.
     * Cells which are not specified in this array won't appear in the result output.
     *
     * @param DataSheetInterface $dataSheet
     * @param string[] $columnNames
     * @return void
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $columnNames)
    {
        foreach ($dataSheet->getRows() as $row) {
            $outRow = [];
            foreach ($columnNames as $key) {
                $outRow[$key] = $row[$key];
            }
            if ($this->firstRowWritten) {
                fwrite($this->getWriter(), ',' . json_encode($outRow, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT));
            } else {
                fwrite($this->getWriter(), json_encode($outRow, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT));
                $this->firstRowWritten = true;
            }
        }
    }
    
    /**
     * Writes the terminated file to the path from getFilePathAbsolute().
     *
     * @param DataSheetInterface $dataSheet
     * @return void
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        fwrite($this->getWriter(), ']');
        fclose($this->getWriter());
    }
    
    
    
    /**
     *
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = fopen($this->getPathname(), 'x+');
            fwrite($this->writer, '[');
        }
        return $this->writer;
    }
    
    /**
     * Returns the absolute path to the file.
     *
     * @return string
     */
    protected function getFilePathAbsolute() : string
    {
        if (is_null($this->pathname)) {
            $filemanager = $this->getWorkbench()->filemanager();
            $this->pathname = Filemanager::pathJoin([
                $filemanager->getPathToCacheFolder(),
                $this->getFilename() . '.' . $this->getFileExtension()
            ]);
        }
        return $this->pathname;
    }
    
    /**
     * Returns the number of rows per request.
     *
     * @return int
     */
    public function getLimitRowsPerRequest() : int
    {
        return $this->limitRowsPerRequest;
    }
    
    /**
     * Sets the number of rows per request (default 10000).
     *
     * If in total more rows are requested, several subsequent requests are started to fetch
     * all rows. If a fatal error: "allowed memory size exhausted" occurs during a
     * xlsx-export it is advisable to reduce this number.
     *
     * @uxon-property limit_rows_per_request
     * @uxon-type integer
     *
     * @param integer $number
     * @return \exface\Core\Actions\ExportXLSX
     */
    public function setLimitRowsPerRequest(int $number) : ExportJSON
    {
        $this->limitRowsPerRequest = intval($number);
        return $this;
    }
    
    /**
     * Returns the time limit per request in microseconds.
     *
     * @return integer
     */
    public function getLimitTimePerRequest() : int
    {
        return $this->limitTimePerRequest;
    }
    
    /**
     * Sets the time limit per request (in seconds) (default 300).
     *
     * If the processing of one request takes longer than the time limit, php assumes that
     * some kind of error occured and stops the execution of the code. If a fatal error:
     * "maximum execution time exceeded" occurs during a xlsx-export it is possible to
     * increase this number to try if the request finishes in a longer time.
     *
     * @uxon-property limit_time_per_request
     * @uxon-type integer
     *
     * @param integer $microseconds
     * @return \exface\Core\Actions\ExportJSON
     */
    public function setLimitTimePerRequest(int $microseconds) : ExportJSON
    {
        $this->limitTimePerRequest = $microseconds;
        return $this;
    }
    
    /**
     * Returns if the header of the output file contains human readable text or
     * column names.
     *
     * @return boolean
     */
    public function getUseAttributeAliasAsHeader() : bool
    {
        return $this->useAttributeAliasAsHeader;
    }
    
    /**
     * Set to TRUE to use attribute aliases as column headers in the exported data instead of captions.
     *
     * @uxon-property write_readable_header
     * @uxon-type boolean
     *
     * @param bool $value
     * @return \exface\Core\Actions\ExportJSON
     */
    public function setUseAttributeAliasAsHeader(bool $value) : ExportJSON
    {
        $this->useAttributeAliasAsHeader = BooleanDataType::cast($value);
        return $this;
    }
}
?>