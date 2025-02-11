<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Widget\OnUiActionWidgetInitEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\Behaviors\CustomAttributeLoaderInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Widgets\Value;

/**
 * Handles custom attribute definitions and type models. 
 * 
 * Custom attribute definitions are stored on the object this behavior is attached to, while
 * type models can be defined by adding or editing them via the `type_models` property (see below).
 * 
 * ## Custom Attribute Definitions
 * 
 * Each custom attribute is based off of a unique definition, stored on the object this behavior is attached to.
 * Whenever a custom attribute needs to be loaded, this behavior instantiates that attribute with the help of its definition,
 * which is assembled from these properties:
 * 
 * - **Name**(`attribute_name_alias`): The attribute that holds the display name of the custom attribute.
 * - **Storage Key**(`attribute_storage_key_alias`): The attribute that holds the storage key. The storage key is used to generate a
 * data address and a technical alias of the custom attribute. Make sure it matches whatever storage scheme you are using. For example
 * JSON storage keys should be written in snake_case, have no hierarchies and may omit the root accessor: `$.some_alias`, `some_alias`.
 * - **Type Model**(`attribute_type_model_alias`): The attribute that holds the type model key. Type models simplify the configuration of
 * custom attributes, by assigning meaningful defaults to most attribute properties. They are identified with a key, that must either match a
 * default type model or a type model that you defined in `type_models` (see below).
 * - **Hint**(`attribute_hint_alias`): The attribute that holds a short description of the custom attribute.
 * - **Required**(`attribute_required_alias`): The attribute that holds the setting for whether the custom attribute is required.
 * - **Owner Alias**(`attribute_definition_owner_alias`): This property is optional. You only need to set it if you wish to store definitions
 * for attributes that belong to multiple different MetaObjects in the same table. In that case, the definition owner is used to identify what 
 * MetaObject a custom attribute definition belongs to.
 * 
 * ## Type Models
 * 
 * Type models are special templates that simplify the creation of new custom attributes. They automatically set the properties of the
 * attribute to match a pre-configured template, meaning users won't have to know about the technical details of attribute configuration.
 * When creating a new custom attribute they must assign a type model to it. They can choose from all type models configured in the `type_models`
 * property, as well as some basic default type models, such as "DATE", "TIME", "TEXT" and "NUMBER".
 * 
 * To define a new type model, add a new entry to the `type_models` property or duplicate an existing one. Type models use a simple form
 * of inheritance. Any property that you omitted from your new type model will instead be inherited from the parent of that type model.
 * You can assign a parent by entering it in the `inherits` property. If you omit that property or have entered an invalid parent, your
 * type model will inherit from a default configuration. Inheritance will never overwrite properties that you specified in your type model.
 * 
 * ## Examples
 * 
 * ### Behavior Configuration
 * 
 * ```
 * 
 *  {
 *      "attribute_hint_alias": "hint",
 *      "attribute_name_alias": "name",
 *      "attribute_required_alias": "required",
 *      "attribute_type_model_alias": "type_model",
 *      "attribute_storage_key_alias": "storage_key",
 *      "attribute_definition_owner_alias": "owner_alias",
 *      "type_models": {
 *          "INHERITS_DEFAULT": {
 *              "inherits": "",
 *              "required": true,
 *              "copyable": false
 *          },
 *          "INHERITS_TIME": {
 *              "inherits": "TIME",
 *              "hidden": true,
 *              "editable": false
 *          }
 *      }
 * }
 * 
 * ```
 * 
 * ### Default Type Model
 * 
 * The default type model is defined in code and is shown here for demonstrative purposes only. 
 * You don't have to add it manually. Any type model without a valid parent will inherit these
 * settings.
 * 
 * ```
 * 
 *  "DEFAULT" : {
 *      "inherits": "",
 *      "data_type": "exface.Core.String",
 *      "readable": true,
 *      "writable": true,
 *      "copyable": true,
 *      "editable": true,
 *      "required": false,
 *      "hidden": false,
 *      "sortable": true,
 *      "filterable": true,
 *      "aggregatable": true,
 *      "default_aggregate_function": "",
 *      "default_sorter_dir": "ASC",
 *      "value_list_delimiter": ",",
 *      "default_display_order": ""
 *  } 
 * 
 * ```
 * 
 * ## Usage
 * 
 * How to use this behavior in another one (e.g. `CustomAttributesJsonBehavior`)
 * 
 * ```
 * 
 * $definitionBehavior = $definitionObject->getBehaviors()->findBehavior(CustomAttributeDefinitionBehavior::class);
 * if(! $definitionBehavior instanceof CustomAttributeDefinitionBehavior) {
 *      $msg = 'Could not find behavior of type "' . CustomAttributeDefinitionBehavior::class . '" on MetaObject "' . $definitionObjectAlias . '"!';
 *      throw new BehaviorRuntimeError( $this, $msg, null, null, $logBook);
 * }
 *
 * $customAttributes = $definitionBehavior->addCustomAttributes(
 *      $this->getObject(),
 *      $this,
 *      $logBook);
 * 
 * ```
 */
class CustomAttributeDefinitionBehavior extends AbstractBehavior
{
    protected const KEY_DATA_TYPE = "data_type";
    protected const KEY_INHERITS_FROM = "inherits";
    
    private array $typeModels = [];
    private ?string $attributeTypeModelAlias = null;
    private ?string $attributeNameAlias = null;
    private ?string $attributeStorageKeyAlias = null;
    private ?string $attributeHintAlias = null;
    private ?string $attributeRequiredAlias = null;
    private ?string $attributeDefinitionOwnerAlias = null;

    protected function registerEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(
            OnUiActionWidgetInitEvent::getEventName(),
            [$this,'onWidgetInitModifyEditor'],
            $this->getPriority())
        ;

        return $this;
    }

    protected function unregisterEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(
            OnUiActionWidgetInitEvent::getEventName(),
            [$this,'onWidgetInitModifyEditor'],
        );

        return $this;
    }

    /**
     * Add type models as dropdown to any widget editors that access `$this->typeModels`.
     * 
     * @param OnUiActionWidgetInitEvent $event
     * @return void
     */
    public function onWidgetInitModifyEditor(OnUiActionWidgetInitEvent $event) : void
    {
        $widget = $event->getWidget();
        if(! $widget->getMetaObject()->isExactly($this->getObject()) || 
           ! $widget instanceof iContainOtherWidgets) {
            return;
        }
        
        $inputWidgets = $widget->getInputWidgets();
        
        if(empty($inputWidgets)) {
            return;
        }

        $typeAlias = $this->attributeTypeModelAlias;
        foreach ($inputWidgets as $input) {
            if(!$input instanceof Value || $input->getAttributeAlias() !== $typeAlias) {
                continue;
            }
            
            
            // TODO if input is `attribute_type_alias` - make sure, it shows a dorpdown with the
            // available types in this behavior (e.g. DATUM, PRIO, USER from above).
        }
    }

    /**
     * Load and add custom attributes to the target object.
     * 
     * @return MetaAttributeInterface[]
     */
    public function addCustomAttributes(MetaObjectInterface $targetObject, CustomAttributeLoaderInterface $attributeLoader, BehaviorLogBook $logBook) : array
    {
        if(empty($this->getTypeModelsAll())) {
            throw new BehaviorRuntimeError($this, 'Could not load custom attributes: Missing type models!', null, null, $logBook);
        }
        
        $attrs = [];
        
        $logBook->addLine('Loading attribute definitions...');
        $logBook->addIndent(1);
        
        $attributeDefinitionsSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getObject());
        $attributeDefinitionsSheet->getColumns()->addMultiple([
            $modelAlias = $this->getAttributeTypeModelAlias(),
            $nameAlias = $this->getAttributeNameAlias(),
            $keyAlias = $this->getAttributeStorageKeyAlias(),
            $hintAlias = $this->getAttributeHintAlias(),
            $requiredAlias = $this->getAttributeRequiredAlias()
        ]);

        $targetAlias = $targetObject->getAliasWithNamespace();
        if($ownerAlias = $this->getAttributeDefinitionOwnerAlias()) {
            $logBook->addLine('Loading only definitions that match "' . $targetAlias . '" in "' . $ownerAlias . '" from "' . $this->getObject()->getAliasWithNamespace() . '".');
            $attributeDefinitionsSheet->getFilters()->addConditionFromString($ownerAlias, $targetAlias);
        } else {
            $logBook->addLine('No value was set for "attribute_definition_owner_alias". Loading ALL definitions from "' . $this->getObject()->getAliasWithNamespace() . '".');
        }
        
        $attributeDefinitionsSheet->dataRead();
        
        $logBook->addLine('Attribute definitions loaded successfully.');
        $logBook->addDataSheet('Attribute Definitions', $attributeDefinitionsSheet);
        $logBook->addIndent(-1);
        $logBook->addLine('Adding custom attributes to "' . $targetAlias . '"...');
        $logBook->addIndent(1);
        
        foreach ($attributeDefinitionsSheet->getRows() as $definitionRow) {
            $typeKey = $definitionRow[$modelAlias];
            $name = $definitionRow[$nameAlias];

            if(! $typeModel = $this->getTypeModel($typeKey)) {
                throw new BehaviorRuntimeError($this, 'Error while loading custom attribute "' . $name . '": Type model "' . $typeKey . '" not found! Check "' . $this->getAliasWithNamespace() . '" for available type models.', null , null, $logBook);
            }
            
            $storageKey = $definitionRow[$keyAlias];
            $alias = $attributeLoader->customAttributeStorageKeyToAlias($storageKey);
            $address = $attributeLoader->getCustomAttributeDataAddress($storageKey);
            $attr = MetaObjectFactory::addAttributeTemporary(
                $targetObject, 
                $name, 
                $alias, 
                $address, 
                $typeModel[self::KEY_DATA_TYPE]);
            
            // Remove properties from the template that should or could not be applied to the attribute.
            unset($typeModel[self::KEY_DATA_TYPE]);
            unset($typeModel[self::KEY_INHERITS_FROM]);
            unset($typeModel[$nameAlias]);
            // Apply the template.
            $attr->importUxonObject(new UxonObject($typeModel));
            // Set values that were not stored in the template.
            $attr->setShortDescription($definitionRow[$hintAlias]);
            $attr->setRequired($definitionRow[$requiredAlias]);
            
            $attrs[] = $attr;
            $logBook->addLine('Added "' . $attr->getAlias() . '" with data address "' . $attr->getDataAddress() . '" of type "' . $typeKey . '(' . $attr->getDataType()->getAliasWithNamespace() . ')".');
        }
        $logBook->addIndent(-1);
        
        return $attrs;
    }

    /**
     * Returns an array with all currently defined type models.
     * 
     * @return array
     */
    protected function getTypeModelsAll() : array
    {
        if(empty($this->typeModels)) {
            $this->setTypeModels(new UxonObject());
        }
        
        return  $this->typeModels;
    }

    /**
     * Returns the type model for the specified key or NULL if no match was found.
     * 
     * @param string $typeKey
     * @return array|null
     */
    protected function getTypeModel(string $typeKey) : ?array
    {
        return $this->getTypeModelsAll()[$typeKey];
    }

    /**
     * Type models are special templates that simplify the creation of new custom attributes. 
     * 
     * They automatically set the properties of the attribute to match a pre-configured template, 
     * meaning users won't have to know about the technical details of attribute configuration.
     * When creating a new custom attribute they must assign a type model to it. They can choose 
     * from all type models configured in the `type_models` property, as well as some basic default 
     * type models, such as "DATE", "TIME", "TEXT" and "NUMBER".
     * 
     * @uxon-property type_models
     * @uxon-type \exface\core\CommonLogic\Model\Attribute[]
     * @uxon-template {"":{"inherits":"","data_type":"exface.Core.String","readable":true,"writable":true,"copyable":true,"editable":true,"required":false,"hidden":false,"sortable":true,"filterable":true,"aggregatable":true,"default_aggregate_function":"","default_sorter_dir":"ASC","value_list_delimiter":",","default_display_order":""}}
     * 
     * @param UxonObject $uxon
     * @return $this
     */
    public function setTypeModels(UxonObject $uxon) : CustomAttributeDefinitionBehavior
    {
        // Prepare type models.
        $this->typeModels = [];
        $inputTypeModels = $uxon->toArray();
        $defaultTypeModels = $this->getDefaultTypeModels();

        // Merge defaults with input.
        $mergedModels = array_merge($defaultTypeModels, $inputTypeModels);
        
        // Resolve inheritance hierarchy.
        foreach ($mergedModels as $typeKey => $typeModel) {
            if(key_exists($typeKey, $this->typeModels)) {
                continue;
            }
            
            $resolvedModels = $this->resolveInheritanceHierarchy(
                $typeKey, 
                $typeModel, 
                $mergedModels, 
                $this->typeModels);
            
            $this->typeModels = array_merge($this->typeModels, $resolvedModels);
        }
        
        return $this;
    }

    /**
     * Resolves the inheritance hierarchy of a given type model by applying naive recursion.
     * 
     * Loops, and empty or invalid parent keys resolve to `array_merge($model, $this->getBaseTypeModel())`.
     * NOTE: Loops can only be detected if you provide an accurate value for `$resolvedModels`!
     * 
     * @param string $key The type model key for the model you want to resolve.
     * @param array  $model An associative array with `[property => value]` that represents 
     * the model you wish to resolve.
     * @param array  $allModels An associative array with `[modelKey => model[]]` that contains all models, 
     * regardless of whether they have been resolved or not.
     * @param array  $resolvedModels An associative array with `[modelKey => model[]]` that contains all models
     * which have already been resolved. Make sure this value is accurate and up-to-date!
     * @return array An associative array with `[modelKey => model[]]` that contains the resolved model, as well
     * as the models resolved during all recursions.
     */
    protected function resolveInheritanceHierarchy(
        string $key, 
        array $model, 
        array $allModels, 
        array $resolvedModels) : array
    {
        // If we have already been resolved, return without changes.
        if(key_exists($key, $resolvedModels)) {
            return $resolvedModels[$key];
        }

        // If model has no explicit parent OR that parent is invalid, inherit from base.
        $parentKey = $model[self::KEY_INHERITS_FROM];
        if(!$parentKey || $parentKey === $key || !key_exists($parentKey, $allModels)) {
            return [$key => array_merge($this->getBaseTypeModel(), $model)];
        }

        // If model has an explicit parent that has already been resolved,
        // we can inherit from it directly.
        if(key_exists($parentKey, $resolvedModels)) {
            return [$key => array_merge($resolvedModels[$parentKey], $model)];
        }
        
        // Otherwise, we have to resolve the parent model recursively.
        // We add ourselves to the resolved list to prevent loops. 
        // This is safe, because each type model has a single root.
        $resolvedModels[$key] = array_merge($this->getBaseTypeModel(), $model);
        $result = $this->resolveInheritanceHierarchy(
            $parentKey,
            $allModels[$parentKey],
            $allModels,
            $resolvedModels
        );
        // Now we merge with our resolved parent.
        $result[$key] = array_merge($result[$parentKey], $model);
        
        return $result;
    }

    /**
     * @return string
     */
    public function getAttributeTypeModelAlias() : string
    {
        if(! $this->attributeTypeModelAlias) {
            throw new BehaviorConfigurationError($this, 'Missing value for property "attribute_type_model_alias"!');
        }
        
        return $this->attributeTypeModelAlias;
    }

    /**
     * Tell this behavior, in which attribute it will find the type model of a custom attribute.
     * 
     * @uxon-property attribute_type_model_alias
     * @uxon-type metamodel:attribute
     * @uxon-template "type_model"
     * 
     * @param string $alias
     * @return $this
     */
    public function setAttributeTypeModelAlias(string $alias) : static
    {
        $this->attributeTypeModelAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    public function getAttributeNameAlias() : string
    {
        if(! $this->attributeNameAlias) {
            throw new BehaviorConfigurationError($this, 'Missing value for property "attribute_name_alias"!');
        }
        
        return $this->attributeNameAlias;
    }

    /**
     * Tell this behavior, in which attribute it will find the name of a custom attribute.
     * 
     * @uxon-property attribute_name_alias
     * @uxon-type metamodel:attribute
     * @uxon-template "name"
     * 
     * @param string $alias
     * @return $this
     */
    public function setAttributeNameAlias(string $alias) : static
    {
        $this->attributeNameAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    public function getAttributeStorageKeyAlias() : string
    {
        if(! $this->attributeStorageKeyAlias) {
            throw new BehaviorConfigurationError($this, 'Missing value for property "attribute_storage_key_alias"!');
        }

        return $this->attributeStorageKeyAlias;
    }

    /**
     * Tell this behavior, in which attribute it will find the storage key of a custom attribute.
     *
     * @uxon-property attribute_storage_key_alias
     * @uxon-type metamodel:attribute
     * @uxon-template "storage_key"
     *
     * @param string $alias
     * @return $this
     */
    public function setAttributeStorageKeyAlias(string $alias) : static
    {
        $this->attributeStorageKeyAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    public function getAttributeHintAlias() : string
    {
        if(! $this->attributeHintAlias) {
            throw new BehaviorConfigurationError($this, 'Missing value for property "attribute_hint_alias"!');
        }
        
        return $this->attributeHintAlias;
    }

    /**
     * Tell this behavior, in which attribute it will find the description of a custom attribute.
     * 
     * @uxon-property attribute_hint_alias
     * @uxon-type metamodel:attribute
     * @uxon-template "hint"
     * 
     * @param string $alias
     * @return $this
     */
    public function setAttributeHintAlias(string $alias) : static
    {
        $this->attributeHintAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    public function getAttributeRequiredAlias() : string
    {
        if(! $this->attributeRequiredAlias) {
            throw new BehaviorConfigurationError($this, 'Missing value for property "attribute_required_alias"!');
        }
        
        return $this->attributeRequiredAlias;
    }

    /**
     * Tell this behavior, in which attribute it will find whether an attribute will be required.
     * 
     * @uxon-property attribute_required_alias
     * @uxon-type metamodel:attribute
     * @uxon-template "required"
     * 
     * @param string $alias
     * @return $this
     */
    public function setAttributeRequiredAlias(string $alias) : static
    {
        $this->attributeRequiredAlias = $alias;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAttributeDefinitionOwnerAlias() : ?string
    {
        return $this->attributeDefinitionOwnerAlias;
    }

    /**
     * (Optional) The attribute alias used to determine, what object a custom attribute belongs to.
     * 
     * You only need to set a value for this property, if you are storing custom attribute
     * definitions for more than one MetaObject in the same table.
     * 
     * @uxon-property attribute_definition_owner_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string|null $alias
     * @return $this
     */
    public function setAttributeDefinitionOwnerAlias(?string $alias) : static
    {
        $this->attributeDefinitionOwnerAlias = $alias;
        return $this;
    }

    /**
     * An associative array with `[TypeModelKey => []]` that contains some basic type model definitions
     * 
     * All default type models can be overwritten with `type_models`.
     * 
     * @return string[][]
     */
    protected function getDefaultTypeModels() : array
    {
        return [
            'DATE' => [
                self::KEY_DATA_TYPE => "exface.Core.Date",
            ],
            'TEXT' => [
                self::KEY_DATA_TYPE => "exface.Core.String",
            ],
            'NUMBER' => [
                self::KEY_DATA_TYPE => "exface.Core.Number",
            ],
            'TIME' => [
                self::KEY_DATA_TYPE => "exface.Core.Time",
            ],
        ];
    }

    /**
     * Returns the base from which all type models inherit by default.
     * 
     * @return string[]
     */
    protected function getBaseTypeModel() : array
    {
        return [
            // DATATYPE
            self::KEY_DATA_TYPE => "exface.Core.String",
            // BASIC FLAGS
            "readable" => "true",
            "writable" => "true",
            "copyable" => "true",
            "editable" => "true",
            "required" => "false",
            "hidden" => "false",
            "sortable" => "true",
            "filterable" => "true",
            "aggregatable" => "true",
            // DEFAULTS
            "default_aggregate_function" => "",
            "default_sorter_dir" => "ASC",
            "value_list_delimiter" => ",",
            "default_display_order" => "",
        ];
    }
}