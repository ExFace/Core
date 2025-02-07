<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
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
 * Automatically adds custom attributes to the object, whenever it is loaded into memory.
 * 
 * ## Type Models
 * 
 * Type models are special templates that simplify the creation of new custom attributes. Each
 * 
 * ## Examples
 * 
 * ```
 *  {
 *      xxx "attribute_object_alias": "my.App.ART", // The object, that will receive the custom attributes
 *      xxx "relation_to_values_object": "ATTR_VAL", // The object, that contins values of the attribute (?)
 *      "attribute_name_alias": "NAME", // Attribute of the definition object, that contins the future attribute name
 *      "attribute_hint_alias": "DESCRIPTION", // Attribute of the definition object, that contins the future attribute
 * description
 *      "attribute_required_alias": "REQUIRED", // Attribute of the definition object, that contins the future
 * attribute alias
 *      "attribute_type_alias": "TYPE", // Attribute of the definition object, that contins the future attribute type 
 *      +++ "attribute_type_models": {
 *          "DATUM": {      
 *              "DATATYPE": "exface.Core.Date",
 *          },
 *          "PRIO": {
 *              "DATATYPE": "exface.Core.StringEnum",
 *              "CUSTOM_DATA_TYPE": {
 *                  "values": {
 *                      1: "High",
 *                      2: "Medium",
 *                      3: "Low"
 *                  }
 *              } 
 *          }, 
 *          "USER": {
 *              "DATATYPE": "exface.Core.HexadecimalNumber",
 *              "RELATED_OBJ": "exface.Core.USER"
 *              "RELATED_OBJ_ATTR": "USERNAME",
 *              "RELATION_CARDINALITY": null
 *          }
 *      }
 *  }
 * 
 * ```
 * 
 * ## Usage
 * 
 * How to use this behavior in another one (e.g. CustomAttributesJsonBehavior)
 * 
 * ```
 * $defBehavior = $this->findAttributeDefinitionBehavior($this->getObject());
 * // Load definitions
 * foreach ($defBehavior->getCustomAttributes($this->getObject()) as $attr) {
 *    $attr->setDataAddress(...);
 *    $this->getObject()->addAttribute($attr);
 * }
 * ```
 */
class CustomAttributeDefinitionBehavior extends AbstractBehavior
{
    protected const BASE_TEMPLATE_KEY = "BASE";
    protected const DATA_TYPE_KEY = "data_type";
    
    private array $typeModels = [];
    private ?string $attributeTypeModelAlias = null;
    private ?string $attributeNameAlias = null;
    private ?string $attributeStorageKeyAlias = null;
    private ?string $attributeHintAlias = null;
    private ?string $attributeRequiredAlias = null;
    private ?string $definitionOwnerAttributeAlias = null;

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
     * Summary of getAttributes
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
        if($ownerAlias = $this->getDefinitionOwnerAttributeAlias()) {
            $logBook->addLine('Loading only definitions that match "' . $targetAlias . '" in "' . $ownerAlias . '" from "' . $this->getObject()->getAliasWithNamespace() . '".');
            $attributeDefinitionsSheet->getFilters()->addConditionFromString($ownerAlias, $targetAlias);
        } else {
            $logBook->addLine('No value was set for "definition_owner_attribute_alias". Loading all definitions from "' . $this->getObject()->getAliasWithNamespace() . '".');
        }
        
        $attributeDefinitionsSheet->dataRead();
        
        $logBook->addLine('Attribute definitions loaded successfully.');
        $logBook->addDataSheet('Attribute Definitions', $attributeDefinitionsSheet);
        $logBook->addIndent(-1);
        $logBook->addLine('Adding custom attributes to "' . $targetAlias . '"...');
        $logBook->addIndent(1);
        
        foreach ($attributeDefinitionsSheet->getRows() as $definitionRow) {
            $typeKey = $definitionRow[$modelAlias];
            if(! $typeModel = $this->getTypeModel($typeKey)) {
                throw new BehaviorRuntimeError($this, 'Custom attribute type "' . $typeKey . '" not defined for "' . $this->getObject()->getAliasWithNamespace() . '"!', null , null, $logBook);
            }
            
            $name = $definitionRow[$nameAlias];
            $storageKey = $definitionRow[$keyAlias];
            $alias = $attributeLoader->customAttributeStorageKeyToAlias($storageKey);
            $address = $attributeLoader->getCustomAttributeDataAddress($storageKey);
            $attr = MetaObjectFactory::addAttributeTemporary(
                $targetObject, 
                $name, 
                $alias, 
                $address, 
                $typeModel[self::DATA_TYPE_KEY]);
            
            unset($typeModel[self::DATA_TYPE_KEY]);
            unset($typeModel[$nameAlias]);
            
            $attr->importUxonObject(new UxonObject($typeModel));
            $attr->setShortDescription($definitionRow[$hintAlias]);
            $attr->setRequired($definitionRow[$requiredAlias]);
            
            $attrs[] = $attr;
            $logBook->addLine('Added "' . $attr->getAlias() . '" with data address "' . $attr->getDataAddress() . '" of type "' . $typeKey . '(' . $attr->getDataType()->getAliasWithNamespace() . ')".');
        }
        $logBook->addIndent(-1);
        
        return $attrs;
    }
    
    public function getTypeModelsAll() : array
    {
        if(empty($this->typeModels)) {
            $this->setTypeModels(new UxonObject());
        }
        
        return  $this->typeModels;
    }

    public function getTypeModel(string $typeKey) : ?array
    {
        return $this->getTypeModelsAll()[$typeKey];
    }

    protected function setTypeModels(UxonObject $uxon) : CustomAttributeDefinitionBehavior
    {
        // Prepare type models.
        $inputTypeModels = $uxon->toArray();
        $defaultTypeModels = $this->getDefaultTypeModels();
        
        // Prepare base model.
        $baseModel = $defaultTypeModels[self::BASE_TEMPLATE_KEY];
        if(key_exists(self::BASE_TEMPLATE_KEY, $inputTypeModels)) {
            // Optionally, apply changes from input to base model.
            $baseModel = array_merge($baseModel, $inputTypeModels[self::BASE_TEMPLATE_KEY]);
        }

        $this->typeModels = [];
        // Merge defaults with input.
        foreach (array_merge($defaultTypeModels, $inputTypeModels) as $typeKey => $typeModel) {
            // Inherit from base model, then store the result.
            $this->typeModels[$typeKey] = array_merge($baseModel, $typeModel);
        }
        
        return $this;
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
    public function getDefinitionOwnerAttributeAlias() : ?string
    {
        return $this->definitionOwnerAttributeAlias;
    }

    /**
     * (Optional) The attribute alias used to determine, what object a custom attribute belongs to.
     * 
     * You only need to set a value for this property, if you are storing custom attribute
     * definitions for more than one MetaObject in the same table.
     * 
     * @uxon-property definition_owner_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string|null $alias
     * @return $this
     */
    public function setDefinitionOwnerAttributeAlias(?string $alias) : static
    {
        $this->definitionOwnerAttributeAlias = $alias;
        return $this;
    }

    /**
     * An associative array with `[TypeModelKey => [...(TypeModel)]]` that contains some
     * basic type model definitions, as well as the base type model.
     * 
     * All default type models, including the base, can be overwritten with `type_models`.
     * 
     * @return string[][]
     */
    protected function getDefaultTypeModels() : array
    {
        return [
            'DATE' => [
                self::DATA_TYPE_KEY => "exface.Core.Date"
            ],
            'TEXT' => [
                self::DATA_TYPE_KEY => "exface.Core.String"
            ],
            'NUMBER' => [
                self::DATA_TYPE_KEY => "exface.Core.Number"
            ],
            'TIME' => [
                self::DATA_TYPE_KEY => "exface.Core.Time"
            ],
            // The base template is intentionally incomplete, ignoring fields like
            // "name", "description" and so on.
            self::BASE_TEMPLATE_KEY => [
                // DATATYPE
                self::DATA_TYPE_KEY => "exface.Core.String",
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
            ]
        ];
    }
}