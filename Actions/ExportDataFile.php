<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Actions\ActionExportDataError;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\BooleanDataType;

/**
 * This action is the base class for a number of actions, which export raw data as a file
 * for download. It can handle very large data sets by sequentially processing smaller parts.
 * 
 * @author SFL
 *
 */
abstract class ExportDataFile extends ExportData
{

    private $pathname = null;

    private $writer = null;

    private $useAttributeAliasAsHeader = false;

    private $limitRowsPerRequest = 10000;

    private $limitTimePerRequest = 300;

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
        try {
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
            } while (count($dataSheet->getRows()) == $rowsOnPage);
        } catch (ActionExportDataError $aede) {
            $errorMessage = $aede->getMessage();
            throw $aede;
        }
        
        // Speicher frei machen
        $dataSheet = null;
        
        // Datei abschliessen und zum Download bereitstellen
        $this->writeFileResult($dataSheetMaster);
        $result = ResultFactory::createFileResult($task, $this->getPathname());
        
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
    abstract protected function writeHeader(iShowData $dataWidget) : array;

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
    abstract protected function writeRows(DataSheetInterface $dataSheet, array $columnNames);

    /**
     * Writes the terminated file to the harddrive.
     * 
     * @param DataSheetInterface $dataSheet
     * @return void
     */
    abstract protected function writeFileResult(DataSheetInterface $dataSheet);

    /**
     * Returns the writer for the file.
     *
     * @return resource
     */
    abstract protected function getWriter();

    /**
     * Returns the absolute path to the file.
     *
     * @return string
     */
    public function getPathname() : string
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
    public function setLimitRowsPerRequest(int $number) : ExportDataFile
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
     * @return \exface\Core\Actions\ExportXLSX
     */
    public function setLimitTimePerRequest(int $microseconds) : ExportDataFile
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
     * @return \exface\Core\Actions\ExportDataFile
     */
    public function setUseAttributeAliasAsHeader(bool $value) : ExportDataFile
    {
        $this->useAttributeAliasAsHeader = BooleanDataType::cast($value);
        return $this;
    }
}
?>