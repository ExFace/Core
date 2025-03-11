<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\CustomAttribute;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\Behaviors\CustomAttributeLoaderInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Handles custom attribute definitions and type models. 
 * 
 * Custom attribute definitions will be stored on the object of this behavior, 
 * while type models can be defined in the `type_models` property of this behavior (see "Type Models").
 * 
 * ## Custom Attribute Definitions
 * 
 * Each custom attribute is based off of a unique definition, stored on the object of this behavior.
 * Whenever a custom attribute is being loaded, this behavior will configure it based on the following data:
 * 
 * - **Name**(`attribute_name_alias`): The display name of the custom attribute.
 * - **Storage Key**(`attribute_storage_key_alias`): The storage key is used to generate the data address and the
 * technical alias  of the custom attribute. Make sure it matches whatever storage scheme you are using. For example:
 * JSON storage keys should  be written in snake_case, have no hierarchies and may omit the root accessor. Both
 * `$.some_alias` and `some_alias` will result in the same technical alias and data address. 
 * - **Type Model**(`attribute_type_model_alias`): The type model the custom attribute is based on. Type models
 * simplify
 * the configuration of custom attributes, by assigning meaningful defaults to most attribute properties. They are
 * identified with a key, that must either match a default type model or a type model that you defined in `type_models`
 * (see "Type Models").
 * - **Category Alias**(`attribute_category_alias`): The categories the custom attribute belongs to. Categories give
 * designers more fine-grained control over which custom attributes will be included in automatically generated widgets
 * (see "Categories").
 * - **Hint**(`attribute_hint_alias`): The short description is used for tooltips and info panels, when working with an
 * attribute.
 * - **Required**(`attribute_required_alias`): Determines, whether a custom attribute will be required in editors.
 * - **Owner Object**(`attribute_owner_object_alias`): This property is optional. You only need to set it if you
 * wish to store definitions for attributes that belong to multiple different MetaObjects in the same table. In that
 * case, the definition owner object is used to identify what MetaObject a custom attribute belongs to.
 * 
 * ## Type Models
 * 
 * Type models are special templates that simplify the creation of new custom attributes. They automatically configure
 * the properties of a custom attribute, meaning users won't have to know any technical details. When creating  a new
 * custom attribute they simply assign a type model to it, which then takes care of everything else.  They can choose
 * from all type models configured in the `type_models` property, as well as some basic default  type models, such as
 * "Date", "Time", "Text" and "Number".
 * 
 * You can extend these basic type models with your own. Simply add a new entry to the `type_models` property. 
 * Type models can inherit from any other type model. You can assign a parent by entering it in the `inherits`
 * property.  The type model will then use the property value of its parent, unless you defined a value for it. If you
 * do not specify a valid parent, your type model will inherit from a default configuration.
 * 
 * ## Categories
 * 
 * Since designers won't be able to know ahead of time what custom attributes will available at runtime, they will have
 * to rely on auto-generated widgets. To still give them some amount of control over what custom attributes will be
 * included in these auto-generated widgets, each custom attribute can be assigned to one or more categories.
 * 
 * By default, each custom attribute is already assigned to its type model as a category (for example "Time"). Beyond
 * that you can configure additional categories in the type model, all of which will be applied to all custom
 * attributes that use it. The most useful way however, is to manually edit what categories a custom attribute belongs
 * to, by changing the values stored in `attribute_category_alias`. While creating or editing a custom attribute, all
 * available categories will be displayed in a  dropdown multi-select. You can extend the available selection, by
 * adding more options to the property `general_categories`.
 * 
 * ## Setup
 * 
 * To enable custom attributes for your app, a decent amount of setup is required:
 * 
 * 1. Create a new table for your app, that has the following properties. It will be used to store the custom attribute definitions:
 * 
 *      - It must have all the default columns of your app.
 *      - It must have a matching column for: name (varchar), storage_key (varchar), type_model (varchar),categories (varchar), hint (varchar), required (tinyint) and optionally owner_object_id (binary(16)).
 * 
 * 2. Create a new MetaObject with matching attributes. If you have a column for `owner_object`, make sure that its attribute has a relation to `exface.Core.OBJECT`!
 * 
 * 3. Add a new `CustomAttributeDefinitionBehavior` and configure it to your needs.
 * 
 * 4. Create a simple page that allows you to create and edit your custom attribute definitions.
 * 
 * 5. For each object that want to have access to these definitions, add a CustomAttributeJsonBehavior and
 * configure it as needed. 
 * 
 * 6. Each of those objects needs an attribute, where the actual JSON data will be stored and of course the underlying
 * data source needs a matching column as well (usually varchar(max)).
 * 
 * 7. You can now use any custom attributes, that you have created via the page mentioned in step 4 (see "Using Custom
 * Attributes").
 * 
 * ## Using Custom Attributes
 * 
 * Once a custom attribute has been created and all the behaviors are set up properly, designers can work with them as
 * with any regular attribute. However, this approach is not recommended. Designers would need to know ahead of time
 * what custom attributes  they're working with, nullifying any benefits from having flexible attributes.
 * 
 * Instead, we recommend to make use of attribute groups, to auto-generate widgets with specific subsets of all
 * available custom  attributes. For widgets that support attribute groups (for example DataTable, Container and
 * Filter), you can define one or more attribute groups, which will be applied additively. You can mix and match them
 * with regular attribute aliases. 
 * 
 * Attribute groups work like filters for specific properties of an attribute. `~VISIBLE` for example selects all
 * attributes that are visible. You can chain multiple selectors with `~`, like `~VISIBLE~REQUIRED`, which would select
 * all attributes that are both  visible AND required. Lastly, you can negate a selector with `!`: `~!VISIBLE` would
 * select all attributes that are not visible.
 * 
 * `~CUSTOM` is a special selector just for custom attributes, which gives you even more control. It allows you to
 * define sub-selectors that filter for the categories a custom attribute belongs to. Use `:` to start your list of
 * categories and `,` to chain them. 
 * `~CUSTOM:Company A, Company B` would select all attributes that are custom AND belong to the categories "Company A"
 * AND "Company B". Category selectors can be negated as well: `~CUSTOM:!Company A` selects all attributes that are
 * custom AND do not belong to the category
 * "Company A".
 * 
 * For example, the table definition below will generate:
 * - The column "Item".
 * - A column for each attribute that is required AND not custom.
 * - A column for each attribute that is required AND custom AND not in the category "Company B" AND is 
 * in the category "Company C".
 * - A default filter for each attribute that is custom.
 * 
 * ```
 * 
 *  {
 *      "widget_type": "DataTable",
 *      "object_alias": "some.object.Alias",
 *      "columns": [
 *          {
 *               "attribute_group_alias": "~REQUIRED~!Custom"
 *          },
 *          {
 *               "attribute_alias": "Item"
 *          },
 *          {
 *               "attribute_group_alias": "~REQUIRED~CUSTOM:!Company B,Company C"
 *          }
 *      ],
 *      "filters": [
 *          {
 *              "attribute_group_alias": "~CUSTOM"
 *          }
 *      ]
 *  }
 * 
 * ```
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
 *      "attribute_owner_object_alias": "owner_alias",
 *      "attribute_category_alias": "categories",
 *      "general_categories": [
 *          "Company A",
 *          "Company B"
 *      ],
 *      "type_models": {
 *          "INHERITS_DEFAULT": {
 *              "inherits": "",
 *              "required": true,
 *              "copyable": false
 *          },
 *          "INHERITS_TIME": {
 *              "inherits": "Time",
 *              "hidden": true,
 *              "editable": false
 *          },
 *          "INHERITS_ABOVE": {
 *               "inherits": "INHERITS_TIME"
 *           }
 *      }
 * }
 * 
 * ```
 * 
 * ### Default Type Model
 * 
 * The default type model is defined in code and is shown here for demonstrative purposes only. 
 * You don't have to add it manually. Any type model without a valid parent will inherit from this model.
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
 * ## Code Usage
 * 
 * See `CustomAttributeJsonBehavior`:
 * 
 * ```
 * 
 *  $definitionBehavior = $definitionObject->getBehaviors()->findBehavior(CustomAttributeDefinitionBehavior::class);
 *  if(! $definitionBehavior instanceof CustomAttributeDefinitionBehavior) {
 *      $msg = 'Could not find behavior of type "' . CustomAttributeDefinitionBehavior::class . '" on MetaObject "' . $definitionObjectAlias . '"!'; throw new BehaviorRuntimeError( $this, $msg, null, null, $logBook);
 *  }
 * 
 *  $customAttributes = $definitionBehavior->addCustomAttributes(
 *      $this->getObject(),
 *      $this,
 *      $logBook);
 * 
 * ```
 * 
 * @author Georg Bieger
 */
class CustomAttributeDefinitionBehavior extends AbstractBehavior
{
    protected const KEY_DATA_TYPE = "data_type";
    protected const KEY_INHERITS_FROM = "inherits";
    protected const KEY_CATEGORIES = "categories";
    
    private array $typeModels = [];
    private ?string $attributeTypeModelAlias = null;
    private ?string $attributeCategoryAlias = null;
    private ?string $attributeNameAlias = null;
    private ?string $attributeStorageKeyAlias = null;
    private ?string $attributeHintAlias = null;
    private ?string $attributeRequiredAlias = null;
    private ?string $attributeOwnerObjectAlias = null;
    private bool $modelsInheritCategories = false;
    private array $generalCategories = [];

    protected function registerEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedConfigureEnums'],
            $this->getPriority());

        return $this;
    }

    protected function unregisterEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedConfigureEnums'],
        );

        return $this;
    }

    public function onLoadedConfigureEnums(OnMetaObjectLoadedEvent $event) : void
    {
        $object = $event->getObject();
        if(!$object->isExactly($this->getObject())) {
            return;
        }
        
        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));


        // TypeModel editor
        $keyValuePairs = [];
        foreach (array_keys($this->getTypeModelsAll()) as $modelKey) {
            $keyValuePairs[$modelKey] = $modelKey;
        }

        $typeModelEditorUxon = new UxonObject([
            "show_values" => false,
            "values" => $keyValuePairs
        ]);

        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), "exface.Core.GenericStringEnum");
        $typeModelAttribute = $object->getAttribute($this->getAttributeTypeModelAlias());
        $typeModelAttribute->setDataType($dataType);
        $typeModelAttribute->setCustomDataTypeUxon($typeModelEditorUxon);

        // Category selector
        $allCategories = $this->getGeneralCategories();
        $categorySelectorUxon = new UxonObject([
            "widget_type" => "InputSelect",
            "multi_select" => true,
            "selectable_options" => $allCategories
        ]);

        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), "exface.Core.String");
        $categoryAttribute = $object->getAttribute($this->getAttributeCategoryAlias());
        $categoryAttribute->setDataType($dataType);
        $categoryAttribute->setDefaultEditorUxon($categorySelectorUxon);

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));
    }

    /**
     * Load and add custom attributes to the target object.
     * 
     * @return MetaAttributeInterface[]
     */
    public function addCustomAttributes(MetaObjectInterface $targetObject, CustomAttributeLoaderInterface $attributeLoader, BehaviorLogBook $logBook) : array
    {
        if(empty($this->getTypeModelsAll())) {
            throw new BehaviorRuntimeError($this, 'Could not load custom attributes: No type models found in behavior on object "' . $this->getObject()->getAliasWithNamespace() . '"!', null, null, $logBook);
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
            $requiredAlias = $this->getAttributeRequiredAlias(),
            $categoryAlias = $this->getAttributeCategoryAlias()
        ]);
        
        $targetObjectId = $targetObject->getId();
        if($ownerIdAlias = $this->getAttributeOwnerObjectAlias()) {
            $logBook->addLine('Loading only definitions that match "' . $targetObjectId . '" in "' . $ownerIdAlias . '" of "' . $this->getObject()->getAliasWithNamespace() . '".');
            $attributeDefinitionsSheet->getFilters()->addConditionFromString($ownerIdAlias, $targetObjectId);
        } else {
            $logBook->addLine('No value was set for "attribute_owner_object_alias". Loading ALL definitions from "' . $this->getObject()->getAliasWithNamespace() . '".');
        }
        
        $attributeDefinitionsSheet->dataRead();
        
        $logBook->addLine('Attribute definitions loaded successfully.');
        $logBook->addDataSheet('Attribute Definitions', $attributeDefinitionsSheet);
        $logBook->addIndent(-1);
        $logBook->addLine('Adding custom attributes to "' . $targetObjectId . '"...');
        $logBook->addIndent(1);
        
        foreach ($attributeDefinitionsSheet->getRows() as $definitionRow) {
            $typeKey = $definitionRow[$modelAlias];
            $name = $definitionRow[$nameAlias];

            if(! $typeModel = $this->getTypeModel($typeKey)) {
                throw new BehaviorRuntimeError($this, 'Error while loading custom attribute "' . $name . '": Type model "' . $typeKey . '" not found! Check "' . $this->getAliasWithNamespace() . '" on object "' . $this->getObject()->getAliasWithNamespace() . '" for available type models.', null , null, $logBook);
            }
            
            $storageKey = $definitionRow[$keyAlias];
            $alias = $attributeLoader->customAttributeStorageKeyToAlias($storageKey);
            $address = $attributeLoader->getCustomAttributeDataAddress($storageKey);
            $attr = MetaObjectFactory::addAttributeTemporary(
                $targetObject, 
                new CustomAttribute($targetObject, $attributeLoader),
                $name, 
                $alias, 
                $address, 
                $typeModel[self::KEY_DATA_TYPE]);
            
            // Remove properties from the template that should not be applied to the attribute.
            unset($typeModel[self::KEY_DATA_TYPE]);
            unset($typeModel[self::KEY_INHERITS_FROM]);
            unset($typeModel[$nameAlias]);
            // Apply the template.
            $attr->importUxonObject(new UxonObject($typeModel));
            // Set values that were not stored in the template.
            $attr->setShortDescription($definitionRow[$hintAlias]);
            $attr->setRequired($definitionRow[$requiredAlias]);
            
            if($attr instanceof CustomAttribute) {
                $delimiter = $this->getObject()->getAttribute($categoryAlias)->getValueListDelimiter();
                $categories = explode($delimiter, $definitionRow[$categoryAlias]);
                $attr->addCategories($categories);
            }
            
            $attrs[] = $attr;
            $logBook->addLine('Added "' . $attr->getAlias() . '" with data address "' . $attr->getDataAddress() . '" of type "' . $typeKey . '(' . $attr->getDataType()->getAliasWithNamespace() . ')".');
        }
        //$this->registerWidgetModifications($attrs);
        
        $logBook->addIndent(-1);
        
        return $attrs;
    }

    protected function registerWidgetModifications(array $attributes) : CustomAttributeDefinitionBehavior
    {
        // Only register behaviors once!
        /*if ($this->behaviors !== null) {
            return $this;
        } else {
            $this->behaviors = [];
        }*/
        
        $columns = [];
        foreach ($attributes as $attr) {
            $colUxon = [
                'attribute_alias' => $attr->getAlias(),
            ];
            $columns[] = $colUxon;
        }

        // Create a behavior configuration notify on updates changing the state attribute
        $uxon = new UxonObject([
            "add_columns" => $columns,
        ]);

        // Add the on-update behavior
        $behaviorOnUpdate = BehaviorFactory::createFromUxon($this->getObject(), WidgetModifyingBehavior::class, $uxon, $this->getApp()->getSelector());
        $this->getObject()->getBehaviors()->add($behaviorOnUpdate);

        return $this;
    }

    /**
     * Define what categories a custom attribute can be assigned to.
     *
     * This list will be extended with all categories found in the `type_models` property.
     *
     * @uxon-property general_categories
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param UxonObject $generalCategories
     * @return $this
     */
    public function setGeneralCategories(UxonObject $generalCategories) : CustomAttributeDefinitionBehavior
    {
        $this->generalCategories = $generalCategories->toArray();
        return $this;
    }
    
    /**
     * Get a list of all categories defined for this behavior.
     * 
     * @return array
     */
    public function getGeneralCategories() : array
    {
        return $this->generalCategories;
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
     * When creating a new custom attribute, users must assign a type model to it. They can choose 
     * from all type models configured in the `type_models` property, as well as some basic default 
     * type models, such as "DATE", "TIME", "TEXT" and "NUMBER".
     * 
     * @uxon-property type_models
     * @uxon-type \exface\core\CommonLogic\Model\CustomAttribute[]
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
        
        // Add the type model key to the list of categories that attributes derived from this type model belong to.
        if(empty($model[self::KEY_CATEGORIES]) || !in_array($key, $model[self::KEY_CATEGORIES],true)) {
            $model[self::KEY_CATEGORIES][] = $key;
        }
        
        // If the model has no explicit parent OR that parent is invalid, inherit from base.
        $parentKey = $model[self::KEY_INHERITS_FROM];
        if(!$parentKey || $parentKey === $key || !key_exists($parentKey, $allModels)) {
            return [$key => $this->mergeTypeModels($this->getBaseTypeModel(), $model)];
        }

        // If the model has an explicit parent that has already been resolved,
        // we can inherit from it directly.
        if(key_exists($parentKey, $resolvedModels)) {
            return [$key => $this->mergeTypeModels($resolvedModels[$parentKey], $model)];
        }
        
        // Otherwise, we have to resolve the parent model recursively.
        // We add ourselves to the resolved list to prevent loops. 
        // This is safe, because each type model has a single root.
        $resolvedModels[$key] = $this->mergeTypeModels($this->getBaseTypeModel(), $model);
        $result = $this->resolveInheritanceHierarchy(
            $parentKey,
            $allModels[$parentKey],
            $allModels,
            $resolvedModels
        );
        // Now we merge with our resolved parent.
        $result[$key] = $this->mergeTypeModels($result[$parentKey], $model);
        
        return $result;
    }

    /**
     * Merge two type models with one another, as per `array_merge()`.
     * 
     * @param array $left
     * @param array $right
     * @return array
     */
    protected function mergeTypeModels(array $left, array $right) : array
    {
        // Merge categories.
        if($this->getModelsInheritCategories()) {
            $categoriesLeft = $left[self::KEY_CATEGORIES];
            $categoriesRight = $right[self::KEY_CATEGORIES];
            
            if (empty($categoriesRight)) {
                $right[self::KEY_CATEGORIES] = $categoriesLeft;
            } else if (!empty($categoriesLeft)) {
                $right[self::KEY_CATEGORIES] = array_unique(array_merge(
                    $categoriesLeft, $categoriesRight
                ));
            }
        } 
        
        return array_merge($left, $right);
    }

    /**
     * @return string
     */
    public function getAttributeTypeModelAlias() : string
    {
        if(! $this->attributeTypeModelAlias) {
            throw new BehaviorConfigurationError($this, $this->getMissingPropertyMessage("attribute_type_model_alias"));
        }
        
        return $this->attributeTypeModelAlias;
    }

    /**
     * The attribute alias of the definition object that holds the type model key. 
     * 
     * Type models simplify the configuration of custom attributes, by assigning meaningful defaults to most attribute
     * properties.
     * 
     * @uxon-property attribute_type_model_alias
     * @uxon-type metamodel:attribute
     * @uxon-template type_model
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
     * The attribute alias of the definition object that holds the categories of a custom attribute.
     * 
     * Categories can be used to limit which custom attributes will be included in automatically generated widgets.
     * 
     * @uxon-property attribute_category_alias
     * @uxon-type metamodel:attribute
     * @uxon-template attribute_category_alias
     * 
     * @param string $alias
     * @return $this
     */
    public function setAttributeCategoryAlias(string $alias) : CustomAttributeDefinitionBehavior
    {
        $this->attributeCategoryAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    public function getAttributeCategoryAlias() : string
    {
        if(! $this->attributeCategoryAlias) {
            throw new BehaviorConfigurationError($this, $this->getMissingPropertyMessage("attribute_category_alias"));
        }

        return $this->attributeCategoryAlias;
    }

    /**
     * @return string
     */
    public function getAttributeNameAlias() : string
    {
        if(! $this->attributeNameAlias) {
            throw new BehaviorConfigurationError($this, $this->getMissingPropertyMessage("attribute_name_alias"));
        }
        
        return $this->attributeNameAlias;
    }

    /**
     * The attribute of the definition object that holds the name of a custom attribute.
     * 
     * @uxon-property attribute_name_alias
     * @uxon-type metamodel:attribute
     * @uxon-template name
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
            throw new BehaviorConfigurationError($this, $this->getMissingPropertyMessage("attribute_storage_key_alias"));
        }

        return $this->attributeStorageKeyAlias;
    }

    /**
     * The attribute alias of the definition object that holds the storage key of a custom attribute. 
     * 
     * The storage key is used to generate the data address and the technical alias of the custom attribute.
     *
     * @uxon-property attribute_storage_key_alias
     * @uxon-type metamodel:attribute
     * @uxon-template storage_key
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
            throw new BehaviorConfigurationError($this, $this->getMissingPropertyMessage("attribute_hint_alias"));
        }
        
        return $this->attributeHintAlias;
    }

    /**
     * The attribute alias of the definition object that holds the short description of a custom attribute.
     * 
     * The short description is used for tooltips and info panels, when working with the custom attribute.
     * 
     * @uxon-property attribute_hint_alias
     * @uxon-type metamodel:attribute
     * @uxon-template hint
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
            throw new BehaviorConfigurationError($this, $this->getMissingPropertyMessage("attribute_required_alias"));
        }
        
        return $this->attributeRequiredAlias;
    }

    /**
     * The attribute of the definition object that determines whether a custom attribute is required.
     * 
     * @uxon-property attribute_required_alias
     * @uxon-type metamodel:attribute
     * @uxon-template required
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
    public function getAttributeOwnerObjectAlias() : ?string
    {
        return $this->attributeOwnerObjectAlias;
    }

    /**
     * (Optional) Relation to the meta object, that the attribute will be attached to.
     * 
     * You only need to set a value for this property, if you are storing custom attribute
     * definitions for more than one MetaObject in the same table.
     * 
     * @uxon-property attribute_owner_object_alias
     * @uxon-type metamodel:relation
     * 
     * @param string|null $alias
     * @return $this
     */
    public function setAttributeOwnerObjectAlias(?string $alias) : static
    {
        $this->attributeOwnerObjectAlias = $alias;
        return $this;
    }

    /**
     * If TRUE, type models will inherit categories from their parents (default is FALSE).
     * 
     * @uxon-property models_inherit_categories
     * @uxon-type boolean
     * @uxon-template false
     * 
     * @param bool $value
     * @return $this
     */
    public function setModelsInheritCategories(bool $value) : CustomAttributeDefinitionBehavior
    {
        $this->modelsInheritCategories = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getModelsInheritCategories() : bool
    {
        return $this->modelsInheritCategories;
    }

    /**
     * An associative array with `[TypeModelKey => []]` that contains some basic type model definitions
     * 
     * @return string[][]
     */
    protected function getDefaultTypeModels() : array
    {
        return [
            'Date' => [
                self::KEY_DATA_TYPE => "exface.Core.DateTime",
                "default_editor_uxon" => ["widget_type" => "InputDateTime"]
            ],
            'Text' => [
                self::KEY_DATA_TYPE => "exface.Core.String",
            ],
            'Number' => [
                self::KEY_DATA_TYPE => "exface.Core.Number",
            ],
            'Time' => [
                self::KEY_DATA_TYPE => "exface.Core.Time",
            ],
        ];
    }
    
    protected function getMissingPropertyMessage(string $propertyName) : string
    {
        return 'Missing value for property "' . $propertyName . '" in behavior on object "' .  $this->getObject()->getAliasWithNamespace() . '"!';
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
            "categories" => []
        ];
    }
}