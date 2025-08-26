<?php

namespace exface\Core\Actions;

use exface\Core\Actions\Traits\iProcessUxonTasksTrait;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

class UxonValidate extends AbstractAction
{
    use iProcessUxonTasksTrait;
    
    /**
     * @inheritDoc
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $schemaBase = new UxonObject();

        $path = $this->getParamPath($task);
        $uxon = $this->getParamUxon($task);
        $rootObject = $this->getRootObject($task);

        if($rootObject) {
            $schemaBase->setProperty('object_alias', $rootObject->getAliasWithNamespace());
        }

        $rootPrototypeClass = $this->getRootPrototypeClass($task);
        $schema = $this->getSchema($task, $rootPrototypeClass);

        if (! $schemaBase->isEmpty()) {
            $uxon = $schemaBase->extend($uxon);
        }
        
        $errors = [
            
        ];
        
        return  ResultFactory::createJSONResult($task, $errors);
    }
}