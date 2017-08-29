<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Object;

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
    
    public static function createFromUxon(Workbench $workbench, UxonObject $uxon, Object $from_object = null, Object $to_object = null)
    {
        $mapper = static::createEmpty($workbench);
        
        if (!is_null($from_object)){
            $mapper->setFromMetaObject($from_object);
        }
        
        if (!is_null($to_object)){
            $mapper->setToMetaObject($to_object);
        }
        
        $mapper->importUxonObject($uxon);
        
        return $mapper;
    }
}
?>