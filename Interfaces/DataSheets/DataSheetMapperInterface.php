<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;
use exface\Core\CommonLogic\UxonObject;

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
    public function map(DataSheetInterface $fromSheet);
    
    /**
     *
     * @throws DataSheetMapperError if no from-object set
     * 
     * @return MetaObjectInterface
     */
    public function getFromMetaObject();
    
    /**
     * @param MetaObjectInterface $object
     * @return DataSheetMapperInterface
     */
    public function setFromMetaObject(MetaObjectInterface $object);
    
    /**
     *
     * @param string $alias_with_namespace
     * @return DataSheetMapperInterface
     */
    public function setFromObjectAlias($alias_with_namespace);
    
    /**
     * @return MetaObjectInterface
     */
    public function getToMetaObject();
    
    /**
     * @param MetaObjectInterface $toMetaObject
     */
    public function setToMetaObject(MetaObjectInterface $toMetaObject);
    
    /**
     * @return DataMappingInterface[]
     */
    public function getMappings();
    
    /**
     * @return DataColumnMappingInterface[]
     */
    public function getColumnToColumnMappings();
    
    /**
     *
     * @param UxonObject
     * @return DataSheetMapperInterface
     */
    public function setColumnToColumnMappings(UxonObject $uxon);
    
    /**
     *
     * @param DataColumnMappingInterface $map
     * @return DataSheetMapperInterface
     */
    public function addColumnToColumnMapping(DataColumnMappingInterface $map);
    
    /**
     * @return DataColumnToFilterMappingInterface[]
     */
    public function getColumnToFilterMappings();
    
    /**
     *
     * @param UxonObject
     * @return DataSheetMapperInterface
     */
    public function setColumnToFilterMappings(UxonObject $uxon);
    
    /**
     *
     * @param DataColumnMappingInterface $map
     * @return DataSheetMapperInterface
     */
    public function addColumnToFilterMapping(DataColumnToFilterMappingInterface $map);
    
    /**
     * @return DataFilterToColumnMappingInterface[]
     */
    public function getFilterToColumnMappings();
    
    /**
     *
     * @param UxonObject
     * @return DataSheetMapperInterface
     */
    public function setFilterToColumnMappings(UxonObject $uxon);
    
    /**
     *
     * @param DataColumnMappingInterface $map
     * @return DataSheetMapperInterface
     */
    public function addFilterToColumnMapping(DataFilterToColumnMappingInterface $map);
    
    /**
     * Creates all types of mappings, that can be derived from expressions: mappings for columns, filters, sorters, aggregators, etc.
     * 
     * @param UxonObject
     * @return DataSheetMapperInterface
     */
    public function setExpressionMappings(UxonObject $uxon);
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return DataSheetMapperInterface
     */
    public function setRefreshDataAfterMapping(bool $trueOrFalse) : DataSheetMapperInterface;
    
    /**
     * 
     * @param bool $value
     * @return DataSheetMapperInterface
     */
    public function setInheritColumns(bool $value) : DataSheetMapperInterface;

    /**
     * 
     * @param bool $value
     * @return DataSheetMapperInterface
     */
    public function setInheritFilters(bool $value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param bool $value
     * @return DataSheetMapperInterface
     */
    public function setInheritSorters(bool $value) : DataSheetMapperInterface;
   
}