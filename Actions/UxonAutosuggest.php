<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Uxon\WidgetSchema;
use exface\Core\Uxon\ActionSchema;
use exface\Core\Uxon\DatatypeSchema;
use exface\Core\Uxon\BehaviorSchema;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Returns autosuggest values for provided UXON objects.
 * 
 * Accepts the following parameters:
 * 
 * - `uxon` - the entire UXON being edited
 * - `text` - the text, that needs to get suggested (i.e. what was typed so far)
 * - `path` - the path to the currently edited node from the root of the UXON (a JSON array of node names)
 * - `input` - the type of input: `field` or `value`
 * - `schema` - the UXON schema to be used (if not set, the generic UxonSchema will be used).
 * - `prototype` - class or file path (relative to the vendor folder) to be used as prototype
 * - `object` - a selector for the root object of the UXON
 * 
 * The result is a JSON with the following structure
 * 
 * {
 *  "values": [
 *      "suggestion 1",
 *      "suggestion 2",
 *      "..."
 *  ],
 *  "templates": {
 *      "field1": {"": ""},
 *      "field2": ["": ""]
 *  }
 * }
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonAutosuggest extends AbstractAction
{
    const SCHEMA_WIDGET = 'widget';
    const SCHEMA_ACTION = 'action';
    const SCHEMA_BEHAVIOR = 'behavior';
    const SCHEMA_DATATYPE = 'datatype';
    
    const PARAM_TEXT = 'text';
    const PARAM_PATH = 'path';
    const PARAM_TYPE = 'input';
    const PARAM_UXON = 'uxon';
    const PARAM_SCHEMA = 'schema';
    const PARAM_OBJECT = 'object';
    const PARAM_PROTOTYPE = 'prototype';
    
    const TYPE_FIELD = 'field';
    const TYPE_VALUE = 'value';
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $options = [];
        
        $currentText = $task->getParameter(self::PARAM_TEXT);
        $path = json_decode($task->getParameter(self::PARAM_PATH), true);
        $type = $task->getParameter(self::PARAM_TYPE);
        $uxon = UxonObject::fromJson($task->getParameter(self::PARAM_UXON));
        $schema = $task->getParameter(self::PARAM_SCHEMA);
        
        if ($rootObjectSelector = $task->getParameter(self::PARAM_OBJECT)) {
            try {
                $rootObject = $this->getWorkbench()->model()->getObject($rootObjectSelector);
            } catch (MetaObjectNotFoundError $e) {
                $rootObject = null;  
            }
        }
        
        if ($rootPrototypeSelector = trim($task->getParameter(self::PARAM_PROTOTYPE))) {
            if (StringDataType::endsWith($rootPrototypeSelector, '.php', false)) {
                $rootPrototypeClass = str_replace("/", "\\", substr($rootPrototypeSelector, 0, -4));
                $rootPrototypeClass = "\\" . ltrim($rootPrototypeClass, "\\");
            } else {
                $rootPrototypeClass = $rootPrototypeSelector;
            }
        }
        
        switch (mb_strtolower($schema)) {
            case self::SCHEMA_WIDGET: 
                $schema = new WidgetSchema($this->getWorkbench());
                break;
            case self::SCHEMA_ACTION:
                $schema = new ActionSchema($this->getWorkbench());
                break;
            case self::SCHEMA_DATATYPE:
                $schema = new DatatypeSchema($this->getWorkbench());
                break;
            case self::SCHEMA_BEHAVIOR:
                $schema = new BehaviorSchema($this->getWorkbench());
                break;
        }
        
        if (strcasecmp($type, self::TYPE_FIELD) === 0) {
            $options = $this->suggestPropertyNames($schema, $uxon, $path, $rootPrototypeClass);
        } else {
            $options = $this->suggestPropertyValues($schema, $uxon, $path, $currentText, $rootPrototypeClass, $rootObject);
        }
        
        return ResultFactory::createJSONResult($task, $options);
    }
    
    protected function suggestPropertyNames(UxonSchema $schema, UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        if ($rootPrototypeClass === null) {
            $prototypeClass = $schema->getPrototypeClass($uxon, $path);
        } else {
            $prototypeClass = $schema->getPrototypeClass($uxon, $path, $rootPrototypeClass);
        }
        return [
            'values' => $schema->getProperties($prototypeClass),
            'templates' => $schema->getPropertiesTemplates($prototypeClass)
        ];
    }
    
    protected function suggestPropertyValues(UxonSchema $schema, UxonObject $uxon, array $path, string $valueText, string $rootPrototypeClass = null, MetaObjectInterface $rootObject = null) : array
    {
        return ['values' => $schema->getValidValues($uxon, $path, $valueText, $rootPrototypeClass, $rootObject)];
    }
}