<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\DataQueries\FileReadDataQuery;
use exface\Core\CommonLogic\Filesystem\LocalFileInfo;
use exface\Core\DataConnectors\FileContentsConnector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileStreamInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Worksheet\Validations;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\DateDataType;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use \PhpOffice\PhpSpreadsheet\Shared\Date;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\DataQueries\DataSourceFileInfo;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * A query builder to access Excel files (or similar spreadsheets).
 * 
 * **WARNING:** currently only reading is supported and only that of entire worksheets.
 * The pagination is virtual, so even if the first 10 rows are requested, all rows will
 * be read from the Excel and filtered in-memory.
 * 
 * ## Data source configuration
 * 
 * To access Excel files create a data source with this query builder and a file connection - e.g. one with the 
 * `LocalFileConnector` or `DataSourceFileConnector`.
 * 
 * ## Object data addresses
 * 
 * The meta object address should point to a worsheet within the document. It follows the excel reference format.
 * Example:
 * 
 * - `path/from/installation/root/excel_file.xlsx` will access the default worksheet (the one, that is shown when
 * opening the file in Excel).
 * - `path/from/installation/root/excel_file.xlsx[My sheet]` will access the sheet named `My sheet`
 * 
 * ### Data address properties on object-level
 * 
 * - `EXCEL_FILL_MERGED_CELLS` - if set to FALSE will prevent values of merged cells from being filled into all cells
 * of the merge range.
 * 
 * ## Attribute data addresses
 * 
 * Attributes of the meta objects can be
 * 
 * - cell ranges in a single column (e.g. `A1:A999`). Be sure to include enough rows for eventual modifications in the
 * excel  file! Don't worry about empty rows - only rows with values will actually be read!
 * - cell ranges in multiple columns (e.g. `A10:B20`) - all values will be read into a single data column row-by-row
 * from  left to right.
 * - single cell coordinates (e.g. `B3`) will add the value of the cell to each row - handy for reading header data for
 * a table.
 * - Any file attributes as described in `FileBuilder`
 * 
 * ## Known issues and TODOs
 * 
 * TODO Add real pagination using read-filters
 * TODO Add writing capabilities
 *
 * @author Andrej Kabachnik
 *        
 */
class ExcelBuilder extends FileBuilder
{    
    /**
     * Set to FALSE to keep the value of a merged cell in the first (top left) cell only.
     * 
     * By default the value of a merged cell is replicated into every cell of the merge
     * range. If set to FALSE, the value will be kept in the top left cell only - just
     * like if you would unmerge the cell in Excel.
     *
     * @uxon-property excel_fill_merged_cells
     * @uxon-target object
     * @uxon-default true
     * @uxon-type boolean
     */
    const DAP_EXCEL_FILL_MERGED_CELLS = 'excel_fill_merged_cells';
    
    /**
     * Set to FALSE to return an empty result if the target excel sheet is not found instead of raising an error.
     *
     * @uxon-property excel_error_if_sheet_not_found
     * @uxon-target object
     * @uxon-default true
     * @uxon-type boolean
     */
    const DAP_EXCEL_ERROR_IF_SHEET_NOT_FOUND = 'excel_error_if_sheet_not_found';
    
    /**
     * Set to TRUE to read only data, no formatting information, etc. - this consumes less memory.
     *
     * @uxon-property excel_read_data_only
     * @uxon-target object
     * @uxon-type boolean
     * @uxon-default false
     */
    const DAP_EXCEL_READ_DATA_ONLY = 'excel_read_data_only';
    
    /**
     * Set to FALSE to skip reading empty cells saving some more memory on large files.
     *
     * @uxon-property excel_read_empty_cells
     * @uxon-target object
     * @uxon-type boolean
     * @uxon-default true
     */
    const DAP_EXCEL_READ_EMPTY_CELLS = 'excel_read_empty_cells';
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return string|null
     */
    protected function getSheetForObject(MetaObjectInterface $object) : ?string
    {
        $addr = trim($object->getDataAddress());
        $sheetInDataAddress = trim(str_replace($this->getPathForObject($object, false), '', $addr), "[]");
        if ($sheetInDataAddress !== '') {
            return $sheetInDataAddress;
        }
        return null;
    }

    private $tempFiles = [];
    private array $ranges = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileBuilder::getPathForObject()
     */
    protected function getPathForObject(MetaObjectInterface $object, bool $replacePlaceholders = true) : string
    {
        $addr = trim($object->getDataAddress());
        $delim = strrpos($addr, '[');
        if ($delim !== false) {
            if (substr($addr, -1) === ']') {
                $path = substr($addr, 0, $delim);
            } else {
                throw new QueryBuilderException('Invalid data address syntax "' . $addr . '" for object ' . $object->getAliasWithNamespace());
            }
        } else {
            $path = $addr;
        }
        
        if ($replacePlaceholders) {
            $path = $this->replacePlaceholdersByFilterValues($path);
        }
        
        return $path;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $this->prepareFilters();
        $this->prepareSorters();
        // Keep compatibilty to legacy objects from the times, when there was no filesystem abstraction
        if (! ($data_connection instanceof FileContentsConnector)) {
            return parent::read($data_connection);
        } else {
            return $this->readFromLegacyFileContentsConnector($data_connection);
        }
    }

    private function readFromLegacyFileContentsConnector(FileContentsConnector $data_connection) : DataQueryResultDataInterface
    {
        $query = new FileContentsDataQuery();
        $query->setPath($this->getPathForObject($this->getMainObject()));
        $query = $data_connection->query($query);
        $deleteAfterRead = false;
        if (null !== ($fileInfo = $query->getFileInfo()) && $fileInfo instanceof DataSourceFileInfo) {
            $contents = $fileInfo->getContents();
            if ($contents === null) {
                throw new QueryBuilderException('Cannot read spreadsheet from "' . $fileInfo->getFilename() . '": fetching contents failed!');
            }
            $tmpFolder = $this->getWorkbench()->filemanager()->getPathToTempFolder() . DIRECTORY_SEPARATOR . 'ExcelBuilderCache' . DIRECTORY_SEPARATOR;
            if (! file_exists($tmpFolder)) {
                mkdir($tmpFolder);
            }
            $excelPath = $tmpFolder . $query->getFileInfo()->getFilename();
            file_put_contents($excelPath, $contents);
            $deleteAfterRead = true;
        } else {
            $excelPath = $query->getPathAbsolute();
        }

        $fileInfo = new LocalFileInfo($excelPath);
        $result_rows = $this->buildResultRows($fileInfo);
        
        $resultTotalRows = count($result_rows);
        
        $result_rows = $this->applyFilters($result_rows);
        $result_rows = $this->applySorting($result_rows);
        $result_rows = $this->applyAggregations($result_rows, $this->getAggregations());
        $result_rows = $this->applyPagination($result_rows);
        
        $cnt = count($result_rows);
        
        if ($deleteAfterRead === true) {
            @unlink($excelPath);
        }
        
        return new DataQueryResultData($result_rows, $cnt, ($resultTotalRows > $cnt+$this->getOffset()), $resultTotalRows);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileBuilder::buildResultRows()
     */
    protected function buildResultRows(FileInfoInterface $fileInfo) : array
    {
        $result_rows = [];
        $mainObj = $this->getMainObject();
        $excelPath = $this->getStreamablePath($fileInfo);

        $dapReadDataOnly = BooleanDataType::cast($mainObj->getDataAddressProperty(self::DAP_EXCEL_READ_DATA_ONLY)) ?? false;
        $dapReadEmptyCells = BooleanDataType::cast($mainObj->getDataAddressProperty(self::DAP_EXCEL_READ_EMPTY_CELLS)) ?? true;
        $dapErrorIfNoSheet = BooleanDataType::cast($mainObj->getDataAddressProperty(self::DAP_EXCEL_ERROR_IF_SHEET_NOT_FOUND)) ?? true;
        
        $sheetName = $this->getSheetForObject($mainObj);
        
        $reader = IOFactory::createReaderForFile($excelPath);
        // Add performance-related settings
        $reader->setReadDataOnly($dapReadDataOnly);
        $reader->setReadEmptyCells($dapReadEmptyCells);
        // Make sure, only our target sheet is read as this will save memory on files with many large sheets
        if ($sheetName !== null) {
            $reader->setLoadSheetsOnly($sheetName);
        }
        // Do read
        $spreadsheet = $reader->load($excelPath);
        // Get the sheet
        $worksheet = $sheetName !== null ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();
        
        if (! $worksheet) {
            if ($dapErrorIfNoSheet) {
                throw new QueryBuilderException('Worksheet "' . $sheetName . '" not found in spreadsheet "' . $fileInfo->getPathAbsolute() . '"!');
            } else {
                return [];
            }
        }
        
        $lastRow = $worksheet->getHighestDataRow();
        $static_values = [];
        $this->ranges = [];
        
        foreach ($this->getAttributes() as $qpart) {
            $colKey = $qpart->getColumnKey();
            $address = $qpart->getDataAddress();
            $attrType = $qpart->getDataType();
            switch (true) {
                // IDEA some data types may require formatted output. Add them here. Date/time was one of
                // them at the beginning, but moved to explicit conversion later - see below.
                // case $attrType instanceof DateDataType: $formatValues = true; break;
                default: $formatValues = false;
            }
            switch (true) {
                case $this->isFileDataAddress($address):
                    $static_values[$colKey] = $this->buildResultValueFromFile($fileInfo, $address);
                    continue 2;
                case $this->isAddressRange($address):
                    $resultRowNo = 0;
                    foreach ($this->getValuesOfRange($worksheet, $address, $formatValues) as $sheetRowNo => $colVals) {
                        if ($sheetRowNo > $lastRow) {
                            break;
                        }
                        foreach ($colVals as $val) {
                            $result_rows[$resultRowNo][$colKey] = $this->parseExcelValue($val, $attrType);
                            $resultRowNo += 1;
                        }
                    }
                    break;
                case $this->isAddressCoordinate($address):
                    $val = $this->getValueOfCoordinate($worksheet, $address, $formatValues);
                    $static_values[$colKey] = $this->parseExcelValue($val, $attrType);
                    break;
                case $this->isColumnName($address):
                    // read column of given column name
                    $row_nr = 0;
                    try {
                        $range = $this->rangeToArray(
                            $worksheet, 
                            $this->addressToRange($address, $worksheet), 
                            null, 
                            true, 
                            $formatValues, 
                            true
                        );
                    } catch (\Throwable $e) {
                        throw new QueryBuilderException('Cannot read column "' . $address . '" from worksheet "' . $worksheet->getTitle() . '": ' . $e->getMessage(), null, $e);
                    }
                    foreach ($range as $sheetRowNo => $colVals) {
                        foreach ($colVals as $val) {
                            $result_rows[$row_nr][$colKey] = $this->parseExcelValue($val, $attrType);
                        }
                        $row_nr++;
                    }
                    break;
                default:
                    throw new QueryBuilderException('Invalid data address "' . $address . '" for Excel query builder in "' . $qpart->getAlias() . '"!');
            }
        }
        
        // add static values
        foreach ($static_values as $alias => $val) {
            foreach (array_keys($result_rows) as $row_nr) {
                $result_rows[$row_nr][$alias] = $val;
            }
        }
        
        // Free up memory as PHPSreadsheet is known to consume a lot of it
        unset($worksheet);
        unset($spreadsheet);
        unset($reader);

        return $result_rows;
    }

    /**
     * Converts a data address into an Excel range.
     * 
     * @param string    $address
     * @param Worksheet $worksheet
     * @return string
     */
    protected function addressToRange(string $address, Worksheet $worksheet) : string
    {
        $rangeName = trim($address, '[]');
        $rangeName = StringHelper::strToUpper($rangeName);
        $range = $this->ranges[$rangeName];
        
        // Generate ranges.
        if ($range !== null) {
            return $range;
        }
        
        // Read the first row as header data to get the column names
        $headerRow = 1;
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $headerData = $worksheet->rangeToArray("A{$headerRow}:{$highestColumn}{$headerRow}", null, true, true, true)[1];

        foreach ($headerData as $columnLetter => $columnName) {
            if ($columnName === null) {
                continue;
            }
            $columnName = trim($columnName);
            if ($columnName === '') {
                continue;
            }
            // Define the range from the second row to the last row
            $range = "{$columnLetter}2:{$columnLetter}{$highestRow}";

            // Create a named range for each column name
            $cellRange = ltrim(substr($range, (int) strrpos($range, '!')), '!');
            $cellRange = str_replace('$', '', $cellRange);
            $this->ranges[StringHelper::strToUpper($columnName)] = $cellRange;
        }

        return $this->ranges[$rangeName];
    }

    /**
     * Returns a file path compatible with file_get_contents() and other PHP built-in functions
     * 
     * @param \exface\Core\Interfaces\Filesystem\FileInfoInterface $fileInfo
     * @throws \exface\Core\Exceptions\QueryBuilderException
     * @return string
     */
    protected function getStreamablePath(FileInfoInterface $fileInfo) : string
    {
        if ($fileInfo instanceof FileStreamInterface) {
            return $fileInfo->getStreamUrl();
        }

        $contents = $fileInfo->openFile()->read();
        if ($contents === null) {
            throw new QueryBuilderException('Cannot read spreadsheet from "' . $fileInfo->getFilename() . '": fetching contents failed!');
        }
        $tmpFolder = $this->getWorkbench()->filemanager()->getPathToTempFolder() . DIRECTORY_SEPARATOR . 'ExcelBuilderCache' . DIRECTORY_SEPARATOR;
        if (! file_exists($tmpFolder)) {
            mkdir($tmpFolder);
        }
        $excelPath = $tmpFolder . $fileInfo->getFilename();
        file_put_contents($excelPath, $contents);
        $this->tempFiles[] = $excelPath;

        return $excelPath;
    }
    
    /**
     * Parses an Excel-value into the internal format for the given metamodel data type
     * 
     * @param mixed|null $value
     * @param DataTypeInterface $dataType
     * @param bool $nullOnError
     * @throws \Throwable
     * 
     * @return mixed|null
     */
    protected function parseExcelValue($value, DataTypeInterface $dataType, bool $nullOnError = true)
    {
        switch (true) {
            case $dataType instanceof DateDataType:
                if ($value === null || $value === '') {
                    $parsed = null;
                    break;
                }
                try {
                    $value = is_numeric($value) ? 
                        Date::excelToDateTimeObject($value) :
                        $dataType->cast($value, true);
                    
                    $parsed = $dataType::formatDateNormalized($value);
                } catch (\Throwable $e) {
                    if (! ($e instanceof ExceptionInterface)) {
                        $e = new QueryBuilderException($e->getMessage(), null, $e);
                    }
                    if ($nullOnError === true) {
                        $parsed = null;
                        $this->getWorkbench()->getLogger()->warning('Cannot parse excel value "' . $value . '" as date/time: ' . $e->getMessage(), [], $e);
                    } else {
                        throw $e;
                    }
                }
                break;
            default:
                $parsed = $value;
                break;
        }
        return $parsed;
    }
    
    /**
     * 
     * @param string $dataAddress
     * @return bool
     */
    protected function isAddressRange(string $dataAddress) : bool
    {
        return preg_match('/^[a-z]+\d+:[a-z]+\d+$/i', $dataAddress) === 1;
    }
    
    /**
     * 
     * @param string $dataAddress
     * @return bool
     */
    protected function isAddressCoordinate(string $dataAddress) : bool
    {
        return preg_match('/^[a-z]+\d+$/i', $dataAddress) === 1;
    }
    /**
     *
     * @param string $dataAddress
     * @return bool
     */
    protected function isColumnName(string $dataAddress) : bool
    {
        return StringDataType::startsWith($dataAddress, '[') && StringDataType::endsWith($dataAddress, ']');
    }
    
    /**
     * 
     * @param Worksheet $sheet
     * @param string $coordinate
     * @param bool $formatValues
     * @return mixed|null|\PhpOffice\PhpSpreadsheet\RichText\RichText
     */
    protected function getValueOfCoordinate(Worksheet $sheet, string $coordinate, bool $formatValues = false)
    {
        if ($formatValues) {
            return $sheet->getCell($coordinate)->getFormattedValue();
        } else {
            return $sheet->getCell($coordinate)->getValue();
        }
    }

    /**
     *
     * @param Worksheet $sheet
     * @param string    $range
     * @param bool      $formatValues
     * @return array
     * @throws Exception
     */
    protected function getValuesOfRange(Worksheet $sheet, string $range, bool $formatValues = false) : array
    {
        $fill = BooleanDataType::cast($this->getMainObject()->getDataAddressProperty(self::DAP_EXCEL_FILL_MERGED_CELLS) ?? true);
        if ($fill && $this->hasMergedCells($sheet, $range)) {
            return $this->rangeToArrayUnmerged(
                $sheet,
                $range,         // The worksheet range that we want to retrieve
                null,           // Value that should be returned for empty cells
                true,           // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
                $formatValues,  // Should values be formatted (the equivalent of getFormattedValue() for each cell)
                true            // Should the array be indexed by cell row and cell column
            );
        }
        return $this->rangeToArray(
            $sheet,
            $range,             // The worksheet range that we want to retrieve
            null,               // Value that should be returned for empty cells
            true,               // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
            $formatValues,      // Should values be formatted (the equivalent of getFormattedValue() for each cell)
            true                // Should the array be indexed by cell row and cell column
        );
    }

    /**
     * Create array from a range of cells.
     *
     * @param mixed $nullValue Value returned in the array entry if a cell doesn't exist
     * @param bool  $calculateFormulas Should formulas be calculated?
     * @param bool  $formatData Should formatting be applied to cell values?
     * @param bool  $returnCellRef False - Return a simple array of rows and columns indexed by number counting from
     *     zero True - Return rows and columns indexed by their actual row and column IDs
     * @param bool  $ignoreHidden False - Return values for rows/columns even if they are defined as hidden.
     *                            True - Don't return values for rows/columns that are defined as hidden.
     * @throws Exception
     */
    public function rangeToArray(
        Worksheet $worksheet,
        string $range,
        mixed $nullValue = null,
        bool $calculateFormulas = true,
        bool $formatData = true,
        bool $returnCellRef = false,
        bool $ignoreHidden = false
    ): array|string {
        $cellCollection = $worksheet->getCellCollection();
        $range = Validations::validateCellOrCellRange($range);

        $returnValue = [];
        //    Identify the range that we need to extract from the worksheet
        [$rangeStart, $rangeEnd] = Coordinate::rangeBoundaries($range);
        $minCol = Coordinate::stringFromColumnIndex($rangeStart[0]);
        $minRow = $rangeStart[1];
        $maxCol = Coordinate::stringFromColumnIndex($rangeEnd[0]);
        $maxRow = $rangeEnd[1];
        ++$maxCol;
        // Loop through rows
        $r = -1;
        for ($row = $minRow; $row <= $maxRow; ++$row) {
            if (($ignoreHidden === true) && ($worksheet->getRowDimension($row)->getVisible() === false)) {
                continue;
            }
            $rowRef = $returnCellRef ? $row : ++$r;
            $c = -1;
            // Loop through columns in the current row
            for ($col = $minCol; $col !== $maxCol; ++$col) {
                if (($ignoreHidden === true) && ($worksheet->getColumnDimension($col)->getVisible() === false)) {
                    continue;
                }
                $columnRef = $returnCellRef ? $col : ++$c;
                //    Using getCell() will create a new cell if it doesn't already exist. We don't want that to happen
                //        so we test and retrieve directly against cellCollection
                $cell = $cellCollection->get($col . $row);
                $returnValue[$rowRef][$columnRef] = $nullValue;
                if ($cell !== null) {
                    $returnValue[$rowRef][$columnRef] = $this->getCellValue(
                        $cell,
                        $nullValue,
                        $calculateFormulas,
                        $formatData
                    );
                }
            }
        }

        // Return
        return $returnValue;
    }

    /**
     *
     * @param Worksheet  $sheet
     * @param string     $pRange
     * @param mixed|null $nullValue
     * @param boolean    $calculateFormulas
     * @param boolean    $formatData
     * @param boolean    $returnCellRef
     *
     * @return array|string
     * @throws Exception
     */
    protected function rangeToArrayUnmerged(
        Worksheet $sheet,
        string    $pRange,
        mixed     $nullValue = null,
        bool      $calculateFormulas = true,
        bool      $formatData = true,
        bool      $returnCellRef = false
    ) : array|string
    {
        // Return value
        $returnValue = [];
        //    Identify the range that we need to extract from the worksheet
        [$rangeStart, $rangeEnd] = Coordinate::rangeBoundaries($pRange);
        $minCol = Coordinate::stringFromColumnIndex($rangeStart[0]);
        $minRow = $rangeStart[1];
        $maxCol = Coordinate::stringFromColumnIndex($rangeEnd[0]);
        $maxRow = $rangeEnd[1];
        $cellCollection = $sheet->getCellCollection();
        
        ++$maxCol;
        // Loop through rows
        $r = -1;
        for ($row = $minRow; $row <= $maxRow; ++$row) {
            $rRef = $returnCellRef ? $row : ++$r;
            $c = -1;
            // Loop through columns in the current row
            for ($col = $minCol; $col != $maxCol; ++$col) {
                $cRef = $returnCellRef ? $col : ++$c;
                //    Using getCell() will create a new cell if it doesn't already exist. We don't want that to happen
                //        so we test and retrieve directly against cellCollection
                if ($cellCollection->has($col . $row)) {
                    // Cell exists
                    $cell = $cellCollection->get($col . $row);
                    $mergeRange = $cell->getMergeRange();
                    if ($mergeRange !== null) {
                        $mergeStart = Coordinate::rangeBoundaries($mergeRange)[0];
                        $cell = $sheet->getCellByColumnAndRow($mergeStart[0], $mergeStart[1]);
                    }
                    
                    $returnValue[$rRef][$cRef] = $this->getCellValue(
                        $cell,
                        $nullValue,
                        $calculateFormulas,
                        $formatData
                    );
                } else {
                    // Cell doesn't exist
                    $returnValue[$rRef][$cRef] = $nullValue;
                }
            }
        }
        
        // Return
        return $returnValue;
    }

    /**
     * Tries to get and format a cell value, according to the function parameters.
     * 
     * @param Cell       $cell
     * @param mixed|null $nullValue
     * @param bool       $calculateFormulas
     * @param bool       $formatData
     * @return mixed
     * @throws \PhpOffice\PhpSpreadsheet\Calculation\Exception
     */
    protected function getCellValue(
        Cell $cell,
        mixed $nullValue = null,
        bool $calculateFormulas = true,
        bool $formatData = true,
    ) : mixed
    {
        $value = $cell->getValue();
        if ($value !== null) {
            if ($value instanceof RichText) {
                $result = $value->getPlainText();
            } else {
                if ($calculateFormulas) {
                    $result = $cell->getCalculatedValue();
                } else {
                    $result = $value;
                }
            }

            if ($formatData || Date::isDateTime($cell)) {
                $style = $cell->getStyle();
                $result = NumberFormat::toFormattedString(
                    $result,
                    $style->getNumberFormat() ? $style->getNumberFormat()->getFormatCode() : NumberFormat::FORMAT_GENERAL
                );
            }
        } else {
            // Cell holds a NULL
            $result = $nullValue;
        }
        
        return $result;
    }
    
    /**
     * 
     * @param Worksheet $sheet
     * @param string $range
     * @return bool
     */
    protected function hasMergedCells(Worksheet $sheet, string $range) : bool
    {
        $merges = $sheet->getMergeCells();
        if (empty($merges)) {
            return false;
        }
        $range = $sheet->shrinkRangeToFit($range);
        $rangeCells = Coordinate::extractAllCellReferencesInRange($range);
        foreach ($merges as $mergeRange) {
            $mergedCells = Coordinate::extractAllCellReferencesInRange($mergeRange);
            $intercect = array_intersect($rangeCells, $mergedCells);
            if (! empty($intercect)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * @throws \exface\Core\Exceptions\QueryBuilderException
     * @return void
     */
    protected function prepareFilters()
    {
        foreach ($this->getFilters()->getFilters() as $qpart) {
            switch (true) {
                case $this->isFileProperty($qpart):
                    if (! $this->getAttribute($qpart->getAlias())) {
                        // FIXME What to do with filters over missing file values??? Error? Ignore? Auto-add to read?
                        throw new QueryBuilderException('Cannot filter "' . $this->getMainObject() . '" over "' . $qpart->getAlias(). '" - no correspoinding column is being read!');
                    }
                default:
                    $qpart->setApplyAfterReading(true);
            }
        }
        
        return;
    }
    
    /**
     * 
     * @return void
     */
    protected function prepareSorters()
    {
        foreach ($this->getSorters() as $qpart) {
            $qpart->setApplyAfterReading(true);
        }
        
        return;
    }

    public function __destruct()
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileBuilder::isFullReadRequired()
     */
    protected function isFullReadRequired(FileReadDataQuery $query) : bool
    {
        return true;
    }
}