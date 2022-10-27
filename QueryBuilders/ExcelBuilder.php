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

/**
 * A query builder to access Excel files (or similar spreadsheets).
 * 
 * **WARNING:** this is still a very early version, which currently only supports reading simpledata!
 * 
 * ## Data source configuration
 * 
 * To access Excel files create a data source with this query builder and a connection with the `FileContentsConnector`.
 * 
 * ## Object data addresses
 * 
 * The meta object address should point to a worsheet within the document. It follows the excel reference format. Example:
 * 
 * - `path/from/installation/root/excel_file.xlsx` will access the default worksheet (the one, that is shown when opening
 * the file in Excel).
 * - `path/from/installation/root/excel_file.xlsx[My sheet]` will access the sheet named `My sheet`
 * 
 * ## Attribute data addresses
 * 
 * Attributes of the meta objects can be
 * 
 * - cell ranges in a single column (e.g. `A1:A999`). Be sure to include enough rows for eventual modifications in the excel 
 * file! Don't worry about empty rows - only rows with values will actually be read!
 * - cell ranges in multiple columns (e.g. `A10:B20`) - all values will be read into a single data column row-by-row from 
 * left to right.
 * - column names (e.g. `[my column]`) - not yet available!
 *
 *
 * @author Andrej Kabachnik
 *        
 */
class ExcelBuilder extends FileContentsBuilder
{    
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
        $delim = strpos($addr, '[');
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
        
        $query = $this->buildQuery();
        $data_connection->query($query);
        
        $spreadsheet = IOFactory::load($query->getPathAbsolute());
        $sheetName = $this->getSheetForObject($this->getMainObject());
        $sheet = $sheetName !== null && $sheetName !== '' ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();
        
        if (! $sheet) {
            throw new QueryBuilderException('Worksheet "' . $sheetName . '" not found in spreadsheet "' . $query->getPathAbsolute() . '"!');
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
                case $attrType instanceof DateDataType: $formatValues = true; break;
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
                            $result_rows[$resultRowNo][$colKey] = $val;
                            $resultRowNo += 1;
                        }
                    }
                    break;
                case $this->isAddressCoordinate($address):
                    $static_values[$colKey] = $this->getValueOfCoordinate($sheet, $address, $formatValues);
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
        
        $resultTotalRows = count($result_rows);
        
        $result_rows = $this->applyFilters($result_rows);
        $result_rows = $this->applySorting($result_rows);
        $result_rows = $this->applyAggregations($result_rows, $this->getAggregations());
        $result_rows = $this->applyPagination($result_rows);
        
        $cnt = count($result_rows);
        return new DataQueryResultData($result_rows, $cnt, ($resultTotalRows > $cnt+$this->getOffset()), $resultTotalRows);
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
        return $sheet->rangeToArray(
            $range,     // The worksheet range that we want to retrieve
            null,        // Value that should be returned for empty cells
            true,        // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
            $formatValues,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
            true         // Should the array be indexed by cell row and cell column
        );
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::prepareFilters()
     */
    protected function prepareFilters(FileContentsDataQuery $query) : bool
    {
        foreach ($this->getFilters()->getFilters() as $qpart) {
            $qpart->setApplyAfterReading(true);
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