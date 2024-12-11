<?php
namespace exface\Core\QueryBuilders;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\QueryBuilders\Traits\CsvBuilderTrait;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;

/**
 * A query builder to read CSV files.
 * 
 * ## Data source configuration
 * 
 * To access CSV files create a data source with this query builder and a connection with the `FileContentsConnector`.
 * 
 * ## Object data addresses
 * 
 * The meta object address is the file path - either absolute or relative to the base of the corresponding connection.
 * 
 * ## Attribute data addresses
 * 
 * - CSV column number starting with 0 - e.g. `0` for the first column, '1' for the second, etc.
 * - `~row_number` for the current row number (starting with 0, EXCLUDING header rows)
 * - File-related data addresses as available in the `FileContentsBuilder`
 *      - `~filepath`
 *      - `~filepath_relative`
 *      - `~contents`
 *
 * @author Andrej Kabachnik
 *        
 */
class CsvBuilder extends FileContentsBuilder
{    
    use CsvBuilderTrait;
    
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
        $query = $data_connection->query($query);
        
        // Compute static values (not depending on the contents of the CSV)
        $static_values = array();
        foreach ($this->getAttributes() as $qpart) {
            if ($this->isFileProperty($qpart->getDataAddress())) {
                $static_values[$qpart->getColumnKey()] = $this->getFileProperty($query, $qpart->getDataAddress());
            } 
        }
        
        // See which filters and sorters can be applied inside the reader and which must
        // be applied after reading generically
        $readAll = $this->prepareFilters($query) || $this->prepareSorters($query);
        
        return $this->readCsv($query, $static_values, $readAll);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::prepareFilters()
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
     * {@inheritDoc}
     * @see \exface\Core\QueryBuilders\FileContentsBuilder::count()
     */
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $query = $data_connection->query($this->buildQuery());
        $this->prepareFilters($query);
        $rowCount = $this->countCsvRows($query);
        return new DataQueryResultData([], $rowCount, false, $rowCount);
    }
    
    /**
     *
     * @see CsvBuilderTrait::getDelimiter()
     */
    protected function getDelimiter(MetaObjectInterface $object) : string
    {
        return $object->getDataAddressProperty(self::DAP_DELIMITER) ?? ',';
    }
    
    /**
     *
     * @see CsvBuilderTrait::getEnclosure()
     */
    protected function getEnclosure(MetaObjectInterface $object) : string
    {
        return $object->getDataAddressProperty(self::DAP_ENCLOSURE) ?? '"';
    }
    
    /**
     *
     * @see CsvBuilderTrait::getHeaderRowsNumber()
     */
    protected function getHeaderRowsNumber(MetaObjectInterface $object) : int
    {
        $hasHeader = BooleanDataType::cast($object->getDataAddressProperty(self::DAP_HAS_HEADER_ROW));
        return $hasHeader === true ? 1  : 0;
    }
}