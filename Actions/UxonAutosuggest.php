<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\UxonWidgetSchema;
use exface\Core\CommonLogic\UxonSchema;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\CommonLogic\Model\RelationPath;

class UxonAutosuggest extends AbstractAction
{
    const SCHEMA_WIDGET = 'widget';
    const SCHEMA_ACTION = 'widget';
    
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
        
        switch (mb_strtolower($schema)) {
            case self::SCHEMA_WIDGET: 
                $schema = new UxonWidgetSchema($this->getWorkbench());
                if (strcasecmp($type, self::TYPE_FIELD) === 0) {
                    $options = $this->suggestPropertyNames($schema, $uxon, $path);
                } else {
                    $options = $this->suggestPropertyValues($schema, $uxon, $path, $currentText);
                }
                break;
        }
        
        return ResultFactory::createJSONResult($task, $options);
    }
    
    protected function suggestPropertyNames(UxonSchema $schema, UxonObject $uxon, array $path) : array
    {
        $entityClass = $schema->getEntityClass($uxon, $path);
        return $schema->getProperties($entityClass);
    }
    
    protected function suggestPropertyValues(UxonSchema $schema, UxonObject $uxon, array $path, string $valueText) : array
    {
        $options = [];
        $entityClass = $schema->getEntityClass($uxon, $path);
        $prop = end($path);
        
        switch (mb_strtolower($prop)) {
            case 'object_alias':
                $options = $this->getObjectAliases($valueText);
                break;
            case 'attribute_alias': 
                $objectAlias = $schema->getPropertyValueRecursive($uxon, $path, 'object_alias');
                $options = $this->getAttributeAliases($objectAlias, $valueText);
                break;
        }
        
        return $options;
    }
    
    protected function getObjectAliases(string $search) : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.OBJECT');
        $ds->getColumns()->addMultiple(['ALIAS', 'APP__ALIAS']);
        $parts = explode('.', $search);
        if (count($parts) === 1) {
            return [];
        } else {
            $alias = $parts[2];
            $ds->addFilterFromString('APP__ALIAS', $parts[0] . '.' . $parts[1]);
        }
        $ds->addFilterFromString('ALIAS', $alias);
        $ds->dataRead();
        
        $options = [];
        foreach ($ds->getRows() as $row) {
            $options[] = $row['APP__ALIAS'] . '.' . $row['ALIAS'];
        }
        return $options;
    }
    
    protected function getAttributeAliases(string $objectAlias, string $search) : array
    {
        if ($objectAlias === '') {
            return [];
        }
        
        $object = $this->getWorkbench()->model()->getObject($objectAlias);
        
        $rels = RelationPath::relationPathParse($search);
        $search = array_pop($rels);
        if (! empty($rels)) {
            $relPath = implode(RelationPath::RELATION_SEPARATOR, $rels);
            $object = $object->getRelatedObject($relPath);
        }
        
        $options = [];
        foreach ($object->getAttributes() as $attr) {
            $options[] = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $attr->getAlias();
        }
        
        return $options;
    }
}