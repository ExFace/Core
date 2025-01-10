<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class DataSheetMapperFactory extends AbstractUxonFactory
{

    /**
     *
     * @param WorkbenchInterface $exface            
     * @return DataSheetMapperInterface
     */
    public static function createEmpty(WorkbenchInterface $workbench) : DataSheetMapperInterface
    {
        return new DataSheetMapper($workbench);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     * @param MetaObjectInterface $from_object
     * @param MetaObjectInterface $to_object
     * @return DataSheetMapperInterface
     */
    public static function createFromUxon(WorkbenchInterface $workbench, UxonObject $uxon, MetaObjectInterface $from_object = null, MetaObjectInterface $to_object = null) : DataSheetMapperInterface
    {
        $mapper = static::createEmpty($workbench);
        
        if ($from_object !== null && ! $uxon->hasProperty('from_object_alias')){
            $mapper->setFromMetaObject($from_object);
        }
        
        if ($to_object !== null && ! $uxon->hasProperty('to_object_alias')){
            $mapper->setToMetaObject($to_object);
        }
        
        $mapper->importUxonObject($uxon);
        
        return $mapper;
    }
}