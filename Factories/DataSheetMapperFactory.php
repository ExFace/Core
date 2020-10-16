<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;

abstract class DataSheetMapperFactory extends AbstractUxonFactory
{

    /**
     *
     * @param Workbench $exface            
     * @return DataSheetMapperInterface
     */
    public static function createEmpty(Workbench $workbench)
    {
        return new DataSheetMapper($workbench);
    }
    
    /**
     * 
     * @param Workbench $workbench
     * @param UxonObject $uxon
     * @param MetaObjectInterface $from_object
     * @param MetaObjectInterface $to_object
     * @return \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface
     */
    public static function createFromUxon(Workbench $workbench, UxonObject $uxon, MetaObjectInterface $from_object = null, MetaObjectInterface $to_object = null)
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
?>