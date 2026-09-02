<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MimeTypeDataType;
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
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;
use exface\Core\Templates\Placeholders\DataAggregationPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Widgets\Container;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Widgets\Data;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\DataSheets\PivotSheetInterface;
use exface\Core\Interfaces\DataSheets\PivotColumnInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Widgets\DataTableConfigurator;
use exface\Core\Widgets\PivotTable;
use exface\Core\Widgets\DataMatrix;

/**
 * This action exports data as a JSON array of key-value-pairs.
 * 
 * By default, captions will be used for keys. Alternatively you can use attribute aliases by setting
 * `use_attribute_alias_as_header` = TRUE.
 * 
 * ## How the data is read (and why it may take several steps)
 * 
 * The export always contains ALL rows that match the current filters - not just the rows that
 * are currently on screen. To stay fast and avoid running out of memory on large tables,
 * the data is fetched in several smaller batches ("requests") instead of one huge read. The
 * batches are then combined into a single export file. As a designer you normally don't have to think about this:
 * The action exports all matching rows, no matter how many there are, and the user gets one complete file.
 * 
 * If you do want to influence this behaviour, two settings let you do so:
 * 
 * - `limit_rows_per_request` - how many rows are fetched per batch (default 10000).
 * - `limit_time_per_request` - how long a single batch may take before it is aborted (default 300 seconds).
 * 
 * On top of the per-batch time limit, there is an overall time budget for the whole export. It is
 * not an action property, but the core config option `EXPORT.MAX_PROCESSING_TIME` (in seconds,
 * default 300). Before reading starts, the action samples a few rows, extrapolates how long the
 * full export would take and aborts up-front with a readable error if that estimate exceeds the
 * budget - so users get quick feedback instead of waiting for a request to time out. Set the
 * option to raise or lower this budget installation-wide. If you remove the option or set it to null the guard
 *  is disabled.
 * 
 * Whether the data can be split into batches depends on the exported object:
 * 
 * - Objects WITH a unique identifier (UID) - almost all business objects - are read batch by
 *   batch. The UID is used to reliably continue where the previous batch stopped, so no row is
 *   ever exported twice or skipped. This is the normal, memory-friendly case.
 * - Objects WITHOUT a UID cannot be split safely (there is no reliable way to tell the batches
 *   apart), so all their rows are read in one single request. For such objects `limit_rows_per_request`
 *   has no effect and very large exports may hit memory limits.
 * 
 * ### When should I change these settings?
 * 
 * You usually do not need to. Only adjust them if an export fails:
 * 
 * - "Allowed memory size exhausted" -> LOWER `limit_rows_per_request` (e.g. to 5000 or 1000) so
 *   each batch is smaller and uses less memory.
 * - "Maximum execution time exceeded" -> RAISE `limit_time_per_request` to give each batch more
 *   time to finish.
 * 
 * ## Which columns get exported?
 * 
 * The columns are taken from the widget the action is placed in (e.g. a `DataTable`). A column is exported
 * unless it is hidden or has `exportable` set to `false`. A hidden column, that is explicitly set to
 * `exportable` = `true`, is still exported. To avoid reading unneeded data, columns that will not be part of
 * the export are removed before the data is read.
 * 
 * ## Filename Placeholders
 * 
 * You can dynamically generate filenames based on aggregated data, by using placeholders in the property `filename`.
 * For example `"filename":"[#=Now('yyyy-MM-dd')#]_[#~data:Materialkategorie:LIST_DISTINCT#]"` could be used to include both
 * the current date and some information about the categories present in the export and result in a filename like `2024-09-10_Muffen`.
 * 
 * ### Supported placeholders:
 * 
 * - `[#=Formula()#]` Allows the use of formulas.
 * - `[#~data:attribute_alias:AGGREGATOR#]` Aggregates the data column for the given alias by applying the specified aggregator. See below for
 * a list of supported aggregators.
 * 
 * ### Supported aggregators:
 * 
 * - `SUM` Sums up all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 * - `AVG` Calculates the arithmetic mean of all values present in the column. Non-numeric values will either be read as numerics or as 0, if they cannot be converted.
 * - `MIN` Gets the lowest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 * non-numeric values will be read as numerics or as 0, if they cannot be converted.
 * - `MAX` Gets the highest of all values present in the column. If only non-numeric values are present, their alphabetic rank is used. If the column is mixed,
 *  non-numeric values will be read as numerics or as 0, if they cannot be converted.
 * - `COUNT` Counts the total number of rows in the column.
 * - `COUNT_DISTINCT` Counts the number of unique entries in the column, excluding empty rows.
 * - `LIST` Lists all non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
 * - `LIST_DISTINCT` Lists all unique, non-empty rows in the column, applying the following format: `Some value,anotherValue,yEt another VaLue` => `SomeValue_AnotherValue_YetAnotherValue`
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
    private $formatEnums = true;
    
    private $limitRowsPerRequest = 10000;
    private $limitTimePerRequest = 300;
    private $firstRowWritten = false;
    private $lazyExport = null;

    private $exportMapper = null;
    private $exportAllWidgetColumns = false;
    private $paginationDisabled = false;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::DOWNLOAD);
        // Don't monitor exports as long-running by default, because it's fine if they take longer for large data sets.
        $this->setMonitorAsLongRunningAfterSeconds(-1);
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
        if ($widget !== null) {
            $widgetSheet = $widget->prepareDataSheetToRead($dataSheet);
            if($this->getUseExportDataSheet($widget) === false) {
                $dataSheet = $widgetSheet;
            } else {
                // If we use the exportDataSheet as the reference (e.g. exporting from DataTables with a configurator)
                // we need to transfer aggregations.
                // TODO geb 2026-04-14: Is this the right place and do we need to import other properties as well?
                foreach ($widgetSheet->getAggregations() as $aggregation) {
                    $dataSheet->getAggregations()->add($aggregation);
                }
            }
            // Don't read columns, that will not be part of the export anyway (hidden or
            // explicitly non-exportable ones) to avoid fetching unneeded data.
            $this->removeNonExportableColumns($dataSheet, $widget);
        }
        
        $dataSheet->removeRows();
        return $dataSheet;
    }

    /**
     * Removes columns from the data sheet, that would not be part of the export anyway.
     * 
     * Only columns bound to widget columns, that will actually be exported (i.e. would pass
     * the writeHeader() test) are kept. Everything else is removed to avoid reading unneeded
     * data - this includes hidden or explicitly non-exportable widget columns as well as
     * technical columns without a widget counterpart (e.g. columns added for button
     * authorization or `hidden_if`/`disabled_if` conditions). System columns are kept, as
     * they may be required for internal processing (e.g. UID sorting).
     * 
     * @param DataSheetInterface $dataSheet
     * @param WidgetInterface $widget
     * @return void
     */
    protected function removeNonExportableColumns(DataSheetInterface $dataSheet, WidgetInterface $widget) : void
    {
        switch (true) {
            case $widget instanceof iUseData:
                $dataWidget = $widget->getData();
                break;
            case $widget instanceof iShowData:
                $dataWidget = $widget;
                break;
            default:
                return;
        }
        $columns = $dataWidget->getColumns();
        // Optional columns live in a separate configurator tab, not in getColumns(). They
        // are exported if the user made them visible (i.e. they are present in the sheet),
        // so they must be treated as potentially exportable here too.
        if (($dataWidget instanceof iHaveConfigurator) && ($configurator = $dataWidget->getConfiguratorWidget()) instanceof DataTableConfigurator) {
            foreach ($configurator->getOptionalColumns() as $optCol) {
                $columns[] = $optCol;
            }
        }
        // Collect the data column names of all widget columns, that will actually be exported.
        // Same rule as in writeHeader() (see isColumnExportable()): a column is exported unless
        // it is hidden or explicitly non-exportable.
        $exportableColNames = [];
        foreach ($columns as $col) {
            if (! ($col instanceof DataColumn)) {
                continue;
            }
            if ($this->isColumnExportable($col) === false) {
                continue;
            }
            $exportableColNames[] = $col->getDataColumnName();
        }
        
        // Remove every sheet column, that is not going to be exported. Collect the names
        // first and remove afterwards to avoid modifying the collection while iterating.
        $namesToRemove = [];
        foreach ($dataSheet->getColumns() as $sheetCol) {
            // Keep columns, that will be exported.
            if (in_array($sheetCol->getName(), $exportableColNames, true)) {
                continue;
            }
            // Keep system columns - they may be required for internal processing.
            if ($sheetCol->isAttribute() && $sheetCol->getAttribute()->isSystem()) {
                continue;
            }
            $namesToRemove[] = $sheetCol->getName();
        }
        foreach ($namesToRemove as $name) {
            $dataSheet->getColumns()->removeByKey($name);
        }
    }

    /**
     * Returns TRUE if the given column will be part of the export and FALSE otherwise.
     * 
     * This is used by both writeHeader() (to decide what to write) and
     * removeNonExportableColumns() (to avoid reading data, that would not be exported anyway).
     * Subclasses writing other file formats may export a different set of columns (e.g. XLSX
     * keeps hidden columns) and override this accordingly.
     * 
     * @param WidgetInterface $col
     * @return bool
     */
    protected function isColumnExportable(WidgetInterface $col) : bool
    {
        if ($col instanceof DataColumn) {
            return $col->isExportable(! $col->isHidden());
        }
        return true;
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
            default:
                return MimeTypeDataType::guessExtensionOfMimeType($this->getMimeType());
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
        $exportMapper = $this->getExportMapper();

        // Prepare DataSheet.
        $dataSheetMaster = $this->getDataSheetToRead($task);
        $dataSheetMaster->setAutoCount(false);

        // Initialize FilePath.
        $this->initializeFilePathAbsolute($dataSheetMaster);

        $lazyExport = $this->isLazyExport($dataSheetMaster);
        $exportedWidget = $this->getWidgetToReadFor($task);
        
        if ($lazyExport) {
            $columnNames = $this->writeHeader($this->getExportColumnWidgets($exportedWidget, $dataSheetMaster));
        }

        $mapperAddedCols = [];
        $errorMessage = null;
        set_time_limit($this->getLimitTimePerRequest());
        
        // Abort early if a timed sample read extrapolates to more than the allowed total time.
        $this->checkEstimatedExportTime($dataSheetMaster, $this->getEstimateSampleSize($exportedWidget));
        
        // Reading the data is different for objects with and without a UID:
        // - With a UID we can paginate deterministically via a keyset cursor, doing several
        //   requests for large data sets - see readPagesByUid().
        // - Without a UID there is no stable sort order to paginate over, so everything is read
        //   in a single request - see readPageWithoutUid().
        // Both return a generator of page sheets, so the per-page processing below stays shared.
        if ($dataSheetMaster->getMetaObject()->hasUidAttribute() && !$this->isPaginationDisabled()) {
            $pages = $this->readPagesByUid($dataSheetMaster, $exportMapper);
        } else {
            $pages = $this->readPageWithoutUid($dataSheetMaster, $exportMapper);
        }
        
        foreach ($pages as $pageSheet) {
            if ($this->willFormatEnumsAsLabels()) {
                foreach ($pageSheet->getColumns() as $col) {
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
            }

            if ($exportMapper !== null) {
                $exportMapper->setInheritColumns(DataSheetMapper::INHERIT_ALL);
                $exportSheet = $exportMapper->map($pageSheet);
            } else {
                $exportSheet = $pageSheet;
            }

            // if we format enums, also format booleans to their labels yes/no
            if ($this->willFormatEnumsAsLabels()) {
                $this->formatBooleanColumnsAsLabels($exportSheet);
            }
            
            if ($lazyExport) {
                $this->writeRows($exportSheet, $columnNames);
            } else {
                $mapperAddedCols = [];
                // Don't add any columns to the master sheet if reading produced hidden/system columns
                // However, if we have mappings, they might have produced additional columns that are
                // actually needed, so we need to detect this and add the columns here. Mappings like
                // JsonToRowsMapping will add columns from the read values, so we need to check columns
                // for every page separately.
                if ($exportMapper !== null && $pageSheet->getColumns()->count() !== $exportSheet->getColumns()->count()) {
                    foreach ($exportSheet->getColumns() as $exportCol) {
                        $exportColExpr = $exportCol->getExpressionObj();
                        $exportColInPageSheet = $pageSheet->getColumns()->getByExpression($exportColExpr);
                        $exportColInMaster = $dataSheetMaster->getColumns()->getByExpression($exportColExpr);
                        if (! $exportCol->getHidden() && ! $exportColInPageSheet && ! $exportColInMaster) {
                            $mapperAddedCols[] = $exportCol;
                            $dataSheetMaster->getColumns()->addFromExpression($exportColExpr, $exportCol->getName());
                        }
                    }
                }
                $dataSheetMaster->addRows($exportSheet->getRows(), false, false);
            }
            
            // Reset the time limit for each iteration, so that we don't run into a timeout for large exports.
            set_time_limit($this->getLimitTimePerRequest());
        }
        
        if (! $lazyExport) {
            $columnNames = $this->writeHeader($this->getExportColumnWidgets($exportedWidget, $dataSheetMaster, $mapperAddedCols));
            $this->writeRows($dataSheetMaster instanceof PivotSheetInterface ? $dataSheetMaster->getPivotResultDataSheet() : $dataSheetMaster, $columnNames);
        }
        
        // Write file to disk and return a file result for download.
        $this->writeFileResult($dataSheetMaster);
        $result = ResultFactory::createFileResultFromPath($task, $this->getFilePathAbsolute(), $this->isDownloadable());
        
        if ($errorMessage !== null) {
            $result->setMessage($errorMessage);
        }
        
        return $result;
    }
    
    /**
     * Aborts the export up-front if the estimated total read time exceeds `limit_time_total`.
     * 
     * Delegates the actual sampling and extrapolation to DataSheet::estimateReadDuration(). The
     * guard is a no-op unless `limit_time_total` is set. 
     * 
     * @param DataSheetInterface $dataSheetMaster
     * @param int $sampleSize
     * @throws ActionRuntimeError if the extrapolated read time exceeds the configured limit
     * @return void
     */
    protected function checkEstimatedExportTime(DataSheetInterface $dataSheetMaster, int $sampleSize) : void
    {
        $budget = $this->getLimitTimeTotal();
        if ($budget === null) {
            return;
        }
        
        try {
            $estimate = $dataSheetMaster->estimateReadDuration($sampleSize);
        } catch (\Throwable $e) {
            return;
        }
        
        if ($estimate !== null && $estimate > $budget) {
            throw new ActionRuntimeError($this, 'Export aborted: estimated read time of ' . round($estimate) . ' seconds exceeds the limit of ' . $budget . ' seconds.', '87NS89T');
        }
    }
    
    /**
     * Returns the number of rows to sample when estimating the read time (see checkEstimatedExportTime()).
     * 
     * Uses the pagination size of the widget the export is called from. Falls back to 30 if the
     * widget or its page size is unknown.
     * 
     * @param WidgetInterface|null $widget
     * @return int
     */
    protected function getEstimateSampleSize(?WidgetInterface $widget) : int
    {
        $default = 30;
        switch (true) {
            case $widget instanceof iUseData:
                $dataWidget = $widget->getData();
                break;
            case $widget instanceof iShowData:
                $dataWidget = $widget;
                break;
            default:
                return $default;
        }
        if (! ($dataWidget instanceof Data)) {
            return $default;
        }
        return $dataWidget->getPaginator()->getPageSize($default) ?? $default;
    }

    /**
     * Reads all matching data for an object without a UID in a single request.
     * 
     * Without a UID there is no stable, unique sort order to paginate over deterministically,
     * so splitting the read into multiple requests could produce duplicate or missing rows.
     * Everything is therefore read at once and yielded as a single page (if non-empty).
     * 
     * @param DataSheetInterface $dataSheetMaster
     * @param DataSheetMapperInterface|null $exportMapper
     * @return \Generator<DataSheetInterface>
     */
    private function readPageWithoutUid(DataSheetInterface $dataSheetMaster, ?DataSheetMapperInterface $exportMapper) : \Generator
    {
        $pageSheet = $this->createPageSheet($dataSheetMaster, $exportMapper);
        $pageSheet->removeRows();
        $pageSheet->setRowsLimit(null);
        $pageSheet->dataRead();
        
        if ($pageSheet->countRows() > 0) {
            yield $pageSheet;
        }
    }
    
    /**
     * Reads matching data for an object with a UID page by page via a keyset cursor.
     * 
     * Since large exports are split into several requests, we MUST sort over a unique attribute.
     * Otherwise, the results of subsequent requests may contain data in different order resulting
     * in duplicate or missing rows from the point of view of the entire (combined) export. Each
     * non-empty page is yielded to the caller for processing.
     * 
     * @param DataSheetInterface $dataSheetMaster
     * @param DataSheetMapperInterface|null $exportMapper
     * @return \Generator<DataSheetInterface>
     */
    private function readPagesByUid(DataSheetInterface $dataSheetMaster, ?DataSheetMapperInterface $exportMapper) : \Generator
    {
        // Sort over the unique UID attribute so pages combine to a stable, gap-free sequence.
        $dataSheetMaster->getSorters()->removeAll();
        $dataSheetMaster->getSorters()->addFromString($dataSheetMaster->getMetaObject()->getUidAttributeAlias());
        $uidCol = $dataSheetMaster->getMetaObject()->getUidAttributeAlias();
        
        $rowsOnPage = $this->getLimitRowsPerRequest();
        $pageSheet = $this->createPageSheet($dataSheetMaster, $exportMapper);
        $offsetFilter = null;
        $cursorValue = null;    // largest UID of the previous page (keyset cursor)
        $cursorSeenUids = [];   // exact UIDs from the last iteration that collate-equal to current $cursorValue
        $firstPage = true;
        
        do {
            if (! $firstPage) {
                if ($offsetFilter !== null) {
                    $pageSheet->getFilters()->removeCondition($offsetFilter);
                }

                // ">=" instead of ">": under a case-insensitive DB collation, UIDs differing
                // only in case (e.g. "abc" vs "ABC") collate as equal, so a strict ">" could
                // skip them - and the ORDER BY tie order is itself non-deterministic across
                // separate page queries. ">=" never skips; the boundary group is simply
                // re-read and stripped below via an exact, case-sensitive comparison.
                $offsetFilter = ConditionFactory::createFromExpressionString(
                    $pageSheet->getMetaObject(),
                    $uidCol,
                    $cursorValue,
                    ComparatorDataType::GREATER_THAN_OR_EQUALS,
                );

                $pageSheet->getFilters()->addCondition($offsetFilter);
            }
            $firstPage = false;
            
            $pageSheet->removeRows();
            $pageSheet->setRowsLimit($rowsOnPage);
            $pageSheet->dataRead();
            
            // Number of rows actually read - captured before de-duplication because it
            // drives loop termination (see the while-condition below).
            $rowsRead = $pageSheet->countRows();
            
            if ($rowsRead === 0) {
                break;
            }
            
            // The ">=" cursor re-reads the previous page's boundary group (all rows whose
            // UID collates equal to $cursorValue). Drop the ones already exported. Exact
            // string comparison => genuinely distinct case-variant UIDs are preserved.
            $this->stripAlreadyExportedRows($pageSheet, $cursorSeenUids);
            
            // If a single collated UID value spans more rows than fit on a page, the whole
            // page is the re-read boundary group: after de-duplication nothing remains and the
            // cursor cannot advance. Rather than failing immediately, progressively double the
            // page size and re-read (up to $maxDoublings times) to try to read past the group.
            $doublings = 0;
            $maxDoublings = 3;
            while ($rowsRead === $rowsOnPage && $pageSheet->countRows() === 0) {
                if ($doublings >= $maxDoublings) {
                    throw new ActionRuntimeError($this, 'Cannot paginate export: more than ' . $rowsOnPage . ' rows share the same UID value "' . $cursorValue . '" under the data source collation. Increase the rows-per-request limit for this export.');
                }
                $rowsOnPage *= 2;
                $doublings++;
                $pageSheet->removeRows();
                $pageSheet->setRowsLimit($rowsOnPage);
                $pageSheet->dataRead();
                $rowsRead = $pageSheet->countRows();
                $this->stripAlreadyExportedRows($pageSheet, $cursorSeenUids);
            }
            
            // Advance the cursor to the largest UID on this page (rows are sorted ascending by UID)
            $uidValues = $pageSheet->getUidColumn()->getValues();
            $cursorValue = end($uidValues);
            
            // Remember every exact UID that collates equal to the cursor, so the next
            // iteration can strip the re-reads. The comparison is case-insensitive (and
            // multibyte-aware) so the boundary group is over-approximated relative to a
            // case-insensitive DB collation - which is safe, because the actual removal keyed
            // on this set uses an exact string match and can never drop a distinct UID.
            $cursorValueFolded = mb_strtolower((string)$cursorValue);
            $cursorSeenUids = [];
            foreach ($uidValues as $uid) {
                if (mb_strtolower((string)$uid) === $cursorValueFolded) {
                    $cursorSeenUids[(string)$uid] = true;
                }
            }
            
            // Skip pages that only contained already-exported re-reads.
            if ($pageSheet->countRows() === 0) {
                break;
            }
            
            yield $pageSheet;
        } while ($rowsRead === $rowsOnPage);
    }

    /**
     * Creates the working copy of the master sheet used to read a single page of data.
     * 
     * The copy additionally gets any columns required by the export mapper - these are only
     * needed while reading (as mapper input) and must not pollute the master sheet.
     * 
     * @param DataSheetInterface $dataSheetMaster
     * @param DataSheetMapperInterface|null $exportMapper
     * @return DataSheetInterface
     */
    private function createPageSheet(DataSheetInterface $dataSheetMaster, ?DataSheetMapperInterface $exportMapper) : DataSheetInterface
    {
        $pageSheet = $dataSheetMaster->copy();
        if ($exportMapper !== null) {
            foreach ($exportMapper->getMappings() as $map) {
                foreach ($map->getRequiredExpressions($pageSheet) as $req) {
                    if (! $pageSheet->getColumns()->getByExpression($req)) {
                        $pageSheet->getColumns()->addFromExpression($req);
                    }
                }
            }
        }
        return $pageSheet;
    }
    
    /**
     * Removes rows from $pageSheet whose UID was already exported on the previous page.
     * 
     * When paginating via a ">=" UID cursor (see perform()), the boundary group - all rows
     * whose UID collates equal to the cursor value under the data source collation - is
     * re-read on the next page. This strips those already-exported rows again using an exact,
     * case-sensitive string comparison, so that genuinely distinct UIDs which merely collate
     * equal (e.g. "abc" vs "ABC" under a case-insensitive collation) are preserved.
     * 
     * @param DataSheetInterface $pageSheet
     * @param bool[] $exportedUids exact UID strings (as keys) already written to the output
     * @return void
     */
    protected function stripAlreadyExportedRows(DataSheetInterface $pageSheet, array $exportedUids) : void
    {
        if (empty($exportedUids)) {
            return;
        }
        $duplicateIndexes = [];
        foreach ($pageSheet->getUidColumn()->getValues() as $rowIdx => $uid) {
            if (isset($exportedUids[(string)$uid])) {
                $duplicateIndexes[] = $rowIdx;
            }
        }
        if (! empty($duplicateIndexes)) {
            $pageSheet->removeRows($duplicateIndexes);
        }
    }

    protected function writeHeader(array $exportedColumns) : array
    {
        $header = [];
        foreach ($exportedColumns as $widget) {
            if ($widget instanceof iShowDataColumn && $widget->isBoundToDataColumn()) {
                if (! $this->isColumnExportable($widget)) {
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
     * @param \exface\Core\Interfaces\WidgetInterface $exportedWidget
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $exportedSheet
     * @param \exface\Core\Interfaces\DataSheets\DataColumnInterface[] $additionalColumns
     * @return WidgetInterface[]
     */
    protected function getExportColumnWidgets(WidgetInterface $exportedWidget, DataSheetInterface $exportedSheet, array $additionalColumns = []) : array
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

        // If the widget has a datatable configurator:
        // -> add optional columns, that are really exported in the datasheet 
        // if we are using the exportDataSheet as reference: only keep columns sent in DataSheet (visible ones) and re-order according to exportSheet
        if (($exportedWidget instanceof iHaveConfigurator) && ($configurator = $exportedWidget->getConfiguratorWidget()) instanceof DataTableConfigurator) {

            /** @var $column \exface\Core\Widgets\DataColumn */
            foreach ($configurator->getOptionalColumns() as $column) {
                if ($exportedSheet->getColumns()->has($column->getDataColumnName())) {
                    $widgets[] = $column;
                }
            }
            
            // only keep columns that are in the source sheet
            // and order columns to match the order in the source sheet
            if ($this->getUseExportDataSheet($exportedWidget) === true) {
                $orderedColumns = [];
                foreach ($exportedSheet->getColumns() as $sheetCol) {
                    foreach ($widgets as $column) {
                        if ($column->getDataColumnName() === $sheetCol->getName()) {
                            $orderedColumns[] = $column;
                            break;
                        }
                    }
                }
                $widgets = $orderedColumns;
            }
        }

        foreach ($additionalColumns as $sheetCol) {
            $widgets[] = WidgetFactory::createFromUxonInParent($exportedWidget, new UxonObject([
                'widget_type' => 'DataColumn',
                'caption' => $sheetCol->getExpressionObj()->__toString(),
                'data_column_name' => $sheetCol->getName()
            ]));
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
     * Returns whether the exported DataSheet of the given widget should be used as the source of truth 
     * for the export, over the widget columns. This only works for DataTables as of now, but allows exports to match the
     * datatable configuration (e.g. visible columns, column order)
     * 
     * @param WidgetInterface $exportedWidget
     * @return bool
     */
    private function getUseExportDataSheet(WidgetInterface $exportedWidget): bool
    {
        // we only want this if its a DataTable (with configurator), if export_all_widget_columns is set to false
        // and its not a PivotTable or DataMatrix (because those should not get re-ordered)
        if (($this->getExportAllWidgetColumns() === false) && 
            ($exportedWidget instanceof iHaveConfigurator) && 
            ($exportedWidget->getConfiguratorWidget()) instanceof DataTableConfigurator &&
            !($exportedWidget instanceof PivotTable) &&
            !($exportedWidget instanceof DataMatrix)) {
            return true;
        }
        return false;
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
     * Formats boolean data columns using their data type formatter (e.g. Yes/No).
     *
     * @param DataSheetInterface $sheet
     * @return void
     */
    protected function formatBooleanColumnsAsLabels(DataSheetInterface $sheet) : void
    {
        foreach ($sheet->getColumns() as $col) {
            $type = $col->getDataType();
            if (! $type instanceof BooleanDataType) {
                continue;
            }
            $values = $col->getValues();
            $newValues = [];
            foreach ($values as $val) {
                $newValues[] = $type->format($val);
            }
            $col->setValues($newValues);
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
     * Sets how many rows are fetched per batch (default 10000).
     * 
     * The export reads all matching rows, but does so in several smaller batches to save memory
     * (see the class description). This property controls the batch size: a smaller value uses
     * less memory per batch but needs more batches, a larger value is faster but needs more memory.
     * 
     * Lower this value (e.g. to 5000 or 1000) if an export fails with a memory error like
     * "allowed memory size exhausted".
     * 
     * Note: this only has an effect for objects that have a unique identifier (UID). Objects
     * without a UID are always read in a single request (see the class description).
     *
     * @uxon-property limit_rows_per_request
     * @uxon-type integer
     * @uxon-default 10000
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
     * Returns whether all widget columns should be exported or only currently visible ones.
     *
     * @return bool
     */
    public function getExportAllWidgetColumns() : bool
    {
        return $this->exportAllWidgetColumns;
    }
    
    /**
     * Sets whether or not all columns of the widget, or only the ones passed in the datasheet (visible ones) should be exported. (default is false)
     * 
     * @uxon-property export_all_widget_columns
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return \exface\Core\Actions\ExportJSON
     */
    public function setExportAllWidgetColumns(bool $value) : ExportJSON
    {
        $this->exportAllWidgetColumns = $value;
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
     * Sets how long a single batch may take before it is aborted, in seconds (default 300).
     * 
     * The export reads its data in several batches (see the class description). This is the time
     * limit for ONE batch, not for the whole export - it is reset for every batch. If a batch
     * takes longer than this, the system assumes something went wrong and stops.
     * 
     * Raise this value if an export fails with a time error like "maximum execution time exceeded",
     * to give each batch more time to finish.
     *
     * @uxon-property limit_time_per_request
     * @uxon-type integer
     * @uxon-default 300
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
     * Returns the total time budget for the whole export in seconds or NULL if disabled.
     *
     * @return int|null
     */
    public function getLimitTimeTotal() : ?int
    {
        $config = $this->getWorkbench()->getCoreApp()->getConfig();
        return $config->hasOption("EXPORT.MAX_PROCESSING_TIME") ? 
            $config->getOption("EXPORT.MAX_PROCESSING_TIME") : null;
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
        if ($this->getExportMapper() !== null) {
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

    /**
     * @return bool
     */
    protected function willFormatEnumsAsLabels() : bool
    {
        return $this->formatEnums;
    }

    /**
     * Set to FALSE to keep raw values for enumerations - e.g. the status id instead of its name
     * 
     * @uxon-property format_enums_as_labels
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return $this
     */
    protected function setFormatEnumsAsLabels(bool $trueOrFalse) : ExportJSON
    {
        $this->formatEnums = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function isPaginationDisabled(): bool
    {
        return $this->paginationDisabled;
    }

    /**
     * Set to TRUE to disable pagination and read all data in a single request.
     * This can lower the performance but will keep the original sorting of the rows.
     * 
     * @uxon-property pagination_disabled
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $paginationDisabled
     * @return ExportJSON
     */
    public function setPaginationDisabled(bool $paginationDisabled): ExportJSON
    {
        $this->paginationDisabled = $paginationDisabled;
        return $this;
    }
}