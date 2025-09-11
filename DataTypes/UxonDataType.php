<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\DataTypes\UxonValidationError;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\UxonSchemaFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\Exceptions\UxonExceptionInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Uxon\JsonValidationRule;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Widgets\Button;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

/**
 * UXON specific implementation of the JSON-Datatype.
 * 
 * @see JsonDataType, DataTypeInterface
 */
class UxonDataType extends JsonDataType
{
    private const PRP_OBJECT_ALIAS = 'object_alias';

    /**
     * Check if a given class as a property setter for a given property name.
     * 
     * @param string $class
     * @param string $propertyName
     * @return bool
     */
    public static function hasPropertySetter(string $class, string $propertyName) : bool
    {
        $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($propertyName);
        return method_exists($class, $setterCamelCased);
    }

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
     * @throws InvalidArgumentException
     */
    public function validate(mixed $inputData, string $rootPrototypeClass = null): array
    {
        if(!$inputData instanceof UxonObject) {
            $inputData = UxonObject::fromAnything($inputData);
        }
        
        if(!$inputData->hasProperty(self::PRP_OBJECT_ALIAS)) {
            $inputData->setProperty(self::PRP_OBJECT_ALIAS, null);
        }

        $schema = UxonSchemaFactory::create($this->getWorkbench(), $this->getSchema());

        return $this->validateUxonRecursive(
            [],
            $inputData,
            $schema,
            $inputData->getProperty(self::PRP_OBJECT_ALIAS),
            $rootPrototypeClass,
        );
    }

    /**
     * Performs a recursive validation.
     *
     * @param array               $path
     * @param UxonObject          $uxon
     * @param UxonSchemaInterface $schema
     * @param string|null         $objectAlias
     * @param string|null         $prototypeClass
     * @param mixed|null          $lastValidationObject
     * @return array
     * @throws InvalidArgumentException
     */
    protected function validateUxonRecursive(
        array $path,
        UxonObject $uxon,
        UxonSchemaInterface $schema,
        string $objectAlias = null,
        string $prototypeClass = null,
        mixed $lastValidationObject = null
    ) : array
    {
        $errors = [];
        $prototypeClass = $prototypeClass ?? $schema->getPrototypeClass($uxon, []);
        $validationObject = null;
        $affectedProperty = null;

        try {
            // Validate the UXON import, by creating a mock object.
            // TODO This causes a lot of false positives, maybe there is a better way.
            // TODO Additionally, the errors are annotated to the parent, not the affected property.
            if(!$uxon->isArray(true)) {
                $validationObject = $this->createValidationObject(
                    $schema,
                    $prototypeClass,
                    $uxon->copy(),
                    $lastValidationObject
                );
            }
            
            $creationError = null;
        } catch (Throwable $error) {
            $creationError = $this->processCreationError($error, $uxon, $path, $prototypeClass);
            $affectedProperty = $creationError->getAffectedProperty();
            
            // If the affected property is not a UXON object, we should render the error,
            // otherwise we should wait, to avoid redundant error messages.
            if($affectedProperty !== null) {
                $errors[] = $creationError;
                $creationError = null;
            }
            
            // Try to create an empty validation object to improve accuracy of child validations.
            try {
                $emptyUxon = UxonObject::fromArray([self::PRP_OBJECT_ALIAS => $objectAlias]);
                if($uxon->hasProperty('attribute_alias')) {
                    $emptyUxon->setProperty('attribute_alias', $uxon->getProperty('attribute_alias'));
                }
                
                $validationObject = $this->createValidationObject(
                    $schema,
                    $prototypeClass,
                    $emptyUxon,
                    $lastValidationObject
                );
            } catch (Throwable $error) {
                
            }
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
            if($affectedProperty !== null && $prop === $affectedProperty) {
                continue;
            }
            
            if (!$val instanceof UxonObject) {
                continue;
            }

            $prototype = $schema->getPrototypeClass($uxon, [$prop, ' '], $prototypeClass);
            if ($schema instanceof UxonSchema) {
                $propSchema = $schema->getSchemaForClass($prototype);
            } else {
                $propSchema = $schema;
            }

            if($val->hasProperty(self::PRP_OBJECT_ALIAS)) {
                $objectAlias = $val->getProperty(self::PRP_OBJECT_ALIAS);
            } elseif (!$val->isArray(true) && !$val->hasProperty(self::PRP_OBJECT_ALIAS)
            ) {
                $val->setProperty(self::PRP_OBJECT_ALIAS, $objectAlias);
            }
            
            $subPath = $path;
            $subPath[] = $prop;
            $result = $this->validateUxonRecursive(
                $subPath,
                $val,
                $propSchema,
                $objectAlias,
                $prototype,
                $validationObject
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
    
    protected function processCreationError(
        Throwable $error, 
        UxonObject $uxon, 
        array $path, 
        string $prototype
    ) : UxonValidationError
    {
        $property = null;
        $previous = $error->getPrevious();
        
        if($previous instanceof UxonExceptionInterface) {
            $property = $this->getAffectedProperty($uxon, $previous, $prototype);
        } 
        
        if ($property === null && $error instanceof UxonExceptionInterface) {
            $property = $this->getAffectedProperty($uxon, $error, $prototype);
        }
        
        if($property !== null) {
            $path[] = $property;
        }
        
        return new UxonValidationError(
            $path,
            'Invalid UXON.' . $error->getMessage(),
            $error instanceof ExceptionInterface ? $error->getMessage() : '',
            $error
        );
    }
    
    protected function getAffectedProperty(UxonObject $uxon, UxonExceptionInterface $error, string $prototype) : ?string
    {
        switch (true) {
            case is_a($prototype, Button::class, true):
                $property = $error->getAffectedProperty();
                $baseProperty = 'action_' . $property;
                return $uxon->hasProperty($baseProperty) ? $baseProperty : $property;
            default:
                return $error->getAffectedProperty();
        }
    }

    /**
     * Creates a mock object from a given UXON-Object, catching any errors encountered.
     * 
     * @param UxonSchemaInterface  $schema
     * @param string               $class
     * @param UxonObject           $uxon
     * @param mixed|null           $lastValidationObject
     * @return mixed
     * @throws Throwable
     */
    protected function createValidationObject(
        UxonSchemaInterface $schema,
        string              $class,
        UxonObject          $uxon,
        mixed               $lastValidationObject = null
    ) : mixed
    {
        switch (true) {
            case $uxon->isEmpty():
                return null;
            case is_a($class, UiPageInterface::class, true):
                return UiPageFactory::createFromUxon($this->getWorkbench(), $uxon);
            default:
                $page = UiPageFactory::createEmpty($this->getWorkbench());
                return $schema->createValidationObject($uxon, $class, $page, $lastValidationObject);
        }
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