<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Actions\iExportData;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Exceptions\Actions\ActionLogicError;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Container;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\DataTypes\NumberEnumDataType;

/**
 * This action exports data as a JSON array of key-value-pairs.
 * 
 * By default, captions will be used for keys. Alternatively you can use attribute aliases by setting
 * `use_attribute_alias_as_header` = TRUE.
 * 
 * As all export actions do, this action will read all data matching the current filters (no pagination), eventually
 * splitting it into multiple requests. You can use `limit_rows_per_request` and `limit_time_per_request` to control this.
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
        if ($this->mimeType === null && get_class($this) === ExportJSON::class) {
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
        $dataSheetMaster->setAutoCount(false);
         
        // If there we expect to do split requests, we MUST sort over a unique attribute!
        // Otherwise, the results of subsequent requests may contain data in different order
        // resulting in dublicate or missing rows from the point of view of the entire
        // (combined) export.
        if ($this->getLimitRowsPerRequest() > 0) {
            if ($dataSheetMaster->getMetaObject()->hasUidAttribute()) {
                $dataSheetMaster->getSorters()->addFromString($dataSheetMaster->getMetaObject()->getUidAttributeAlias());
            } else {
                throw new ActionLogicError($this, 'Cannot export data for meta object ' . $dataSheetMaster->getMetaObject()->getAliasWithNamespace() . ': corrupted data expected due to lack of a UID attribute!');
            }
        }
        
        $exportedWidget = $this->getWidgetToReadFor($task);
        
        // Datei erzeugen und schreiben
        $columnNames = $this->writeHeader($exportedWidget);
        $rowsOnPage = $this->getLimitRowsPerRequest();
        $rowOffset = 0;
        $errorMessage = null;
        
        set_time_limit($this->getLimitTimePerRequest());
        do {
            $dataSheet = $dataSheetMaster->copy();
            $dataSheet->setRowsLimit($rowsOnPage);
            $dataSheet->setRowsOffset($rowOffset);
            $dataSheet->dataRead();
            
            foreach ($dataSheet->getColumns() as $col) {
                if ($col->getDataType() instanceof NumberEnumDataType) {
                    $values = $col->getValues();
                    $newValues = [];
                    foreach ($values as $val) {
                        $newValues[] = $col->getDataType()->getLabelOfValue($val);
                    }
                    $col->setValues($newValues);
                }
            }
            
            $this->writeRows($dataSheet, $columnNames);
            
            $rowOffset += $rowsOnPage;
            // Das Zeitlimit wird bei jedem Schleifendurchlauf neu gesetzt, so dass es immer
            // nur fuer einen Durchlauf gilt. Sonst kommt es bei groesseren Abfragen schnell
            // zu einem fatal error: maximum execution time exceeded.
            set_time_limit($this->getLimitTimePerRequest());
        } while ($dataSheet->countRows() === $rowsOnPage);
        
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
     * Generates an array of column names from the passed array of widgets.
     *
     * The column name array is returned.
     *
     * @param WidgetInterface $widget
     * @return string[]
     */
    protected function writeHeader(WidgetInterface $exportedWidget) : array
    {
        $header = [];
        $columnWidgets = $this->getExportColumnWidgets($exportedWidget);
        foreach ($columnWidgets as $widget) {
            if (! $widget->isHidden() && $widget instanceof iShowDataColumn && $widget->isBoundToDataColumn()) {
                if ($this->getUseAttributeAliasAsHeader() && ($widget instanceof iShowSingleAttribute) && $widget->isBoundToAttribute()) {
                    $headerName = $widget->getAttributeAlias();
                } else {
                    $headerName = $widget->getCaption();
                }
                $header[$widget->getDataColumnName()] = $headerName;
            }
        }
        return $header;
    }
    
    /**
     * 
     * @param WidgetInterface $exportedWidget
     * @return array
     */
    protected function getExportColumnWidgets(WidgetInterface $exportedWidget) : array
    {
        switch (true) {
            case $exportedWidget instanceof iUseData:
                $widgets = $exportedWidget->getData()->getColumns();
                break;
            case $exportedWidget instanceof iShowData:
                $widgets = $exportedWidget->getColumns();
                break;
            case $exportedWidget instanceof Container:
                $widgets = $exportedWidget->getWidgets();
                break;
            default:
                $widgets = [];
        }
        
        return $widgets;
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
            foreach ($columnNames as $key => $value) {
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
     * Returns an array with the set filters with the captions as array key and comporator and filter value as array value in the format
     * `{comporator} {filter value}`.
     * 
     * @param DataSheetInterface $dataSheet
     * @return array
     */
    protected function getFilterData(DataSheetInterface $dataSheet) : array
    {
        $filters = [];
        $dataTableFilters = [];
        $exportedWidget = $this->getWidgetDefinedIn()->getInputWidget();
        switch (true) {
            case $exportedWidget instanceof iShowData:
                $dataWidget = $exportedWidget;
                break;
            case $exportedWidget instanceof iUseData:
                $dataWidget = $exportedWidget->getData();
                break;
            default:
                $dataWidget = null;
        }
        if ($dataWidget) {
            foreach ($dataWidget->getFilters() as $filter) {
                $dataTableFilters[$filter->getInputWidget()->getAttributeAlias()] = $filter->getInputWidget()->getCaption();
            }
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
                    if ($dataSheet->getMetaObject()->hasAttribute($filterExpression->toString()) && ($metaAttribute = $dataSheet->getMetaObject()->getAttribute($filterExpression->toString())) && $metaAttribute->isRelation()) {
                        $relatedObject = $metaAttribute->getRelation()->getRightObject();
                        if ($relatedObject->isReadable() && empty($relatedObject->getDataAddressRequiredPlaceholders(false, true))) {
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
                }
                
                // Zeile schreiben
                $filters[$filterName] = $filterComparator . ' ' . $filterValue;
            }
        }
        return $filters;
    }
    
    
    
    /**
     *
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = fopen($this->getFilePathAbsolute(), 'x+');
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