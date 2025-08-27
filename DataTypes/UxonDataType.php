<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\DataTypes\UxonValidationError;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\UxonSchemaFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\iCanValidate;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Uxon\JsonValidationRule;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Uxon\WidgetSchema;
use Psr\SimpleCache\InvalidArgumentException;

class UxonDataType extends JsonDataType implements iCanValidate
{
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
            // Validate the UXON import.
            if(!$uxon->isArray(true)) {
                $validationObject = $this->createValidationObject(
                    $schema,
                    $prototypeClass,
                    $uxon,
                    $page,
                    $lastValidationObject
                ) ?? $lastValidationObject;
            }
        } catch (\Throwable $exception) {
            $errors[] = new UxonValidationError(
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
            $subErrors = $this->validateUxonRecursive(
                $subPath,
                $val,
                $propSchema,
                $prototypeClass,
                $page,
                $validationObject ?? $lastValidationObject
            );
            $errors = array_merge($errors, $subErrors);
        }
        
        return $errors;
    }

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
     * @param string $key
     * @return JsonValidationRule[]
     * @throws InvalidArgumentException
     */
    protected function getValidationRules(string $key) : array
    {
        $rules = [];
        
        foreach ($this->loadValidationRuleUxons($key) as $uxon) {
            $rules[] = JsonValidationRule::fromUxon($this, $uxon);
        }
        
        return $rules;
    }
    
    protected function loadValidationRuleUxons(string $key) : array
    {
        // Try to load from cache.
        $cache = $this->getWorkbench()->getCache()->getPool('uxon.validation');
        $rules = $cache->get('rules');
        if(!empty($rules)) {
            return $rules;
        }

        // 2025-08-01 geb load via DataSheet
        if($key === '\exface\Core\Widgets\Tabs') {
            $rules[] = new UxonObject([
                'alias' => 'TEST',
                'mode' => JsonValidationRule::MODE_PROHIBIT,
                'pattern' => UxonObject::fromJson('{"tabs":[{"widget_type": "*"}]}'),
                'message' => 'Cant use "widget_type" for definition of "Tab"!'
            ]);
        }

        $cache->set('rules', $rules);
        return $rules;
    }
}