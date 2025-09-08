<?php

namespace exface\Core\Actions\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Factories\UxonSchemaFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\UxonSchemaInterface;

/**
 * This trait contains basic utilities for processing tasks that contain a UXON.
 * Use it to ensure uniform parameter names and processing logic.
 */
trait iProcessUxonTasksTrait
{
    protected function getParamUxon(TaskInterface $task) : UxonObject
    {
        return UxonObject::fromJson($task->getParameter('uxon'));
    }
    
    protected function getParamPath(TaskInterface $task) : mixed
    {
        return json_decode($task->getParameter('path'), true);
    }
    
    protected function getParamRootObject(TaskInterface $task) : ?string
    {
        return $task->getParameter('object');
    }

    protected function getParamRootPrototype(TaskInterface $task) : ?string
    {
        return $task->getParameter('prototype');
    }
    
    protected function getParamSchemaName(TaskInterface $task) : ?string
    {
        return $task->getParameter('schema');
    }

    protected function getRootObject(TaskInterface $task) : ?MetaObjectInterface
    {
        $rootObject = null;
        
        if ($rootObjectSelector = $this->getParamRootObject($task)) {
            try {
                $rootObject = $this->getWorkbench()->model()->getObject($rootObjectSelector);
            } catch (MetaObjectNotFoundError $e) {
                
            }
        }

        return $rootObject;
    }
    
    protected function getRootPrototypeClass(TaskInterface $task) : ?string
    {
        $rootObjectSelector = $this->getParamRootObject($task);
        $rootPrototypeClass =  null;
        
        if ($rootPrototypeSelector = trim($this->getParamRootPrototype($task))) {
            if (StringDataType::endsWith($rootPrototypeSelector, '.php', false)) {
                $rootPrototypeClass = str_replace("/", "\\", substr($rootPrototypeSelector, 0, -4));
                $rootPrototypeClass = "\\" . ltrim($rootPrototypeClass, "\\");
            } elseif (! $rootPrototypeSelector || $rootObjectSelector === 'null' || $rootObjectSelector === 'undefined') {
                $rootPrototypeClass = null;
            } else {
                $rootPrototypeClass = $rootPrototypeSelector;
            }
        }
        
        return $rootPrototypeClass;
    }
    
    protected function getSchema(TaskInterface $task, ?string $rootPrototypeClass) : UxonSchemaInterface
    {
        // If we know the prototype class and that class has a UXON schema, use that schema
        // instead of the one provided in the request. This is important in case the root
        // prototype already has its own custom schema!
        if ($rootPrototypeClass && 
            is_subclass_of($rootPrototypeClass, iCanBeConvertedToUxon::class) && 
            $rootPrototypeClass::getUxonSchemaClass()) {
            $schemaName = '\\' . $rootPrototypeClass::getUxonSchemaClass();
        } else {
            $schemaName = $this->getParamSchemaName($task);
        }
        
        return UxonSchemaFactory::create($this->getWorkbench(), $schemaName);
    }
}