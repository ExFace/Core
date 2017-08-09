<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

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

    private $requestRowNumber = 30000;

    private $requestTimelimit = 120;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportData::perform()
     */
    protected function perform()
    {
        // DataSheet vorbereiten
        $dataSheetMaster = $this->getInputDataSheet()->copy();
        // Make sure, the input data has all the columns required for the widget
        // we export from. Generally this will not be the case, because the
        // widget calling the action is a button and it normally does not know
        // which columns to export.
        if ($this->getCalledByWidget() && $this->getCalledByWidget()->is('Button')) {
            $this->getCalledByWidget()->getInputWidget()->prepareDataSheetToRead($dataSheetMaster);
        }
        $dataSheetMaster->removeRows();

        // Datei erzeugen und schreiben
        $columnNames = $this->writeHeader($dataSheetMaster);
        $rowsOnPage = $this->getRequestRowNumber();
        $rowOffset = 0;
        do {
            $dataSheet = $dataSheetMaster->copy();
            $dataSheet->setRowsOnPage($rowsOnPage);
            $dataSheet->setRowOffset($rowOffset);
            $dataSheet->dataRead();

            // Das DataSheet kommt hier nur gestueckelt an, daher schwierig affectedRows
            // und resultDataSheet zu setzen.
            // $this->setAffectedRows($dataSheet->removeRows()->dataRead());
            // $this->setResultDataSheet($dataSheet);

            $this->writeRows($dataSheet, $columnNames);

            $rowOffset += $rowsOnPage;
            // Das Zeitlimit wird bei jedem Schleifendurchlauf neu gesetzt, so dass es immer
            // nur fuer einen Durchlauf gilt. Sonst kommt es bei groesseren Abfragen schnell
            // zu einem fatal error: maximum execution time exceeded.
            set_time_limit($this->getRequestTimelimit());
        } while (count($dataSheet->getRows()) == $rowsOnPage);

        // Speicher frei machen
        $dataSheet = null;

        // Datei abschliessen und zum Download bereitstellen
        $this->writeFileResult();
        $url = $this->getWorkbench()->getCMS()->createLinkToFile($this->getPathname());
        $this->setResult($url);
        $this->setResultMessage('Download ready. If it does not start automatically, click <a href="' . $url . '">here</a>.');
    }

    /**
     * Generates an array of column names from the passed DataSheet and writes it as headers
     * to the file.
     *
     * The column name array is returned.
     *
     * @param DataSheetInterface $dataSheet
     * @return string[]
     */
    abstract protected function writeHeader(DataSheetInterface $dataSheet);

    /**
     * Generates rows from the passed DataSheet and writes them to the file.
     *
     * The cells of the row are added in the order specified by the passed columnNames array.
     * Cells which are not specified in this array won't appear in the result output.
     *
     * @param DataSheetInterface $dataSheet
     * @param string[] $columnNames
     */
    abstract protected function writeRows(DataSheetInterface $dataSheet, array $columnNames);

    /**
     * Writes the terminated file to the harddrive.
     */
    abstract protected function writeFileResult();

    /**
     * Returns the writer for the file.
     *
     * @return resource
     */
    abstract protected function getWriter();

    /**
     * Returns the complete path to the file.
     *
     * @return string
     */
    public function getPathname()
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
     * @return integer
     */
    public function getRequestRowNumber()
    {
        return $this->requestRowNumber;
    }

    /**
     * Sets the number of rows per request (default 30000).
     *
     * If in total more rows are requested, several subsequent requests are started to fetch
     * all rows. If a fatal error: "allowed memory size exhausted" occurs during a
     * xlsx-export it is advisable to reduce this number.
     *
     * @uxon-property request_row_number
     * @uxon-type integer
     *
     * @param integer|string $value
     * @return \exface\Core\Actions\ExportXLSX
     */
    public function setRequestRowNumber($value)
    {
        $this->requestRowNumber = intval($value);
        return $this;
    }

    /**
     * Returns the time limit per request.
     *
     * @return integer
     */
    public function getRequestTimelimit()
    {
        return $this->requestTimelimit;
    }

    /**
     * Sets the time limit per request (in seconds) (default 120).
     *
     * If the processing of one request takes longer than the time limit, php assumes that
     * some kind of error occured and stops the execution of the code. If a fatal error:
     * "maximum execution time exceeded" occurs during a xlsx-export it is possible to
     * increase this number to try if the request finishes in a longer time.
     *
     * @uxon-property request_timelimit
     * @uxon-type integer
     *
     * @param integer|string $value
     * @return \exface\Core\Actions\ExportXLSX
     */
    public function setRequestTimelimit($value)
    {
        $this->requestTimelimit = intval($value);
        return $this;
    }
}
?>