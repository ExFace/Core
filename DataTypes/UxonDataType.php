<?php
namespace exface\Core\DataTypes;

use exface\Core\Actions\ShowDialog;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\UxonSchemaFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\iCanValidate;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Uxon\JsonValidationRule;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Uxon\WidgetSchema;

class UxonDataType extends JsonDataType implements iCanValidate
{
    public function validate(mixed $inputData,string $rootPrototypeClass = null): void
    {
        if(!$inputData instanceof UxonObject) {
            $inputData = UxonObject::fromAnything($inputData);
        }

        $schema = UxonSchemaFactory::create($this->getWorkbench(), $this->getSchema());
        
        $this->validateUxonRecursive(
            $inputData,
            $schema,
            $rootPrototypeClass
        );
    }

    protected function validateUxonRecursive(
        UxonObject $uxon,
        UxonSchemaInterface $schema,
        string $prototypeClass = null,
        UiPageInterface $page = null,
        mixed $lastValidationObject = null
    ) : void
    {
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
            
            // Check validation rules.
            foreach ($this->getValidationRules($prototypeClass) as $rule) {
                $rule->check($uxon);
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

                $this->validateUxonRecursive(
                    $val, 
                    $propSchema, 
                    $prototypeClass, 
                    $page, 
                    $validationObject ?? $lastValidationObject
                );
            }
        } catch (\Throwable $exception) {
            throw new DataTypeValidationError($this, 'HEY', null, $exception);
        }
    }

    protected function createValidationObject(
        UxonSchemaInterface $schema,
        string              $class,
        UxonObject $uxon,
        UiPageInterface &$page = null,
        mixed $lastValidationObject = null
    ) : mixed
    {
        switch (true) {
            case is_a($class, UiPageInterface::class, true):
                $object = UiPageFactory::createFromUxon($this->getWorkbench(), $uxon);
                $page = $page ?? $object;
                return $object;
            case $schema instanceof WidgetSchema:
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
     */
    protected function getValidationRules(string $key) : array
    {
        $rules = [];
        
        // 2025-08-01 geb load via DataSheet
        if($key === '\exface\Core\Widgets\Tabs') {
            $rules[] = new JsonValidationRule(
                $this,
                'TEST',
                JsonValidationRule::MODE_PROHIBIT,
                UxonObject::fromJson('{"tabs":[{"widget_type": "*"}]}'),
                ''
            );
        }
        
        return $rules;
    }
}