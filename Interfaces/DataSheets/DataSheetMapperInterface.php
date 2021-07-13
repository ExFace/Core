<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;

/**
 * Maps data from one data sheet to another using mappers for columns, filters, sorters, etc.
 * 
 * TODO add mappers aggregators similarly to column, filter and sorter mappers.
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataSheetMapperInterface extends iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    /**
     * 
     * @param DataSheetInterface $fromSheet
     * @return DataSheetInterface
     */
    public function map(DataSheetInterface $fromSheet) : DataSheetInterface;
    
    /**
     *
     * @throws DataSheetMapperError if no from-object set
     * 
     * @return MetaObjectInterface
     */
    public function getFromMetaObject() : MetaObjectInterface;
    
    /**
     * @param MetaObjectInterface $object
     * @return DataSheetMapperInterface
     */
    public function setFromMetaObject(MetaObjectInterface $object) : DataSheetMapperInterface;
    
    /**
     *
     * @param string $alias_with_namespace
     * @return DataSheetMapperInterface
     */
    public function setFromObjectAlias(string $alias_with_namespace) : DataSheetMapperInterface;
    
    /**
     * @return MetaObjectInterface
     */
    public function getToMetaObject() : MetaObjectInterface;
    
    /**
     * @param MetaObjectInterface $toMetaObject
     */
    public function setToMetaObject(MetaObjectInterface $toMetaObject) : DataSheetMapperInterface;
    
    /**
     * 
     * @param string $alias_with_namespace
     * @return DataSheetMapperInterface
     */
    public function setToObjectAlias(string $alias_with_namespace) : DataSheetMapperInterface;
    
    /**
     * @return DataMappingInterface[]
     */
    public function getMappings() : array;
    
    /**
     * 
     * @param DataMappingInterface $map
     * @return DataSheetMapperInterface
     */
    public function addMapping(DataMappingInterface $map) : DataSheetMapperInterface;  
    
    /**
     * 
     * @param bool $value
     * @throws DataSheetMapperError
     * @return DataSheetMapperInterface
     */
    public function setInheritColumns(bool $value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param bool $value
     * @return DataSheetMapperInterface
     */
    public function setInheritColumnsOnlyForSystemAttributes(bool $value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param bool $value
     * @throws DataSheetMapperError
     * @return DataSheetMapperInterface
     */
    public function setInheritFilters(bool $value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param bool $value
     * @throws DataSheetMapperError
     * @return DataSheetMapperInterface
     */
    public function setInheritSorters(bool $value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return DataSheetMapperInterface
     */
    public function setRefreshDataAfterMapping(bool $trueOrFalse) : DataSheetMapperInterface;
}