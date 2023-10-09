<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
 * To access Excel files create a data source with this query builder and a connection with the `FileContentsConnector`
 * or the `DataSourceFileContentsConnector`.
 * 
 * ## Object data addresses
 * 
 * The meta object address should point to a worsheet within the document. It follows the excel reference format. Example:
 * 
 * - `path/from/installation/root/excel_file.xlsx` will access the default worksheet (the one, that is shown when opening
 * the file in Excel).
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
 * - cell ranges in a single column (e.g. `A1:A999`). Be sure to include enough rows for eventual modifications in the excel 
 * file! Don't worry about empty rows - only rows with values will actually be read!
 * - cell ranges in multiple columns (e.g. `A10:B20`) - all values will be read into a single data column row-by-row from 
 * left to right.
 * - single cell coordinates (e.g. `B3`) will add the value of the cell to each row - handy for reading header data for
 * a table.
 * 
 * ## Known issues and TODOs
 * 
 * TODO Add real pagination using read-filters
 * TODO Add writing capabilities
 *
 * @author Andrej Kabachnik
 *        
 */
class ExcelBuilder extends FileContentsBuilder
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
     * @return string|NULL
     */
    protected function getSheetForObject(MetaObjectInterface $object) : ?string
    {
        $addr = trim($object->getDataAddress());
        return trim(str_replace($this->getPathForObject($object, false), '', $addr), "[]");
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::getPathForObject()
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
        
        return $path ?? '';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $result_rows = [];
        $mainObj = $this->getMainObject();
        
        $query = $this->buildQuery();
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
        
        $dapReadDataOnly = BooleanDataType::cast($mainObj->getDataAddressProperty(self::DAP_EXCEL_READ_DATA_ONLY)) ?? false;
        $dapReadEmptyCells = BooleanDataType::cast($mainObj->getDataAddressProperty(self::DAP_EXCEL_READ_EMPTY_CELLS)) ?? true;
        $dapErrorIfNoSheet = BooleanDataType::cast($mainObj->getDataAddressProperty(self::DAP_EXCEL_ERROR_IF_SHEET_NOT_FOUND)) ?? true;
        
        $sheetName = $this->getSheetForObject($mainObj);
        
        $reader = IOFactory::createReaderForFile($excelPath);
        // Add performance-related settings
        $reader->setReadDataOnly($dapReadDataOnly);
        $reader->setReadEmptyCells($dapReadEmptyCells);
        // Make sure, only our target sheet is read as this will save memory on files with many large sheets
        $reader->setLoadSheetsOnly($sheetName);
        // Do read
        $spreadsheet = $reader->load($excelPath);
        // Get the sheet
        $sheet = $sheetName !== null && $sheetName !== '' ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();
        
        if (! $sheet) {
            if ($dapErrorIfNoSheet) {
                throw new QueryBuilderException('Worksheet "' . $sheetName . '" not found in spreadsheet "' . $query->getPathAbsolute() . '"!');
            } else {
                return new DataQueryResultData([], 0, false, 0);
            }
        }
        
        $this->prepareFilters($query);
        $this->prepareSorters($query);
        
        $lastRow = $sheet->getHighestDataRow();
        $static_values = [];
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
                case $this->isFileProperty($address):
                    $static_values[$colKey] = $this->getFileProperty($query, $address);
                    continue 2;
                case $this->isAddressRange($address):
                    $resultRowNo = 0;
                    foreach ($this->getValuesOfRange($sheet, $address, $formatValues) as $sheetRowNo => $colVals) {
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
                    $val = $this->getValueOfCoordinate($sheet, $address, $formatValues);
                    $static_values[$colKey] = $this->parseExcelValue($val, $attrType);
                    break;
                default:
                    throw new QueryBuilderException('Invalid data address "' . $address . '" for Excel query builder!');
            }
        }
        
        // add static values
        foreach ($static_values as $alias => $val) {
            foreach (array_keys($result_rows) as $row_nr) {
                $result_rows[$row_nr][$alias] = $val;
            }
        }
        
        // Free up memory as PHPSreadsheet is known to consume a lot of it
        unset($sheet);
        unset($spreadsheet);
        unset($reader);
        
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
     * Parses an Excel-value into the internal format for the given metamodel data type
     * 
     * @param mixed|NULL $value
     * @param DataTypeInterface $dataType
     * @param bool $nullOnError
     * @throws \Throwable
     * 
     * @return mixed|NULL
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
                    $parsed = $dataType::formatDateNormalized(Date::excelToDateTimeObject($value));
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
     * @param Worksheet $sheet
     * @param string $coordinate
     * @param bool $formatValues
     * @return string|mixed|number|string|boolean|NULL|\PhpOffice\PhpSpreadsheet\RichText\RichText
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
     * @param string $range
     * @return array
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
        return $sheet->rangeToArray(
            $range,             // The worksheet range that we want to retrieve
            null,               // Value that should be returned for empty cells
            true,               // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
            $formatValues,      // Should values be formatted (the equivalent of getFormattedValue() for each cell)
            true                // Should the array be indexed by cell row and cell column
        );
    }
    
    /**
     * 
     * @param Worksheet $sheet
     * @param string $pRange
     * @param mixed $nullValue
     * @param boolean $calculateFormulas
     * @param boolean $formatData
     * @param boolean $returnCellRef
     * 
     * @return array|string
     */
    protected function rangeToArrayUnmerged(Worksheet $sheet, $pRange, $nullValue = null, $calculateFormulas = true, $formatData = true, $returnCellRef = false)
    {
        // Returnvalue
        $returnValue = [];
        //    Identify the range that we need to extract from the worksheet
        [$rangeStart, $rangeEnd] = Coordinate::rangeBoundaries($pRange);
        $minCol = Coordinate::stringFromColumnIndex($rangeStart[0]);
        $minRow = $rangeStart[1];
        $maxCol = Coordinate::stringFromColumnIndex($rangeEnd[0]);
        $maxRow = $rangeEnd[1];
        $cellCollection = $sheet->getCellCollection();
        $parent = $sheet->getParent();
        
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
                    if ($cell->getValue() !== null) {
                        if ($cell->getValue() instanceof RichText) {
                            $returnValue[$rRef][$cRef] = $cell->getValue()->getPlainText();
                        } else {
                            if ($calculateFormulas) {
                                $returnValue[$rRef][$cRef] = $cell->getCalculatedValue();
                            } else {
                                $returnValue[$rRef][$cRef] = $cell->getValue();
                            }
                        }
                        
                        if ($formatData) {
                            $style = $parent->getCellXfByIndex($cell->getXfIndex());
                            $returnValue[$rRef][$cRef] = NumberFormat::toFormattedString(
                                $returnValue[$rRef][$cRef],
                                ($style && $style->getNumberFormat()) ? $style->getNumberFormat()->getFormatCode() : NumberFormat::FORMAT_GENERAL
                                );
                        }
                    } else {
                        // Cell holds a NULL
                        $returnValue[$rRef][$cRef] = $nullValue;
                    }
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
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::prepareFilters()
     */
    protected function prepareFilters(FileContentsDataQuery $query) : bool
    {
        foreach ($this->getFilters()->getFilters() as $qpart) {
            switch (true) {
                case strcasecmp($qpart->getDataAddress(), self::ATTR_ADDRESS_FOLDER) === 0:
                case strcasecmp($qpart->getDataAddress(), self::ATTR_ADDRESS_CONTENTS) === 0:
                case strcasecmp($qpart->getDataAddress(), self::ATTR_ADDRESS_EXTENSION) === 0:
                case strcasecmp($qpart->getDataAddress(), self::ATTR_ADDRESS_FILENAME_WITHOUT_EXTENSION) === 0:
                case strcasecmp($qpart->getDataAddress(), self::ATTR_ADDRESS_FILENAME) === 0:
                    if (! $this->getAttribute($qpart->getAlias())) {
                        // FIXME What to do with filters over missing file values??? Error? Ignore? Auto-add to read?
                        throw new QueryBuilderException('Cannot filter "' . $this->getMainObject() . '" over "' . $qpart->getAlias(). '" - no correspoinding column is being read!');
                    }
                default:
                    $qpart->setApplyAfterReading(true);
            }
        }
        
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::prepareSorters()
     */
    protected function prepareSorters(FileContentsDataQuery $query) : bool
    {
        foreach ($this->getSorters() as $qpart) {
            $qpart->setApplyAfterReading(true);
        }
        
        return true;
    }
}