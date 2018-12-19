<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\UxonWidgetSchema;

class UxonAutosuggest extends AbstractAction
{
    const SCHEMA_WIDGET = 'WIDGET';
    const SCHEMA_ACTION = 'ACTION';
    
    const TYPE_FIELD = 'FIELD';
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $options = [];
        
        $currentText = $task->getParameter('text');
        $path = json_decode($task->getParameter('path'), true);
        $type = $task->getParameter('input');
        $uxon = UxonObject::fromJson($task->getParameter('uxon'));
        $schema = $task->getParameter('schema');
        
        switch (mb_strtoupper($schema)) {
            case self::SCHEMA_WIDGET: 
                if (strcasecmp($type, self::TYPE_FIELD) === 0) {
                    $options = $this->getWidgetProperties($uxon, $path);
                }
                break;
        }
        
        return ResultFactory::createJSONResult($task, $options);
    }
    
    protected function getWidgetProperties(UxonObject $uxon, array $path) : array
    {
        $schema = new UxonWidgetSchema($this->getWorkbench());
        $entityClass = $schema->getEntityClass($uxon, $path);
        
        return $schema->getProperties($entityClass);
    }
}