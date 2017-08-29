<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;

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
}
?>