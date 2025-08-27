<?php

namespace exface\Core\Actions;

use exface\Core\Actions\Traits\iProcessUxonTasksTrait;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\UxonDataType;
use exface\Core\Exceptions\DataTypes\UxonValidationError;
use exface\Core\Factories\DataTypeFactory;
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

        $uxon = $this->getParamUxon($task);
        $rootObject = $this->getRootObject($task);

        if($rootObject) {
            $schemaBase->setProperty('object_alias', $rootObject->getAliasWithNamespace());
        }

        $rootPrototypeClass = $this->getRootPrototypeClass($task);

        if (! $schemaBase->isEmpty()) {
            $uxon = $schemaBase->extend($uxon);
        }

        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), UxonDataType::class);
        $dataType->setSchema($this->getParamSchemaName($task));
        $errors = $dataType->validate($uxon, $rootPrototypeClass);
        
        return  ResultFactory::createJSONResult($task, $this->toEditorErrors($errors));
    }

    /**
     * @param UxonValidationError[] $errors
     * @return array
     */
    protected function toEditorErrors(array $errors) : array
    {
        $result = [];
        
        foreach ($errors as $error) {
            $result[] = [
                'path' => $error->getPath(),
                'message' => $error->getMessage()
            ];
        }
        
        return $result;
    }
}