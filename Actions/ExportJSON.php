<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Actions\iExportData;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\AggregatePlaceholder;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;
use exface\Core\Templates\Placeholders\DataAggregationPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Widgets\Container;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\DataSheets\PivotSheetInterface;
use exface\Core\Interfaces\DataSheets\PivotColumnInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * This action exports data as a JSON array of key-value-pairs.
 * 
 * By default, captions will be used for keys. Alternatively you can use attribute aliases by setting
 * `use_attribute_alias_as_header` = TRUE.
 * 
 * As all export actions do, this action will read all data matching the current filters (no pagination), eventually
 * splitting it into multiple requests. You can use `limit_rows_per_request` and `limit_time_per_request` to control this.
 *
 *
 *
 *   ## Filename Placeholders
 *
 *
 *
 *   You can dynamically generate filenames based on aggregated data, by using placeholders in the property `filename`.
 *   For example `"filename":"[#=Now('yyyy-MM-dd')#]_[#~data:Materialkategorie:LIST_DISTINCT#]"` could be used to include both
 *   the current date and some information about the categories present in the export and result in a filename like `2024-09-10_Muffen`.
 *
 *   ### Supported placeholders:
 *
 *   - `[#=Formula()#]` Allows the use of formulas.
 *   - `[#~data:attribute_alias:AGGREGATOR#]` Aggregates the data column for the given alias by applying the specified aggregator. See below for
 *  a list of supported aggregators.
 *
 *
 *
 *   ### Supported aggregators:
 *
 *   - `SUM` Sums up all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 *   - `AVG` Calculates the arithmetic mean of all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 *   - `MIN` Gets the lowest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *   non-numeric values will be read as numerics or as 0, if they cannot be converted.
 *   - `MAX` Gets the highest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *    non-numeric values will be read as numerics or as 0, if they cannot be converted.
 *   - `COUNT` Counts the total number of rows in the column.
 *   - `COUNT_DISTINCT` Counts the number of unique entries in the column, excluding empty rows.
 *   - `LIST` Lists all non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 *   - `LIST_DISTINCT` Lists all unique, non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 *
 *
 * @author Andrej Kabachnik
 *
 */
class ExportJSON extends ReadData implements iExportData
{
    private $downloadable = true;
    
    private $filename = null;

    private ?string $filePathAbsolute = null;
    
    protected $mimeType = null;

    private $writer = null;
    
    private $useAttributeAliasAsHeader = false;
    
    private $limitRowsPerRequest = 10000;
    
    private $limitTimePerRequest = 300;
    
    private $firstRowWritten = false;
    
    private $lazyExport = null;

    private $exportMapper = null;
    
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
            $dataSheet = $widget->prepareDataSheetToRead($dataSheet);
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
            return 'Export_[#~object_name#]_' . date('Y-m-d_his', time());
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
        // Prepare DataSheet.
        $dataSheetMaster = $this->getDataSheetToRead($task);
        $dataSheetMaster->setAutoCount(false);
        // Initialize FilePath.
        $this->initializeFilePathAbsolute($dataSheetMaster);

        $lazyExport = $this->isLazyExport($dataSheetMaster);
        $rowsOnPage = $this->getLimitRowsPerRequest();
        
        // If there we expect to do split requests, we MUST sort over a unique attribute!
        // Otherwise, the results of subsequent requests may contain data in different order
        // resulting in dublicate or missing rows from the point of view of the entire
        // (combined) export.
        if ($rowsOnPage > 0) {
            if ($dataSheetMaster->getMetaObject()->hasUidAttribute()) {
                $dataSheetMaster->getSorters()->addFromString($dataSheetMaster->getMetaObject()->getUidAttributeAlias());
            } else {
                $rowsOnPage = null;
            }
        }
        
        $exportedWidget = $this->getWidgetToReadFor($task);
        
        if ($lazyExport) {
            $columnNames = $this->writeHeader($this->getExportColumnWidgets($exportedWidget, $dataSheetMaster));
        }

        $rowOffset = 0;
        $errorMessage = null;
        set_time_limit($this->getLimitTimePerRequest());
        $exportMapper = $this->getExportMapper();
        do {
            $dataSheet = $dataSheetMaster->copy();
            $dataSheet->setRowsLimit($rowsOnPage);
            $dataSheet->setRowsOffset($rowOffset);
            $dataSheet->dataRead();
            
            foreach ($dataSheet->getColumns() as $col) {
                $type = $col->getDataType();
                if ($type instanceof EnumDataTypeInterface) {
                    $values = $col->getValues();
                    $newValues = [];
                    foreach ($values as $val) {
                        $newValues[] = $type->getLabelOfValue($val);
                    }
                    $col->setValues($newValues);
                }
            }

            if ($exportMapper !== null) {
                $exportSheet = $exportMapper->map($dataSheet);
            } else {
                $exportSheet = $dataSheet;
            }
            
            if ($lazyExport) {
                $this->writeRows($exportSheet, $columnNames);
            } else {
                // Don't add any columns to the master sheet if reading produced hidden/system columns
                $dataSheetMaster->addRows($exportSheet->getRows(), false, false);
            }
            
            $rowOffset += $rowsOnPage;
            // Das Zeitlimit wird bei jedem Schleifendurchlauf neu gesetzt, so dass es immer
            // nur fuer einen Durchlauf gilt. Sonst kommt es bei groesseren Abfragen schnell
            // zu einem fatal error: maximum execution time exceeded.
            set_time_limit($this->getLimitTimePerRequest());
        } while ($dataSheet->countRows() === $rowsOnPage);
        
        // Speicher frei machen
        $dataSheet = null;
        
        if (! $lazyExport) {
            $columnNames = $this->writeHeader($this->getExportColumnWidgets($exportedWidget, $dataSheetMaster));
            $this->writeRows($dataSheetMaster instanceof PivotSheetInterface ? $dataSheetMaster->getPivotResultDataSheet() : $dataSheetMaster, $columnNames);
        }
        
        // Datei abschliessen und zum Download bereitstellen
        $this->writeFileResult($dataSheetMaster);
        $result = ResultFactory::createFileResultFromPath($task, $this->getFilePathAbsolute(), $this->isDownloadable());
        
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
     * @param iShowDataColumn[] $widget
     * @return string[]
     */
    protected function writeHeader(array $exportedColumns) : array
    {
        $header = [];
        foreach ($exportedColumns as $widget) {
            if ($widget instanceof iShowDataColumn && $widget->isBoundToDataColumn()) {
                if ($widget instanceof DataColumn && $widget->isExportable(! $widget->isHidden())=== false) {
                    continue;
                }
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
    protected function getExportColumnWidgets(WidgetInterface $exportedWidget, DataSheetInterface $exportedSheet) : array
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
        
        // If the exported data is a pivot-sheet, the columns we get from the widget will not be enough.
        // We need to remove the columns being pivoted and add those, that result from transposing
        // value columns.
        // TODO Shouldn't we add a column as first column that contains all captions of the transposed columns, else we wont see
        // in the export for what information the values actually stand for
        if ($exportedSheet instanceof PivotSheetInterface) {
            $widgetsBeforePivot = $widgets;
            $widgets = [];
            $pivotedSheet = $exportedSheet->getPivotResultDataSheet();
            foreach ($widgetsBeforePivot as $widgetCol) {
                $sheetCol = $exportedSheet->getColumns()->get($widgetCol->getDataColumnName());
                switch (true) {
                    // Don't bother about strange cases, where the sheet does not have a matching column
                    case $sheetCol === null:
                        $widgets[] = $widgetCol;
                        break;
                    // Replace the column with pivot headers with as many columns as headers expected
                    case $exportedSheet->isColumnWithPivotHeaders($sheetCol):
                        foreach ($pivotedSheet->getColumns() as $pivotedCol) {
                            if ($pivotedCol instanceof PivotColumnInterface) {
                                if ($pivotedCol->getPivotColumnGroup()->getColumnWithHeaders() === $sheetCol) {
                                    $widgets[] = WidgetFactory::createFromUxonInParent($exportedWidget, new UxonObject([
                                        'widget_type' => 'DataColumn',
                                        'caption' => $pivotedCol->getTitle(),
                                        'data_column_name' => $pivotedCol->getName()
                                    ]));
                                }
                            }
                        }
                        break;
                    // Skip pivot values columns - the values will be placed in the headers column above
                    case $exportedSheet->isColumnWithPivotValues($sheetCol):
                        break;
                    // Keep regular columns
                    default:
                        $widgets[] = $widgetCol;
                        break;
                }
            }
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
     * Initializes the absolute filepath for this action. Repeated calls to this function have no effect.
     *
     * TODO geb 2024-09-10: Instead of a local getter with unclear timings, a writer or filepath should passed along the logic chain.
     *
     * @param DataSheetInterface $dataSheet
     * @return void
     * @throws \Throwable
     */
    protected function initializeFilePathAbsolute(DataSheetInterface $dataSheet): void
    {
        // Repeated calls should have no effect.
        if($this->filePathAbsolute !== null) {
            return;
        }

        $tplRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $tplRenderer->addPlaceholder(new DataAggregationPlaceholders($dataSheet, '~data:'));
        $tplRenderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench()));
        $tplRenderer->addPlaceholder(new ArrayPlaceholders([
            '~object_name' => $dataSheet->getMetaObject()->getName(),
            '~object_alias' => $dataSheet->getMetaObject()->getAlias(),
        ]));

        try {
            $fileName = $tplRenderer->render($this->getFilename());
        } catch (\Throwable $e) {
            if($e->getPrevious() instanceof FormulaError) {
                throw new InvalidArgumentException('Use of data driven formulas is not supported for placeholders in "file_name"!');
            } else {
                throw $e;
            }
        }

        $fileName = FilePathDataType::sanitizeFilename($fileName);
        $fileName = str_replace(' ', '_', $fileName);
        $fileManager = $this->getWorkbench()->filemanager();
        $this->filePathAbsolute = Filemanager::pathJoin([
            $fileManager->getPathToCacheFolder(),
            $fileName . '.' . $this->getFileExtension()
        ]);
    }

    /**
     * Returns the absolute path to the file. You must initialize the path with `initializeFilePathAbsolute(DataSheetInterface)` first.
     *
     * @return string
     */
    protected function getFilePathAbsolute () : string
    {
        if($this->filePathAbsolute === null) {
            throw new ActionRuntimeError($this, "FilePath not initialized! Make sure to call initializeFilePathAbsolute(DataSheetInterface) at any point before calling getFilePathAbsolute().");
        }

        return $this->filePathAbsolute;
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
    
    /**
     * 
     * @return bool
     */
    protected function isLazyExport(DataSheetInterface $exporetedData) : bool
    {
        if ($this->lazyExport === null && $exporetedData instanceof PivotSheetInterface) {
            return false;
        }
        return $this->lazyExport ?? true;
    }
    
    /**
     * Set to FALSE to force reading all data before starting to write the file.
     * 
     * If not set explicitly, the system will attempt to write every time data is read to save memory: so when exporting
     * large data sets, it will read X rows at a time, write them to the file and continue reading. This might
     * break the output though in some cases: for example, if every new row being read might influence the columns
     * to display.
     * 
     * @uxon-property lazy_export
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return ExportJSON
     */
    public function setLazyExport(bool $value) : ExportJSON
    {
        $this->lazyExport = $value;
        return $this;
    }
    
    /**
     * 
     * @return DataSheetMapperInterface|null
     */
    public function getExportMapper() : ?DataSheetMapperInterface
    {
        return $this->exportMapper;
    }

    /**
     * This mapper is applied right after reading data and allows to modify it before it gets exported.
     * 
     * ```
     * {
     *  "export_mapper": {
     *      "json_to_rows_mapping": [
     *          {
     *              "json_column": "FORM_DATA"
     *          }
     *      ]
     *  }
     * }
     * 
     * ```
     * 
     * @uxon-property export_mapper
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     * @uxon-template {"json_to_rows_mappings": [{"json_column": ""}]}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return ExportJSON
     */
    protected function setExportMapper(UxonObject $uxon) : ExportJSON
    {
        $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $uxon, $this->getMetaObject(), $this->getMetaObject());
        $this->exportMapper = $mapper;
        return $this;
    }
}
?>