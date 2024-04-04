<?php
namespace exface\Core\QueryBuilders\Traits;

use League\Csv\Reader;
use League\Csv\Statement;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * A trait for query builders handling CSV.
 * 
 * The trait provides the following data addresses:
 * 
 * - column number starting with 0 - e.g. `0` for the first column, '1' for the second, etc.
 * - `~row_number` for the current row number (starting with 0, EXCLUDING header rows)
 * 
 * This query builder is internally based on the PHP leagues CSV package:
 * https://csv.thephpleague.com/.
 *
 * @author Andrej Kabachnik
 *        
 */
trait CsvBuilderTrait
{    
    /**
     * 
     * @return string
     */
    abstract protected function getDelimiter(MetaObjectInterface $object) : string;
    
    /**
     * 
     * @return string
     */
    abstract protected function getEnclosure(MetaObjectInterface $object) : string;
    
    /**
     * 
     * @return int
     */
    abstract protected function getHeaderRowsNumber(MetaObjectInterface $object) : int;
    
    /**
     * 
     * @param DataQueryInterface $query
     * @param array $static_values
     * @param bool $readAll
     * @return DataQueryResultDataInterface
     */
    protected function readCsv(DataQueryInterface $query, array $static_values = [], bool $readAll = true) : DataQueryResultDataInterface
    {
        $headerRows = $this->getHeaderRowsNumber($this->getMainObject());
        // Prepare sorting
        foreach ($this->getSorters() as $qpart) {
            $qpart->setAlias($qpart->getDataAddress());
            $qpart->setApplyAfterReading(true);
        }
        
        // Initialize the CSV reader
        $csvReader = $this->initCsvReader($query);
        if ($csvReader === null) {
            return new DataQueryResultData([], 0, false);
        }
        
        // Create a statement for advanced record selection
        $statement = Statement::create();
        
        // Add a WHERE to the statement based on "normal" filtering
        $statement = $statement->where(function ($row) {
            return parent::applyFilters([$row]);
        });
        
        // pagination
        if ($readAll === false) {
            // Increase offset if there is a header row and another time to find out if more rows are there
            $offset = $this->getOffset() + $headerRows;
            $statement = $statement
                ->limit($this->getLimit()+1)
                ->offset($offset);
        }
        
        // sorting
        if (! empty($this->getSorters())) {
            $statement = $statement->orderBy(function($row1, $row2){
                $sorted = parent::applySorting([$row1, $row2]);
                if ($sorted[0] === $row1)
                    return -1;
                else
                    return 1;
            });
        }
        
        $result_rows = [];
        $hasMoreRows = false;
        $records = $statement->process($csvReader);
        $maxRow = $this->getLimit() > 0 ? $this->getLimit() + $this->getOffset() + $headerRows : null;
        try {
            foreach ($this->getAttributes() as $qpart) {
                $colKey = $qpart->getColumnKey();
                $dataAddr = $qpart->getDataAddress();
                $isRowNo = trim($dataAddr) === '~row_number'; 
                if (! $isRowNo && is_numeric($dataAddr) === false) {
                    continue;
                }
                $rowNr = 0;
                foreach ($records as $i => $row) {
                    if ($this->getOffset() === 0 && $i < $headerRows) {
                        continue;
                    }
                    if ($maxRow !== null) {
                        if ($rowNr >= $maxRow) {
                            $hasMoreRows = true;
                            break;
                        }
                    }
                    if ($isRowNo === true) {
                        $result_rows[$rowNr][$colKey] = $rowNr + $this->getOffset();
                    } else {
                        $result_rows[$rowNr][$colKey] = $row[$dataAddr];
                    }
                    $rowNr++;
                }
            }
        } catch (\OutOfBoundsException $e) {
            $result_rows = [];
        }
        
        // add static values
        foreach ($static_values as $alias => $val) {
            foreach (array_keys($result_rows) as $rowNr) {
                $result_rows[$rowNr][$alias] = $val;
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
    
    /**
     * Returns an instance of the PHP League CSV reader or NULL if the source is empty or does not exist
     * 
     * @param DataQueryInterface $query
     * @throws QueryBuilderException
     * @return Reader|NULL
     */
    protected function initCsvReader(DataQueryInterface $query) : ?Reader
    {
        switch (true) {
            case $query instanceof FileContentsDataQuery:
                switch (true) {
                    case $query->getFileExists() === false:
                        return null;
                    case null !== ($path = $query->getPathAbsolute()) && file_exists($path):
                        $csvReader = Reader::createFromPath($path);
                        break;
                    case null !== $contents = $query->getFileContents():
                        $csvReader = Reader::createFromString($contents);
                        break;
                    default:
                        return null;
                }
                break;
            case is_a($query, 'exface\UrlDataConnector\Psr7DataQuery'):
                $response = $query->getResponse() ? $query->getResponse()->getBody()->__toString() : null;
                if ($response === null) {
                    return null;
                }
                $csvReader = Reader::createFromString($response);
                break;
            default:
                throw new QueryBuilderException('Cannot use "' . get_class($query) . '" as query in a CsvBuilder!');
                
        }
        $csvReader->setDelimiter($this->getDelimiter($this->getMainObject()));
        $csvReader->setEnclosure($this->getEnclosure($this->getMainObject()));
        
        return $csvReader;
    }

    /**
     * 
     * @param DataQueryInterface $query
     * @return int
     */
    protected function countCsvRows(DataQueryInterface $query) : int
    {
        $csvReader = $this->initCsvReader($query);
        $rowCount = $this->countRowsFiltered($csvReader);
        $rowCount = max(0, $rowCount - $this->getHeaderRowsNumber($this->getMainObject()));
        
        return $rowCount;
    }

    /**
     * Returns the row count after filtering the CSV.
     * This has to be done on a separate CSV object. Otherwise the complete row count is returned instead of the
     * filtered count.
     *
     * @param Reader $csvReader            
     * @return int row count after filtering
     */
    private function countRowsFiltered(Reader $csvReader) : int
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