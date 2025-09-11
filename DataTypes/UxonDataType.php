<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\DataTypes\UxonValidationError;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\UxonSchemaFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Uxon\JsonValidationRule;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Uxon\WidgetSchema;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * UXON specific implementation of the JSON-Datatype.
 * 
 * @see JsonDataType, DataTypeInterface
 */
class UxonDataType extends JsonDataType
{
    /**
     * Validates a given UXON-Object or array or string representing a UXON and returns
     * an array containing all issues encountered.
     * 
     * Validation is performed recursively, creating mock objects along the way. This may result
     * in false positives, but should largely be representative.
     * 
     * TODO: If this feature proves successful, future revisions will be able to apply custom
     * validation rules and have better guards against false positives.
     * 
     * @param mixed       $inputData
     * @param string|null $rootPrototypeClass
     * @return array
     */
    public function validate(mixed $inputData, string $rootPrototypeClass = null): array
    {
        if(!$inputData instanceof UxonObject) {
            $inputData = UxonObject::fromAnything($inputData);
        }

        $schema = UxonSchemaFactory::create($this->getWorkbench(), $this->getSchema());
        
        return $this->validateUxonRecursive(
            [],
            $inputData,
            $schema,
            $rootPrototypeClass
        );
    }

    /**
     * Performs a recursive validation.
     * 
     * @param array                $path
     * @param UxonObject           $uxon
     * @param UxonSchemaInterface  $schema
     * @param string|null          $prototypeClass
     * @param UiPageInterface|null $page
     * @param mixed|null           $lastValidationObject
     * @return array
     * @throws InvalidArgumentException
     */
    protected function validateUxonRecursive(
        array $path,
        UxonObject $uxon,
        UxonSchemaInterface $schema,
        string $prototypeClass = null,
        UiPageInterface $page = null,
        mixed $lastValidationObject = null
    ) : array
    {
        $errors = [];
        $prototypeClass = $prototypeClass ?? $schema->getPrototypeClass($uxon, []);

        try {
            // Validate the UXON import, by creating a mock object.
            // TODO This causes a lot of false positives, maybe there is a better way.
            // TODO Additionally, the errors are annotated to the parent, not the affected property.
            if(!$uxon->isArray(true)) {
                $validationObject = $this->createValidationObject(
                    $schema,
                    $prototypeClass,
                    $uxon,
                    $page,
                    $lastValidationObject
                ) ?? $lastValidationObject;
            }
            
            $creationError = null;
        } catch (\Throwable $exception) {
            $creationError = new UxonValidationError(
                $path, 
                'Invalid UXON.' . $exception->getMessage(), 
                $exception instanceof ExceptionInterface ? $exception->getMessage() : '',
                $exception
            );
        }

        // Check validation rules.
        foreach ($this->getValidationRules($prototypeClass) as $rule) {
            try {
                $rule->check($uxon);
            } catch (DataTypeValidationError $error) {
                $errors[] = new UxonValidationError(
                    $path,
                    $error->getMessage(),
                    $error->getAlias(),
                    $error
                );
            }
        }

        // Check nested UXONS.
        $subErrors = [];
        foreach ($uxon->getPropertiesAll() as $prop => $val) {
            if (!$val instanceof UxonObject) {
                continue;
            }

            $prototypeClass = $schema->getPrototypeClass($uxon, [$prop, ' '], $prototypeClass);
            if ($schema instanceof UxonSchema) {
                $propSchema = $schema->getSchemaForClass($prototypeClass);
            } else {
                $propSchema = $schema;
            }

            $subPath = $path;
            $subPath[] = $prop;
            $result = $this->validateUxonRecursive(
                $subPath,
                $val,
                $propSchema,
                $prototypeClass,
                $page,
                $validationObject ?? $lastValidationObject
            );
            $subErrors = array_merge($subErrors, $result);
        }
        
        if($creationError && empty($subErrors)) {
            $errors[] = $creationError;
        }
        
        // TODO geb 2025-09: To disable breadcrumb rendering we have to modify the json-editor library. Its not
        // TODO big change, nor does it create any dependencies, so it might be worth it. (treemode._renderValidationErrors).
        return array_merge($errors, $subErrors);
    }

    /**
     * Creates a mock object from a given UXON-Object, catching any errors encountered.
     * 
     * @param UxonSchemaInterface  $schema
     * @param string               $class
     * @param UxonObject           $uxon
     * @param UiPageInterface|null $page
     * @param mixed|null           $lastValidationObject
     * @return mixed
     * @throws \Throwable
     */
    protected function createValidationObject(
        UxonSchemaInterface $schema,
        string              $class,
        UxonObject          $uxon,
        UiPageInterface     &$page = null,
        mixed               $lastValidationObject = null
    ) : mixed
    {
        switch (true) {
            case is_a($class, UiPageInterface::class, true):
                $object = UiPageFactory::createFromUxon($this->getWorkbench(), $uxon);
                $page = $page ?? $object;
                return $object;
            case $schema instanceof WidgetSchema:
                if(!is_a($class, WidgetInterface::class, true)) {
                    return null;
                }
                
                if($page === null) {
                    $page = UiPageFactory::createEmpty($this->getWorkbench());
                }
                
                $parent = $lastValidationObject instanceof WidgetInterface ? $lastValidationObject : null;
                $widgetType = StringDataType::substringAfter($class, '\\', false, false, true);
                return WidgetFactory::createFromUxon($page, $uxon, $parent, $widgetType);
        }

        return null;
    }

    /**
     * STUB TODO geb 2025-09-08 Validation rules will be added in a later revision.
     * 
     * 
     * @param string $key
     * @return JsonValidationRule[]
     * @throws InvalidArgumentException
     */
    protected function getValidationRules(string $key) : array
    {
        $rules = [];
        
        // TODO Cache the fully instantiated rules and filter that cache.
        /*foreach ($this->loadValidationRuleUxons($key) as $uxon) {
            $rules[] = JsonValidationRule::fromUxon($this, $uxon);
        }*/
        
        return $rules;
    }

    /**
     * Load validation rules. Either from cache or from the model.
     * 
     * @param string $key
     * @return array
     * @throws InvalidArgumentException
     */
    protected function loadValidationRuleUxons(string $key) : array
    {
        // Try to load from cache.
        $cache = $this->getWorkbench()->getCache()->getPool('uxon.validation');
        $rules = $cache->get('rules');
        if(!empty($rules)) {
            return $rules;
        }

        // TODO Load via DataSheet
        if($key === '\exface\Core\Widgets\Tabs') {
            $rules[] = new UxonObject([
                'alias' => 'RecursionTest',
                'mode' => JsonValidationRule::MODE_PROHIBIT,
                'json_paths' => ["$..object_alias"],
                'message' => 'Rec'
            ]);
            
            $rules[] = new UxonObject([
                'alias' => 'NoTabsWidgetType',
                'mode' => JsonValidationRule::MODE_PROHIBIT,
                'json_paths' => ['$.tabs.*.widget_type'],
                'message' => 'Cant use "widget_type" for definition of "Tab"!'
            ]);

            $rules[] = new UxonObject([
                'alias' => 'PregSplitTest',
                'mode' => JsonValidationRule::MODE_PROHIBIT,
                'json_paths' => ["$..*[?(@.category == 'fiction' and @.price < 10 or @.color == \"red\")].price..value.post.."],
                'message' => '?'
            ]);
        }

        $cache->set('rules', $rules);
        return $rules;
    }
}