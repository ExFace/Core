<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Uxon\UxonSchema;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Widgets\Markdown;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Factories\UxonSchemaFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

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
    const PARAM_TEXT = 'text';
    const PARAM_PATH = 'path';
    const PARAM_TYPE = 'input';
    const PARAM_UXON = 'uxon';
    const PARAM_SCHEMA = 'schema';
    const PARAM_OBJECT = 'object';
    const PARAM_PROTOTYPE = 'prototype';
    
    const TYPE_FIELD = 'field';
    const TYPE_VALUE = 'value';
    const TYPE_PRESET = 'preset';
    const TYPE_DETAILS = 'details';
    const TYPE_MODEL_BROWSER = 'modelbrowser';
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $options = [];
        
        $currentText = $task->getParameter(self::PARAM_TEXT);
        $path = json_decode($task->getParameter(self::PARAM_PATH), true);
        $type = $task->getParameter(self::PARAM_TYPE);
        $uxon = UxonObject::fromJson($task->getParameter(self::PARAM_UXON));
        $schemaName = $task->getParameter(self::PARAM_SCHEMA);
        $rootObject = null;
        
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
            } elseif (! $rootPrototypeSelector || $rootObjectSelector === 'null' || $rootObjectSelector === 'undefined') {
                $rootPrototypeClass = null;
            } else {
                $rootPrototypeClass = $rootPrototypeSelector;
            }
        }
        
        // If we know the prototype class and that class has a UXON schema, use that schema
        // instead of the one provided in the request. This is important in case the root
        // prototype alread has it's own custom schema!
        if ($rootPrototypeClass && is_subclass_of($rootPrototypeClass, iCanBeConvertedToUxon::class) && $rootPrototypeClass::getUxonSchemaClass()) {
            $schemaName = '\\' . $rootPrototypeClass::getUxonSchemaClass();
        }
        $schema = UxonSchemaFactory::create($this->getWorkbench(), $schemaName);
        
        switch (true) {
            case strcasecmp($type, self::TYPE_FIELD) === 0:
                $options = $this->suggestPropertyNames($schema, $uxon, $path, $rootPrototypeClass);
                break;
            case strcasecmp($type, self::TYPE_PRESET) === 0:
                $options = $this->suggestPresets($schema, $uxon, $path, $rootPrototypeClass);
                break;
            case strcasecmp($type, self::TYPE_DETAILS) === 0:
                $options = $this->suggestDetails($schema, $uxon, $path, $rootPrototypeClass);
                break;
            case strcasecmp($type, self::TYPE_MODEL_BROWSER) === 0:
                $options = $this->suggestModelBrowser($schema, $uxon, $path, $currentText, $rootPrototypeClass, $rootObject);
                break;
            default:
                $options = $this->suggestPropertyValues($schema, $uxon, $path, $currentText, $rootPrototypeClass, $rootObject);
                break;
        }
        return ResultFactory::createJSONResult($task, $options);
    }

    /**
     * 
     * @param UxonSchemaInterface $schema
     * @param UxonObject $uxon
     * @param array $path
     * @param string $rootPrototypeClass
     * @return array
     */
    protected function suggestPresets(UxonSchemaInterface $schema, UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        return $schema->getPresets($uxon, $path, $rootPrototypeClass);
    }
    
    protected function suggestModelBrowser(UxonSchemaInterface $schema, UxonObject $uxon, array $path, string $search, string $rootPrototypeClass = null, MetaObjectInterface $rootObject = null) : array
    {
        $type = $schema->getUxonType($uxon, $path, $rootPrototypeClass);
        
        try {
            $object = $schema->getMetaObject($uxon, $path, $rootObject);
        } catch (MetaObjectNotFoundError $e) {
            return [];
        }
        
        $data = [];
        switch (strtolower($type)) {
            case 'metamodel:attribute':
            default:
                $data = [
                    '_TYPE' => 'object',
                    '_BASE_OBJECT_NAME' => $object->getName(),
                    '_BASE_OBJECT_ALIAS_NS' => $object->getAliasWithNamespace(),
                    '_BASE_OBJECT_ALIAS' => $object->getAlias()
                ];
                foreach ($schema->getValidValues($uxon, $path, $search, $rootPrototypeClass, $rootObject) as $suggest) {
                    $alias = rtrim($suggest, "_");
                    if ($alias !== $suggest) {
                        $rel = $object->getRelation($alias);
                        $data['ATTRIBUTES'][] = [
                            'NAME' => $rel->getName(),
                            'ALIAS' => $alias,
                            '_SUGGEST' => $suggest,
                            '_RELATION' => true,
                            '_RELATION_TO' => $rel->getRightObject()->getName(),
                            'DESCRIPTION' => $rel->isForwardRelation() && $object->hasAttribute($alias) ? $object->getAttribute($alias)->getShortDescription() : ''
                        ];
                    } else {
                        $attr = $object->getAttribute($alias);
                        $data['ATTRIBUTES'][] = [
                            'NAME' => $attr->getName(),
                            'ALIAS' => $alias,
                            '_SUGGEST' => $suggest,
                            'DESCRIPTION' => $attr->getShortDescription()
                        ];
                    }
                }
                break;
        }
        
        return $data;
    }
    
    /**
     * 
     * @param UxonSchemaInterface $schema
     * @param UxonObject $uxon
     * @param array $path
     * @param string $rootPrototypeClass
     * @return array
     */
    protected function suggestDetails(UxonSchemaInterface $schema, UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        $rows = [];
        if (empty($path) === false) {
            $path[] = '';
        }
        $prototypeClass = $schema->getPrototypeClass($uxon, $path, $rootPrototypeClass);
        $prototypeSchemaClass = $prototypeClass::getUxonSchemaClass() ?? '\\' . UxonSchema::class;
        $filepathRelative = $schema->getFilenameForEntity($prototypeClass);
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_PROPERTY_ANNOTATION');
        $ds->getColumns()->addMultiple([
            'PROPERTY', 
            'TYPE', 
            'TEMPLATE', 
            'DEFAULT', 
            'TITLE', 
            'REQUIRED',
            'DESCRIPTION'
            
        ]);
        $ds->getFilters()->addConditionFromString('FILE', $filepathRelative, ComparatorDataType::EQUALS);
        $ds->getSorters()->addFromString('PROPERTY', SortingDirectionsDataType::ASC);
        
        try {
            $ds->dataRead();
        } catch (\Throwable $e) {
            // TODO
        }
        
        $rows = $ds->getRows();
        
        
        //convert markdown to html, remove <a> tags 
        foreach ($rows as &$propertyRow){
            $propertyRow['DESCRIPTION'] = $this->buildHtmlFromMarkdown($propertyRow['DESCRIPTION']);
        }
        
        // Get class annotations
        $dsClass = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_ENTITY_ANNOTATION');
        $dsClass->getColumns()->addMultiple([
            'CLASSNAME',
            'TITLE',
            'DESCRIPTION'
        ]);
        $dsClass->getFilters()->addConditionFromString('FILE', $filepathRelative);
        try {
            $dsClass->dataRead();
        } catch (\Throwable $e) {
            // TODO
        }
        $classInfo = $dsClass->getRow(0);
        $classInfo['DESCRIPTION'] = $this->buildHtmlFromMarkdown($classInfo['DESCRIPTION']);
        
        // TODO transform enum-types to arrays
        
        return [
            'alias' => $classInfo['CLASSNAME'] ? $classInfo['CLASSNAME'] : StringDataType::substringAfter($prototypeClass, '\\', '', false, true),
            'prototype' => $prototypeClass,
            'prototype_schema' => $prototypeSchemaClass::getSchemaName(),
            'properties' => $rows,
            'title' => $classInfo['TITLE'],
            'description' => $classInfo['DESCRIPTION']
        ];
    }
    
    /**
     * 
     * @param UxonSchemaInterface $schema
     * @param UxonObject $uxon
     * @param array $path
     * @param string $rootPrototypeClass
     * @return array
     */
    protected function suggestPropertyNames(UxonSchemaInterface $schema, UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        $prototypeClass = $schema->getPrototypeClass($uxon, $path, $rootPrototypeClass);
        return [
            'values' => $schema->getProperties($prototypeClass),
            'templates' => $schema->getPropertiesTemplates($prototypeClass)
        ];
    }
    
    protected function suggestPropertyValues(UxonSchemaInterface $schema, UxonObject $uxon, array $path, string $valueText, string $rootPrototypeClass = null, MetaObjectInterface $rootObject = null) : array
    {
        return ['values' => $schema->getValidValues($uxon, $path, $valueText, $rootPrototypeClass, $rootObject)];
    }
    
    
    /**
     * Converts markdown into html, removing all <a> tags. 
     * On failure it just returns the markdown string instead.
     * 
     * @param string $markdown
     * @return string
     */
    protected function buildHtmlFromMarkdown(string $markdown) : string 
    {
        try{
            $html = Markdown::convertMarkdownToHtml($markdown);
            $html = preg_replace("(</?a[^>]*\>)i", "", $html);
            return $html;
        } catch (\Throwable $e) {
            return $markdown;
        }
        
        
    }
}