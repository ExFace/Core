<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\DataSheets\DataMapperConfigurationError;
use exface\Core\Interfaces\Debug\LogBookInterface;

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
     * @param bool $readMissingColumns
     * @param LogBookInterface $logbook
     * 
     * @return DataSheetInterface
     */
    public function map(DataSheetInterface $fromSheet, bool $readMissingColumns = null, LogBookInterface $logbook = null) : DataSheetInterface;
    
    /**
     *
     * @throws DataMapperConfigurationError if no from-object set
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
     * @param string|bool $value
     * @throws DataMapperConfigurationError
     * @return DataSheetMapperInterface
     */
    public function setInheritColumns($value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param string|bool $value
     * @throws DataMapperConfigurationError
     * @return DataSheetMapperInterface
     */
    public function setInheritFilters($value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param string|bool $value
     * @throws DataMapperConfigurationError
     * @return DataSheetMapperInterface
     */
    public function setInheritSorters($value) : DataSheetMapperInterface;
    
    /**
     * Set to TRUE to force the to-sheet to be empty if the from-sheet is empty
     * 
     * By default the to-sheet might still get new rows: e.g. if there are column-to-column mappings with
     * formulas. Setting `inherit_empty_data` to `true` will make sure, no new rows are created if the
     * from-sheet is empty. In this case, all changes to the data sheet structure (added columns, filters, etc.)
     * will still be applied - there will only be no rows if the from-sheet had none.
     * 
     * @param bool $value
     * @return DataSheetMapperInterface
     */
    public function setInheritEmptyData(bool $value) : DataSheetMapperInterface;
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return DataSheetMapperInterface
     */
    public function setRefreshDataAfterMapping(bool $trueOrFalse) : DataSheetMapperInterface;
    
    /**
     *
     * @return DataCheckInterface[]
     */
    public function getFromDataChecks() : array;
}