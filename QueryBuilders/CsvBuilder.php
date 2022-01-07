<?php
namespace exface\Core\QueryBuilders;

use League\Csv\Reader;
use League\Csv\Statement;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * A query builder to read CSV files.
 * 
 * This query builder is internally based on the PHP leagues CSV package:
 * https://csv.thephpleague.com/.
 *
 * @author Andrej Kabachnik
 *        
 */
class CsvBuilder extends FileContentsBuilder
{    
    /**
     * Delimiter between row values - defaults to `,` (comma)
     *
     * @uxon-property DELIMITER
     * @uxon-target object
     * @uxon-type string
     * @uxon-default ,
     */
    const DAP_DELIMITER = 'DELIMITER';
    
    /**
     * Enclosing character for strings - defaults to `"` (double quotes)
     *
     * @uxon-property ENCLOSURE
     * @uxon-target object
     * @uxon-type string
     * @uxon-default '
     */
    const DAP_ENCLOSURE = 'ENCLOSURE';
    
    /**
     * Specifies if the file has a header row with coulumn titles or not - defaults to `false` (no)
     *
     * @uxon-property HAS_HEADER_ROW
     * @uxon-target object
     * @uxon-type boolean
     * @uxon-default false
     */
    const DAP_HAS_HEADER_ROW = 'HAS_HEADER_ROW';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $query = $this->buildQuery();        
        $data_connection->query($query);
        
        // Compute static values (not depending on the contents of the CSV)
        $static_values = array();
        foreach ($this->getAttributes() as $qpart) {
            if ($this->isFileProperty($qpart->getDataAddress())) {
                $static_values[$qpart->getColumnKey()] = $this->getFileProperty($query, $qpart->getDataAddress());
            } 
        }
        
        // Prepare filters
        $statementFiltering = $this->prepareFilters($query);
        
        // Prepare sorting
        foreach ($this->getSorters() as $qpart) {
            $qpart->setAlias($qpart->getDataAddress());
            $qpart->setApplyAfterReading(true);
        }
        
        // Initialize the CSV reader
        $csvReader = $this->initCsvReader($query);
        
        // Create a statement for advanced record selection
        $statement = Statement::create();
        
        // Add a WHERE to the statement based on "normal" filtering
        $statement = $statement->where(function ($row) {
            return parent::applyFilters([$row]);
        });
        
        // pagination
        if ($statementFiltering === false) {
            // Increase offset if there is a header row and another time to find out if more rows are there
            $offset = ($this->hasHeaderRow() ? $this->getOffset() + 1 : $this->getOffset());
            $statement = $statement
                ->limit($this->getLimit()+1)
                ->offset($offset);
        }
        
        // sorting
        $statement = $statement->orderBy(function($row1, $row2){
            $sorted = parent::applySorting([$row1, $row2]);
            if ($sorted[0] === $row1)
                return -1;
            else
                return 1;
        });
        
        $result_rows = [];
        $hasMoreRows = false;
        $records = $statement->process($csvReader);
        $maxRow = $this->getLimit() > 0 ? $this->getLimit() + $this->getOffset() : null;
        try {
            foreach ($this->getAttributes() as $qpart) {
                $colKey = $qpart->getColumnKey();
                $rowKey = $qpart->getDataAddress();
                if (is_numeric($rowKey) === false) {
                    continue;
                }
                $rowNr = 0;
                foreach ($records as $row) {
                    if ($maxRow !== null) {
                        if ($rowNr >= $maxRow) {
                            $hasMoreRows = true;
                            break;
                        }
                    }
                    $result_rows[$rowNr][$colKey] = $row[$rowKey];
                    $rowNr++;
                }
            }
        } catch (\OutOfBoundsException $e) {
            $result_rows = [];
        }
        
        // add static values
        foreach ($static_values as $alias => $val) {
            foreach (array_keys($result_rows) as $row_nr) {
                $result_rows[$row_nr][$alias] = $val;
            }
        }
        
        $rowCnt = count($result_rows);
        if ($this->getLimit() > 0 && $hasMoreRows === true) {
            $affectedRowCount = $this->getLimit();
        } else {
            $affectedRowCount = $rowCnt;
        }
        
        return new DataQueryResultData($result_rows, $affectedRowCount, ($this->getOffset() > 0 || $hasMoreRows));
    }
    
    protected function initCsvReader(DataQueryInterface $query) : Reader
    {
        switch (true) {
            case $query instanceof FileContentsDataQuery:
                $splFileInfo = $query->getFileInfo();
                if ($splFileInfo === null) {
                    return new DataQueryResultData([], 0, false);
                }
                $csvReader = Reader::createFromPath($splFileInfo);
                break;
            case is_a($query, 'exface\UrlDataConnector\Psr7DataQuery'):
                $response = $query->getResponse() ? $query->getResponse()->__toString() : null;
                if ($response === null) {
                    return new DataQueryResultData([], 0, false);
                }
                $csvReader = Reader::createFromString($response);
                break;
            default:
                throw new QueryBuilderException('Cannot use "' . get_class($query) . '" as query in a CsvBuilder!');
                
        }
        $csvReader->setDelimiter($this->getDelimiter());
        $csvReader->setEnclosure($this->getEnclosure());
        
        return $csvReader;
    }
    
    /**
     * Returns TRUE if filtering is neaded in the CSV reader and FALSE otherwise.
     * 
     * @param FileContentsDataQuery $query
     * @return bool
     */
    protected function prepareFilters(FileContentsDataQuery $query) : bool
    {
        $readerFiltering = false;
        foreach ($this->getFilters()->getFilters() as $qpart) {
            if ($this->isFileProperty($qpart->getDataAddress()) === false) {
                $qpart->setAlias($qpart->getDataAddress()); // use numeric alias since league/csv filter on arrays with numeric indexes
                $qpart->setApplyAfterReading(true);
                $readerFiltering = true;
            } else {
                // TODO check if the filters on file properties match. Only need to check that once, as the query onle deals with a single file
            }
        }
        return $readerFiltering;
    }
    
    /**
     * 
     * @return string
     */
    protected function getDelimiter() : string
    {
        return $this->getMainObject()->getDataAddressProperty(self::DAP_DELIMITER) ?? ',';
    }
    
    /**
     * 
     * @return string
     */
    protected function getEnclosure() : string
    {
        return $this->getMainObject()->getDataAddressProperty(self::DAP_ENCLOSURE) ?? '"';
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasHeaderRow() : bool
    {
        $prop = $this->getMainObject()->getDataAddressProperty(self::DAP_HAS_HEADER_ROW);
        return $prop === null ? false : BooleanDataType::cast($prop);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::count()
     */
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $query = $data_connection->query($this->buildQuery());
        $this->prepareFilters($query);
        $csvReader = $this->initCsvReader($query);
        $rowCount = $this->countRowsFiltered($csvReader);
        if ($this->hasHeaderRow() === true) {
            $rowCount = max(0, $rowCount - 1);
        }
        
        return new DataQueryResultData([], $rowCount, false, $rowCount);
    }

    /**
     * Returns the row count after filtering the CSV.
     * This has to be done on a separate CSV object. Otherwise the complete row count is returned instead of the
     * filtered count.
     *
     * @param Reader $csvReader            
     * @return int row count after filtering
     */
    private function countRowsFiltered(Reader $csvReader)
    {        
        // add filter based on "normal" filtering
        $statement = Statement::create();
        $statement = $statement->where(function ($row) {
            return parent::applyFilters(array(
                $row
            ));
        });
        
        $records = $statement->process($csvReader);
        return count($records);
    }
}