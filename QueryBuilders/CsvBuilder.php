<?php
namespace exface\Core\QueryBuilders;

use League\Csv\Reader;
use SplFileObject;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;

/**
 * A query builder to read CSV files.
 *
 * Supported data address properties
 * - DELIMITER - defaults to comma (,)
 * - ENCLOSURE - defaults to double quotes (")
 * - HAS_HEADER_ROW - specifies if the file has a header row with coulumn titles or not. Defaults to no (0)
 *
 * @author Andrej Kabachnik
 *        
 */
class CsvBuilder extends FileContentsBuilder
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $query = $this->buildQuery();        
        $data_connection->query($query);
        
        $static_values = array();
        foreach ($this->getAttributes() as $qpart) {
            if ($this->getFileProperty($query, $qpart->getDataAddress()) !== false) {
                $static_values[$qpart->getColumnKey()] = $this->getFileProperty($query, $qpart->getDataAddress());
            } 
        }
        
        // configuration
        $delimiter = $this->getDelimiter();
        $enclosure = $this->getEnclosure();
        $hasHeaderRow = $this->hasHeaderRow();
        
        // prepare filters
        $readerFiltering = $this->prepareFilters($query);
        
        // prepare sorting
        foreach ($this->getSorters() as $qpart) {
            $qpart->setAlias($qpart->getDataAddress());
            $qpart->setApplyAfterReading(true);
        }
        
        // prepare reader
        $csv = Reader::createFromPath(new SplFileObject($query->getPathAbsolute()));
        $csv->setDelimiter($delimiter);
        $csv->setEnclosure($enclosure);
        
        // add filter based on "normal" filtering
        $filtered = $csv;
        $filtered = $filtered->addFilter(function ($row) {
            return parent::applyFilters(array(
                $row
            ));
        });
        
        // pagination
        if ($readerFiltering === false) {
            // Increase offset if there is a header row and another time to find out if more rows are there
            $offset = ($hasHeaderRow === true ? $this->getOffset() + 1 : $this->getOffset());
            $filtered->setOffset($offset);
            $filtered->setLimit($this->getLimit()+1);
        }
        
        // sorting
        $filtered->addSortBy(function ($row1, $row2) {
            $sorted = parent::applySorting(array(
                $row1,
                $row2
            ));
            if ($sorted[0] === $row1)
                return -1;
            else
                return 1;
        });
        
        $resultIterator = $filtered->fetch();
        $result_rows = [];
        $hasMoreRows = false;
        try {
            $result_rows_test = iterator_to_array($resultIterator);
            foreach ($this->getAttributes() as $qpart) {
                $colKey = $qpart->getColumnKey();
                $rowKey = $qpart->getDataAddress();
                if (is_numeric($rowKey) === false) {
                    continue;
                }
                $rowNr = 0;
                $maxRow = $this->getLimit() > 0 ? $this->getLimit() + $this->getOffset() : null;
                foreach ($resultIterator as $row) {
                    if ($maxRow !== null) {
                        if ($rowNr >= $maxRow) {
                            $hasMoreRows = true;
                            break;
                        }
                    }
                    $result_rows[$rowNr][$colKey] = $row[$rowKey];
                    $rowNr++;
                }
                $resultIterator->rewind();
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
        
        return new DataQueryResultData($result_rows, $affectedRowCount, $hasMoreRows);
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
            if ($this->getFileProperty($query, $qpart->getDataAddress()) === false) {
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
        return $this->getMainObject()->getDataAddressProperty('DELIMITER') ? $this->getMainObject()->getDataAddressProperty('DELIMITER') : ',';
    }
    
    /**
     * 
     * @return string
     */
    protected function getEnclosure() : string
    {
        return $this->getMainObject()->getDataAddressProperty('ENCLOSURE') ? $this->getMainObject()->getDataAddressProperty('ENCLOSURE') : "'";
    }
    
    protected function hasHeaderRow() : bool
    {
        if ($this->getMainObject()->getDataAddressProperty('HAS_HEADER_ROW') === null) {
            return false;
        } else {
            return BooleanDataType::cast($this->getMainObject()->getDataAddressProperty('HAS_HEADER_ROW'));
        }
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
        $rowCount = $this->getRowCount($query->getPathAbsolute(), $this->getDelimiter(), $this->getEnclosure());
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
     * @param string $path
     *            path to CSV file
     * @param string $delimiter
     *            delimiter character
     * @param string $enclosure
     *            enclosure character
     *            
     * @return int row count after filtering
     */
    private function getRowCount($path, $delimiter, $enclosure)
    {
        $csv = Reader::createFromPath(new SplFileObject($path));
        $csv->setDelimiter($delimiter);
        $csv->setEnclosure($enclosure);
        
        // add filter based on "normal" filtering
        $filtered = $csv;
        $filtered = $filtered->addFilter(function ($row) {
            return parent::applyFilters(array(
                $row
            ));
        });
        
        return $filtered->each(function ($row) {
            return true;
        });
    }
}
?>
