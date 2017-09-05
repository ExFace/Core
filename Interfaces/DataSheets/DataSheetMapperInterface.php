<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMapperError;

/**
 * Maps data from one data sheet to another using mappers for columns, filters, sorters, etc.
 * 
 * TODO add mappers for filters, sorters and aggregators similarly to column mappers.
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataSheetMapperInterface extends iCanBeConvertedToUxon, ExfaceClassInterface
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
     * @return DataColumnMappingInterface[]
     */
    public function getColumnMappings();
    
    /**
     *
     * @param DataColumnMappingInterface[]|UxonObject[]
     * @return DataSheetMapperInterface
     */
    public function setColumnMappings(array $ColumnMappingsOrUxonObjects);
    
    /**
     *
     * @param DataColumnMappingInterface $map
     * @return DataSheetMapperInterface
     */
    public function addColumnMapping(DataColumnMappingInterface $map);
    
    /**
     * Creates all types of mappings, that can be derived from expressions: mappings for columns, filters, sorters, aggregators, etc.
     * 
     * @param UxonObject[]
     * @return DataSheetMapperInterface
     */
    public function setExpressionMappings(array $ExpressionMappingsOrUxonObjects);
   
}