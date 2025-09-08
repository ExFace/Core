<?php

namespace exface\Core\Actions;

use exface\Core\Actions\Traits\iProcessUxonTasksTrait;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\UxonDataType;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Exceptions\DataTypes\UxonValidationError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * This action validates a UXON given by a task parameter `uxon`, akin to UxonAutoSuggest, and produces
 * an array containing any issues it encountered as result. If no issues were detected, the array will be
 * empty.
 * 
 * This action can be toggled globally by setting `"DEBUG.AUTOMATIC_UXON_VALIDATION" = false` in "System.config.json".
 */
class UxonValidate extends AbstractAction
{
    use iProcessUxonTasksTrait;
    
    public const CFG_AUTOMATIC_UXON_VALIDATION = 'DEBUG.AUTOMATIC_UXON_VALIDATION';
    
    /**
     * @inheritDoc
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $config = $this->getWorkbench()->getConfig();
        // If automatic validation is disabled (FALSE), do nothing.
        if( !$config->hasOption(self::CFG_AUTOMATIC_UXON_VALIDATION) ||
            !$config->getOption(self::CFG_AUTOMATIC_UXON_VALIDATION)) {
            return ResultFactory::createJSONResult($task, []);
        }

        // We wrap everything in a try-catch, to ensure a minimal impact on everyday proceedings.
        try {
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
            $result = ResultFactory::createJSONResult($task, $this->toEditorErrors($errors));
        } catch (\Throwable $e)
        {
            throw new ActionRuntimeError(
                $this, 
                'Automatic UXON-Validation caused an error. Try setting "' . 
                    self::CFG_AUTOMATIC_UXON_VALIDATION . 
                    '" in "System.config.json" to "false". Original Error: ' . 
                    $e->getMessage(),
                null,
                $e
            );
        }
        
        return $result;
    }

    /**
     * Remaps a flat array of errors into an associative array that is specifically compatible with
     * `npm-asset/jsoneditor`.
     * 
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