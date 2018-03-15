<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware\Traits;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
Trait DataEnricherTrait
{    
    protected function getDataSheet(TaskInterface $task, string $getterMethod) : DataSheetInterface
    {
        try {
            $data_sheet = call_user_func([$task, $getterMethod]);
        } catch (\Throwable $e) {
            // TODO
            throw $e;
        }
        
        if (! ($data_sheet instanceof DataSheetInterface)) {
            throw new \LogicException('Invalid return type of method ' . get_class($task) . '::' . $getterMethod . ' - DataSheetInterface expected!');
        }
        
        return $data_sheet;
    }
}