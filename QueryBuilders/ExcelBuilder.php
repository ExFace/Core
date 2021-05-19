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
        
        $lastRow = $sheet->getHighestDataRow();
        $static_values = [];
        foreach ($this->getAttributes() as $qpart) {
            if ($this->isFileProperty($qpart->getDataAddress())) {
                $static_values[$qpart->getColumnKey()] = $this->getFileProperty($query, $qpart->getDataAddress());
                continue;
            } 
            $colKey = $qpart->getColumnKey();
            $address = $qpart->getDataAddress();
            $attrType = $qpart->getDataType();
            switch (true) {
                case $attrType instanceof DateDataType: $formatValues = true; break;
                default: $formatValues = false;
            }
            switch (true) {
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
            }
        }
        
        // add static values
        foreach ($static_values as $alias => $val) {
            foreach (array_keys($result_rows) as $row_nr) {
                $result_rows[$row_nr][$alias] = $val;
            }
        }
        
        $resultTotalRows = count($result_rows);
        
        $this->applyFilters($result_rows);
        $this->applySorting($result_rows);
        $this->applyAggregations($result_rows, $this->getAggregations());
        $this->applyPagination($result_rows);
        
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
        switch (true) {
            case (count(explode(':', $dataAddress)) === 2): return true;
        }
        return false;
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
}