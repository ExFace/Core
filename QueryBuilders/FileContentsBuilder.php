<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\Exceptions\QueryBuilderException;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\FilePathDataType;

/**
 * A query builder to the raw contents of a file.
 * 
 * This is the base for format-specific query builders like the `CsvBuilder`, `ExcelBuilder`, etc. It can also be
 * used by itself to access the raw contents of a single file.
 * 
 * ## Data source configuration
 * 
 * To access file contents create a data source with this query builder and a connection with the `FileContentsConnector`.
 * 
 * ## Object data addresses
 * 
 * The meta object address is the file path - either absolute or relative to the base of the corresponding connection.
 * 
 * ## Attribute data addresses
 * 
 * - `~filepath`
 * - `~filepath_relative`
 * - `~folder`
 * - `~filename`
 * - `~filename_without_extension`
 * - `~extension`
 * - `~contents`
 * 
 * These file-specific data addresses are also available in derived query builders.
 *
 * @author Andrej Kabachnik
 *        
 */
class FileContentsBuilder extends AbstractQueryBuilder
{
    const ATTR_ADDRESS_FILEPATH = '~filepath';
    
    const ATTR_ADDRESS_FILEPATH_RELATIVE = '~filepath_relative';
    
    const ATTR_ADDRESS_FOLDER = '~folder';
    
    const ATTR_ADDRESS_CONTENTS = '~contents';
    
    const ATTR_ADDRESS_FILENAME = '~filename';
    
    const ATTR_ADDRESS_FILENAME_WITHOUT_EXTENSION = '~filename_without_extension';
    
    const ATTR_ADDRESS_EXTENSION = '~extension';
    
    /**
     *
     * @return FileContentsDataQuery
     */
    protected function buildQuery()
    {
        $query = new FileContentsDataQuery();
        $query->setPath($this->getPathForObject($this->getMainObject()));
        return $query;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param bool $replacePlaceholders
     * @return string
     */
    protected function getPathForObject(MetaObjectInterface $object, bool $replacePlaceholders = true) : string
    {
        $path = trim($object->getDataAddress());
        
        if ($replacePlaceholders) {
            $path = $this->replacePlaceholdersByFilterValues($path);
        }
        
        return $path ?? '';
    }

    /**
     * 
     * @param FileContentsDataQuery $query
     * @param string $data_address
     * @return mixed
     */
    protected function getFileProperty(FileContentsDataQuery $query, $data_address)
    {
        $prop = mb_strtolower(trim($data_address));
        if (substr($prop, 0, 1) === '_') {
            $prop = '~' . substr($prop, 1);
        }
        switch ($prop) {
            case self::ATTR_ADDRESS_FILEPATH:
                return $query->getPathAbsolute();
            case self::ATTR_ADDRESS_FILEPATH_RELATIVE:
                return $query->getPathRelative();
            case self::ATTR_ADDRESS_FOLDER:
                return FilePathDataType::findFileName($query->getFileInfo()->getPath());
            case self::ATTR_ADDRESS_CONTENTS:
                return $query->getFileContents();
            case self::ATTR_ADDRESS_FILENAME:
                return $query->getFileInfo()->getFilename();
            case self::ATTR_ADDRESS_FILENAME_WITHOUT_EXTENSION:
                return FilePathDataType::findFileName($query->getFileInfo()->getFilename());
            case self::ATTR_ADDRESS_EXTENSION:
                return $query->getFileInfo()->getExtension();
            default:
                throw new QueryBuilderException('Unknown file property data address "' . $data_address . '"!');
        }
    }
    
    /**
     * 
     * @param string $dataAddress
     * @return bool
     */
    protected function isFileProperty(string $dataAddress) : bool
    {
        $prop = mb_strtoupper(trim($dataAddress));
        $begin = substr($prop, 0, 1);
        if ($begin === '_' || $begin === '~') {
            if (defined(__CLASS__ . '::ATTR_ADDRESS_' . substr($prop, 1))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $result_rows = array();
        $query = $this->buildQuery();
        
        $query = $data_connection->query($query);
        
        foreach ($this->getAttributes() as $qpart) {
            if ($this->isFileProperty($qpart->getDataAddress())) {
                $result_rows[$qpart->getColumnKey()] = $this->getFileProperty($query, $qpart->getDataAddress());
            } elseif ($qpart->getDataAddress()) {
                throw new QueryBuilderException('Unknown data address "' . $qpart->getDataAddress() . '"!');
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
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::count()
     */
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->read($data_connection);
    }
    
    /**
     * The FileContentsBuilder can only handle attributes of one object - no relations (JOINs) supported!
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        return $attribute->getRelationPath()->isEmpty();
    }
    
    /**
     * Returns TRUE if filtering needs to be done after reading and thus all data needs to be read.
     *
     * @param FileContentsDataQuery $query
     * @return bool
     */
    protected function prepareFilters(FileContentsDataQuery $query) : bool
    {
        return false;
    }
    
    /**
     * Returns TRUE if sorting needs to be done after reading and thus all data needs to be read.
     *
     * @param FileContentsDataQuery $query
     * @return bool
     */
    protected function prepareSorters(FileContentsDataQuery $query) : bool
    {
        return false;
    }
}