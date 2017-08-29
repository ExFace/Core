<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use League\Csv\Reader;
use SplFileObject;

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
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(AbstractDataConnector $data_connection = null)
    {
        $query = $this->buildQuery();
        if (is_null($data_connection)) {
            $data_connection = $this->getMainObject()->getDataConnection();
        }
        
        $data_connection->query($query);
        
        $static_values = array();
        $field_map = array();
        foreach ($this->getAttributes() as $qpart) {
            if ($this->getFileProperty($query, $qpart->getDataAddress()) !== false) {
                $static_values[$qpart->getAlias()] = $this->getFileProperty($query, $qpart->getDataAddress());
            } else {
                $field_map[$qpart->getAlias()] = $qpart->getDataAddress();
            }
        }
        
        // configuration
        $delimiter = $this->getMainObject()->getDataAddressProperty('DELIMITER') ? $this->getMainObject()->getDataAddressProperty('DELIMITER') : ',';
        $enclosure = $this->getMainObject()->getDataAddressProperty('ENCLOSURE') ? $this->getMainObject()->getDataAddressProperty('ENCLOSURE') : "'";
        $hasHeaderRow = $this->getMainObject()->getDataAddressProperty('HAS_HEADER_ROW') ? $this->getMainObject()->getDataAddressProperty('HAS_HEADER_ROW') : 0;
        
        // prepare filters
        foreach ($this->getFilters()->getFilters() as $qpart) {
            if ($this->getFileProperty($query, $qpart->getDataAddress()) === false) {
                $qpart->setAlias($qpart->getDataAddress()); // use numeric alias since league/csv filter on arrays with numeric indexes
                $qpart->setApplyAfterReading(true);
            } else {
                // TODO check if the filters on file properties match. Only need to check that once, as the query onle deals with a single file
            }
        }
        
        // prepare sorting
        foreach ($this->getSorters() as $qpart) {
            $qpart->setAlias($qpart->getDataAddress());
            $qpart->setApplyAfterReading(true);
        }
        
        // prepare reader
        $csv = Reader::createFromPath(new SplFileObject($query->getPathAbsolute()));
        $csv->setDelimiter($delimiter);
        $csv->setEnclosure($enclosure);
        
        // column count
        $colCount = count($csv->fetchOne());
        
        // add filter based on "normal" filtering
        $filtered = $csv;
        $filtered = $filtered->addFilter(function ($row) {
            return parent::applyFilters(array(
                $row
            ));
        });
        
        // pagination
        $offset = $hasHeaderRow ? $this->getOffset() + 1 : $this->getOffset();
        $filtered->setOffset($offset);
        $filtered->setLimit($this->getLimit());
        
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
        
        $assocKeys = $this->getAssocKeys($colCount, $field_map);
        $result_rows = $filtered->fetchAssoc($assocKeys);
        $result_rows = iterator_to_array($result_rows);
        
        // row count
        $rowCount = $this->getRowCount($query->getPathAbsolute(), $delimiter, $enclosure);
        if ($hasHeaderRow)
            $rowCount = max(0, $rowCount - 1);
        
        $this->setResultTotalRows($rowCount);
        
        // add static values
        foreach ($static_values as $alias => $val) {
            foreach (array_keys($result_rows) as $row_nr) {
                $result_rows[$row_nr][$alias] = $val;
            }
        }
        
        $this->setResultRows($result_rows);
        return $this->getResultTotalRows();
    }

    protected function getAssocKeys($colCount, $field_map)
    {
        $keys = array_flip($field_map);
        
        $assocKeys = array();
        for ($i = 0; $i < $colCount; $i ++) {
            if (isset($keys[$i]))
                $assocKeys[$keys[$i]] = $keys[$i];
            else
                $assocKeys[$i] = '- unused' . $i; // unique value, not used by query
        }
        
        return $assocKeys;
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
