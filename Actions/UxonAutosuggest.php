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

class UxonAutosuggest extends AbstractAction
{
    const SCHEMA_WIDGET = 'widget';
    const SCHEMA_ACTION = 'action';
    const SCHEMA_BEHAVIOR = 'behavior';
    const SCHEMA_DATATYPE = 'datatype';
    
    const TYPE_FIELD = 'field';
    const TYPE_VALUE = 'value';
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $options = [];
        
        $currentText = $task->getParameter('text');
        $path = json_decode($task->getParameter('path'), true);
        $type = $task->getParameter('input');
        $uxon = UxonObject::fromJson($task->getParameter('uxon'));
        $schema = $task->getParameter('schema');
        
        if ($rootObjectSelector = $task->getParameter('object')) {
            try {
                $rootObject = $this->getWorkbench()->model()->getObject($rootObjectSelector);
            } catch (MetaObjectNotFoundError $e) {
                $rootObject = null;  
            }
        }
        
        if ($rootPrototypeSelector = trim($task->getParameter('prototype'))) {
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