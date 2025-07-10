<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\Behaviors\CustomAttributesDefinition;
use exface\Core\CommonLogic\Model\CustomAttribute;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\MetamodelAliasDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\StringEnumDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Events\Widget\OnUiActionWidgetInitEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\Model\MetaAttributeGroupNotFoundError;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Makes this object define custom attributes to be attached to another object in addition to its regular attributes. 
 * 
 * For example, if you have a list of products and you want your users to define additional attributes for every 
 * product category, you will need a list of possible attributes per category and a place to store their values. 
 * This will require multiple behaviors working together:
 * 
 * - An **attribute definition behavior** (this one) needs to be attached to the meta object representing the 
 * list of possible attributes
 * - An **attribute storage behavior** needs to be attached to the object, that will receive the attributes - i.e. 
 * to the product-object in our example. 
 * 
 * Here is how the definition-behavior might look like:
 * 
 * ```
 * {
 * 	"name_attribute": "NAME", 
 * 	"alias_attribute": "ALIAS", 
 * 	"required_attribute": "REQUIRED_FLAG",
 *  "type_attribute": "TYPE"
 * }
 * 
 * ```
 * 
 * It will generate an attribute from every data row of the definition-object. The attributes will get their names 
 * from the `NAME` attribute of the definition-object, the alias from `ALIAS` and will be required if the `REQUIRED_FLAG` 
 * checkbox is set. The `TYPE` widget of the editor widget for attribute definitions will be automatically turned into
 * an `InputSelect` letting the user pick one of the standard types: text, date, number, etc. - see dedicated chapter below.
 * 
 * Here is another example: in a quality assurance app, you will attach every QA report to on or even multiple things like
 * product properties, packaging, manufacturing process, etc. You could create a custom attribute for every type of allocation 
 * for the report-object, so that in a list of reports users will immediately see, what it is related to. These will be custom 
 * attributes too, but they will all look similar and will not be explicitly defined as such by users - instead, you just 
 * attach a definition behavior to the existing allocation type object and tell we system to create attributes from it. 
 * 
 * ```
 * {
 * 	"name_attribute": "NAME"
 * }
 * 
 * ```
 * 
 * As you can see, we just generate properties of attributes from available data - there is much less configuration. 
 * 
 * ## Attribute model
 * 
 * In case you need users to add new attributes explicitly (like in our product-example) you need to decide, which 
 * aspects of the attributes they can control and give your definition-object corresponding attributes. 
 * 
 * Here is what you can let users define:
 * 
 * - **Name** (`name_attribute`): The display name of the custom attribute.
 * - **Alias** (`alias_attribute`): The attribute alias for the custom attribute.
 * - **Data address** (`data_address_attribute`): The data address is used to generate the data address and the technical alias  of the custom attribute. Make sure it matches whatever storage behavior you are using. For example, if the data of the attributes is to be loaded from JSON via `CustomAttributesJsonBehavior`, the data address should be a valid JSON path. 
 * - **Type** of the attribute (`type_attribute`): This is more than just a data type - it is a preconfigured model
 * of the attribute, that could even include relations, data address properties and other things. You can provide a
 * set of valid type model in this behavior via `type_models`. 
 * - **Attribute groups** (`groups_attribute`): One of the attributes of this object can be used to store a delimited
 * list of attribute groups, that should be assigned to the generated custom attribute belongs to. Attribute groups give
 * designers more fine-grained control over which custom attributes will be included in automatically generated widgets
 * (see "Attribute groups").
 * - **Hint** (`hint_attribute`): The short description stored in this attribute is used for tooltips and info panels, 
 * when working with an attribute.
 * - **Required** (`required_attribute`): Determines, whether a custom attribute will be required in editors.
 * - **Owner Object** (`relation_to_owner_object`): This property is optional. You only need to set it if you
 * wish to store definitions for attributes that belong to multiple different MetaObjects in the same table. In that
 * case, the definition owner object is used to identify what MetaObject a custom attribute belongs to.
 * 
 * ## Types models
 * 
 * If you need to let users pick from different attribute types, you can define multiple so-called "type models".
 * There are built-in type models for the most important data types: "Date", "Time", "Text" and "Number". 
 * 
 * However, type models are more than just data types - they are preconfigured models for the entire attribute. You can 
 * can set any attribute property in the type model: default display or editor widgets, relations configurations,
 * readable/writable flags - everything! 
 * 
 * When a user creates a custom attribute and you have `type_attribute` set in the behavior config, the user will
 * need to pick a type from an automatically generated list. Users cannot control all the mighty attribute configuration
 * explicitly - they can only pick your preconfigured types. This makes it much easier to create custom attributes as
 * there is not much to know about how the workbench works in the background.
 * 
 * You can define as many type models as many as you like. Type models can even use use inheritance: you can specify 
 * another type model in the `inherits` property and change it selectively. By default all type models inherit from
 * the `attribute_defaults`.
 * 
 * ### Default attribute model
 * 
 * There is always a default attribute model. You can modify it using `attribute_defaults`. All type models will inherit
 * from it.
 * 
 * If you have no `type_attribute` in your config at all, all attributes will have the same default model. Most
 * storage-behaviors provide their own default models. For example, the `CustomAttributesJsonBehavior` will have 
 * writable attributes while `CustomAttribtuesLookupBehavior` will have non-writable ones. 
 * 
 * Only change the default model if you know what you are doing. Here is what you can change: 
 * 
 * ```
 * {
 *  "attribute_defaults" : {
 *      "data_type": "exface.Core.String",
 *      "readable": true,
 *      "writable": false,
 *      "copyable": false,
 *      "editable": false,
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
 * }
 * 
 * ## Attribute groups
 * 
 * Since designers won't be able to know ahead of time what custom attributes will available at runtime, they will have
 * to rely on auto-generated widgets. To still give them some amount of control over what custom attributes will be
 * included in these auto-generated widgets, each custom attribute can be assigned to one or more groups.
 * 
 * By default, each custom attribute is already assigned to its type model as a group (for example "Time"). Beyond
 * that you can configure additional groups in the type model, all of which will be applied to all custom
 * attributes that use it. The most useful way however, is to manually edit what groups a custom attribute belongs
 * to, by changing the values stored in `groups_attribute`. While creating or editing a custom attribute, all
 * available groups will be displayed in a  dropdown multi-select.
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
 * define sub-selectors that filter for the groups a custom attribute belongs to. Use `:` to start your list of
 * groups and `,` to chain them. 
 * `~CUSTOM:Company A, Company B` would select all attributes that are custom AND belong to the groups "Company A"
 * AND "Company B". Group selectors can be negated as well: `~CUSTOM:!Company A` selects all attributes that are
 * custom AND do not belong to the group
 * "Company A".
 * 
 * For example, the table definition below will generate:
 * - The column "Item".
 * - A column for each attribute that is required AND not custom.
 * - A column for each attribute that is required AND custom AND not in the group "Company B" AND is 
 * in the group "Company C".
 * - A default filter for each attribute that is custom.
 * 
 * ```
 *  {
 *      "widget_type": "DataTable",
 *      "object_alias": "some.object.Alias",
 *      "filters": [
 *          {"attribute_group_alias": "~CUSTOM"}
 *      ],
 *      "columns": [
 *          {"attribute_group_alias": "~REQUIRED~!CUSTOM"},
 *          {"attribute_alias": "MYATTR"},
 *          {"attribute_group_alias": "~my.App.IMPORTANT_CUSTOM_ATTRS"}
 *      ]
 *  }
 * 
 * ```
 * 
 * @author Georg Bieger
 */
class CustomAttributeDefinitionBehavior extends AbstractBehavior
{
    protected const KEY_DATA_TYPE = "data_type";
    protected const KEY_INHERITS_FROM = "inherits";
    protected const KEY_GROUPS = "groups";

    const ALIAS_GENERATOR_CAMEL = 'CamelCase';
    const ALIAS_GENERATOR_UPPER_CASE = 'UPPER_CASE';
    const ALIAS_GENERATOR_LOWER_CASE = 'lower_case';
    const ALIAS_GENERATOR_UNDERSCORE = 'Under_Score';

    const PLACEHOLDER_ALIAS = '~custom_attribute_alias';
    const PLACEHOLDER_NAME = '~custom_attribute_name';
    
    private array $typeModels = [];
    private ?string $attributeTypeModelAlias = null;
    private ?string $attributeGroupsAlias = null;
    private ?string $attributeNameAlias = null;
    private ?string $attributeStorageKeyAlias = null;
    private ?string $attributeHintAlias = null;
    private ?string $attributeRequiredAlias = null;
    private ?string $attributeOwnerObjectAlias = null;
    private ?string $attributeAliasAlias = null;
    private ?string $aliasGeneratorType = null;
    private bool $modelsInheritGroups = false;
    private ?UxonObject $filtersUxon = null;
    private ?UxonObject $sortersUxon = null;
    private ?array $ownerObjects = null;
    
    private array $attributeDefaults = [
        // DATATYPE
        self::KEY_DATA_TYPE => "exface.Core.String"
    ];

    /**
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(
            OnUiActionWidgetInitEvent::getEventName(),
            [$this,'onEditorRenderedConfigureEnums'],
            $this->getPriority()
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onEditorRenderedConfigureEnums']
        );

        return $this;
    }

    /**
     * TODO Modifying widgets OnUiActionWidgetInitEvent has a serious drawback: 
     * @param \exface\Core\Events\Model\OnMetaObjectLoadedEvent $event
     * @return void
     */
    public function onEditorRenderedConfigureEnums(OnUiActionWidgetInitEvent $event) : void
    {
        $object = $event->getObject();
        if(! $object->isExactly($this->getObject())) {
            return;
        }
        
        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));

        // TypeModel editor
        if (null !== $typeAlias = $this->getTypeAttributeAlias()) {
            $keyValuePairs = [];
            foreach (array_keys($this->getTypeModelsAll()) as $modelKey) {
                $keyValuePairs[$modelKey] = $modelKey;
            }

            $typeModelEditorUxon = new UxonObject([
                "show_values" => false,
                "values" => $keyValuePairs
            ]);

            $dataType = DataTypeFactory::createFromString($this->getWorkbench(), StringEnumDataType::class);
            $typeModelAttribute = $object->getAttribute($typeAlias);
            $typeModelAttribute->setDataType($dataType);
            $typeModelAttribute->setCustomDataTypeUxon($typeModelEditorUxon);
            $typeModelAttribute->setDefaultEditorUxon(new UxonObject([
                'widget_type' => 'InputSelect'
            ]));
        }

        // AttributeGroupSelector
        if (null !== $groupsAlias = $this->getAttributeGroupsAttributeAlias()) {
            $allGroups = $this->getAttributeGroups();
            $groupSelectorUxon = new UxonObject([
                "widget_type" => "InputSelect",
                "multi_select" => true,
                "selectable_options" => $allGroups
            ]);

            $dataType = DataTypeFactory::createFromString($this->getWorkbench(), StringDataType::class);
            $groupsAttribute = $object->getAttribute($groupsAlias);
            $groupsAttribute->setDataType($dataType);
            $groupsAttribute->setDefaultEditorUxon($groupSelectorUxon);
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));
    }

    /**
     * 
     * @return MetaObjectInterface[]
     */
    protected function findOwnerObjects() : array
    {
        if ($this->ownerObjects === null) {
            $behaviorsObj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.OBJECT_BEHAVIORS');
            $definitionAttribute = MetaObjectFactory::addAttributeTemporary($behaviorsObj, 'DEFINITION_OBJECT_ALIAS', 'Definition object alias', 'CONFIG_UXON::$.attributes_definition.object_alias');
            $definitionAttribute->setFilterable(true);
            $ds = DataSheetFactory::createFromObject($behaviorsObj);
            $objectUidCol = $ds->getColumns()->addFromExpression('OBJECT');
            $ds->getFilters()->addConditionFromAttribute($definitionAttribute, $this->getObject()->getAliasWithNamespace(), ComparatorDataType::EQUALS);
            $ds->getFilters()->addConditionFromString('BEHAVIOR', 'CustomAttribute', ComparatorDataType::IS);
            $ds->dataRead();
            
            $objects = [];
            foreach ($objectUidCol->getValues() as $uid) {
                $objects[] = MetaObjectFactory::createFromUid($this->getWorkbench(), $uid);
            }
            $this->ownerObjects = $objects;
        }
        return $this->ownerObjects;
    }

    /**
     * Load and add custom attributes to the target object.
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $targetObject
     * @param \exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook $logBook
     * @throws \exface\Core\Exceptions\Behaviors\BehaviorRuntimeError
     * @return array<CustomAttribute|MetaAttributeInterface>
     */
    public function addAttributesToObject(MetaObjectInterface $targetObject, CustomAttributesDefinition $definition, BehaviorLogBook $logBook) : array
    {        
        $attrs = [];
        
        $logBook->addLine('Loading attribute definitions...');
        $logBook->addIndent(1);
        
        if (null === $tplUxon = $definition->getDataSheetTemplateUxon()) {
            $attributeDefinitionsSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getObject());
        } else {
            $attributeDefinitionsSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $tplUxon, $this->getObject());
        }

        $attributeDefinitionsSheet->getColumns()->addMultiple([
            $nameAlias = $this->getNameAttributeAlias()
        ]);
        if (null !== $aliasAlias = $this->getAliasAttributeAlias()) {
            $attributeDefinitionsSheet->getColumns()->addFromExpression($aliasAlias);
        }
        if (null !== $typeAlias = $this->getTypeAttributeAlias()) {
            $attributeDefinitionsSheet->getColumns()->addFromExpression($typeAlias);
        }
        if (null !== $storageKeyAlias = $this->getDataAddressAttributeAlias()) {
            $attributeDefinitionsSheet->getColumns()->addFromExpression($storageKeyAlias);
        }
        if (null !== $hintAlias = $this->getHintAttributeAlias()) {
            $attributeDefinitionsSheet->getColumns()->addFromExpression($hintAlias);
        }
        if (null !== $requiredAlias = $this->getRequiredAttributeAlias()) {
            $attributeDefinitionsSheet->getColumns()->addFromExpression($requiredAlias);
        }
        if (null !== $groupsAlias = $this->getAttributeGroupsAttributeAlias()) {
            $attributeDefinitionsSheet->getColumns()->addFromExpression($groupsAlias);
        }
        if (null !== $filtersUxon = $this->getFiltersUxon()) {
            $conditionGroup = ConditionGroupFactory::createFromUxon($this->getWorkbench(), $filtersUxon, $this->getObject());
            if(empty($attributeDefinitionsSheet->getFilters())) {
                $attributeDefinitionsSheet->setFilters($conditionGroup);
            } else {
                $attributeDefinitionsSheet->getFilters()->addNestedGroup($conditionGroup);
            }
        }
        if (null !== $sortersUxon = $this->getSortersUxon()) {
            $attributeDefinitionsSheet->getSorters()->importUxonObject($sortersUxon);
        }

        if($typeAlias !== null && empty($this->getTypeModelsAll())) {
            throw new BehaviorRuntimeError($this, 'Could not load custom attributes: No type models found in behavior on object "' . $this->getObject()->getAliasWithNamespace() . '"!', null, null, $logBook);
        }
        
        if($ownerRelAlias = $this->getRelationPathToOwnerObject()) {
            $ownerRel = $attributeDefinitionsSheet->getMetaObject()->getRelation($ownerRelAlias);
            if (! $ownerRel->getRightObject()->is('exface.Core.OBJECT')) {
                throw new BehaviorRuntimeError($this, 'Cannot use relation "`"' . $ownerRelAlias . '" as object-filter for custom attributes because it does not point to the core object "exface.Core.OBJECT"!', null, null, $logBook);
            }
            switch ($ownerRel->getRightKeyAttribute()->getAlias()) {
                case 'ALIAS_WITH_NS':
                    $targetFilterVal = $targetObject->getAliasWithNamespace();
                    break;
                case 'UID':
                    $targetFilterVal = $targetObject->getId();
                    break;
                default:
                    throw new BehaviorRuntimeError($this, 'Cannot use relation "`"' . $ownerRelAlias . '" as object-filter for custom attributes because it neither points to a meta object UID nor to its namespaced alias', null, null, $logBook);
            }
            $attributeDefinitionsSheet->getFilters()->addConditionFromString($ownerRelAlias, $targetFilterVal);
            $logBook->addLine('Loading attribute definitions that match `' . $attributeDefinitionsSheet->getFilters()->__toString() . '` from "' . $this->getObject()->getAliasWithNamespace() . '".');
        } else {
            $logBook->addLine('Property `relation_to_owner_object` not set. Loading ALL definitions from "' . $this->getObject()->getAliasWithNamespace() . '".');
        }

        $attrDefaults = $this->getAttributeDefaults($definition);
        $attrDefaultsJson = JsonDataType::encodeJson($attrDefaults);
        $typePhs = StringDataType::findPlaceholders($attrDefaultsJson);
        $typeModelsJson = JsonDataType::encodeJson($this->getTypeModelsAll());
        $typePhs = array_merge($typePhs, StringDataType::findPlaceholders($typeModelsJson));
        foreach ($typePhs as $ph) {
            switch (true) {
                case $attributeDefinitionsSheet->getColumns()->getByExpression($ph):
                    continue 2;
                case $attributeDefinitionsSheet->getMetaObject()->hasAttribute($ph):
                case Expression::detectFormula($ph):
                    $attributeDefinitionsSheet->getColumns()->addFromExpression($ph);
                    break;
            }
        }

        try {
            $attributeDefinitionsSheet->dataRead();
        } catch (\Throwable $e) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attribute definitions from ' . $this->getObject()->__toString() . '. ' . $e->getMessage(), null, null, $logBook);
        }
        
        $logBook->addLine('Found **' . $attributeDefinitionsSheet->countRows() . '** attributes');
        $logBook->addDataSheet('Attribute Definitions', $attributeDefinitionsSheet);
        $logBook->addIndent(-1);
        $logBook->addLine('Adding custom attributes to ' . $targetObject->__toString() . '...');
        $logBook->addIndent(1);

        foreach ($attributeDefinitionsSheet->getRows() as $definitionRow) {
            $name = $definitionRow[$nameAlias];
            $alias = null;
            
            if ($aliasAlias !== null) {
                $alias = $definitionRow[$aliasAlias];
            } 
            if ($storageKeyAlias !== null) {
                $address = $definitionRow[$storageKeyAlias];
                if ($alias === null) {
                    $alias = $address;
                }
            } else {
                $address = '';
            }
            if ($alias === null) {
                $alias = $this->getAliasFromName($name);
            }
            
            // Instantiate a new custom attribute
            $attr = new CustomAttribute($targetObject, $name, $alias, $definition->getStorageBehavior());
            $attr->setDataAddress($address);

            if ($this->hasAttributeTypeModels() === true) {
                $typeKey = $definitionRow[$typeAlias];
                if (! $typeModel = $this->getTypeModel($typeKey)) {
                    throw new BehaviorRuntimeError($this, 'Error while loading custom attribute "' . $name . '": Type model "' . $typeKey . '" not found! Check "' . $this->getAliasWithNamespace() . '" on object "' . $this->getObject()->getAliasWithNamespace() . '" for available type models.', null, null, $logBook);
                }
                $typeModel = array_merge($attrDefaults, $typeModel);
            } else {
                $typeKey = null;
                $typeModel = $attrDefaults;
            }


            // Remove properties from the template that should not be applied to the attribute.
            unset($typeModel[self::KEY_INHERITS_FROM]);
            unset($typeModel[$nameAlias]);

            // Apply the template.
            if (! empty($typeModel)) {
                // See if there are any placeholders and replace them. Need to replace them
                // every time because the replacement values are different for every attribute
                if (! empty($typePhs)) {
                    $typeModelStr = JsonDataType::encodeJson($typeModel);
                    $typePhVals = $definitionRow;
                    $typePhVals[self::PLACEHOLDER_ALIAS] = $attr->getAlias();
                    $typePhVals[self::PLACEHOLDER_NAME] = $attr->getName();
                    $typeModelStr = StringDataType::replacePlaceholders($typeModelStr, $typePhVals);
                    $typeModel = JsonDataType::decodeJson($typeModelStr);
                }
                $attr->importUxonObject(new UxonObject($typeModel));
            }

            // Set values that were not stored in the template.
            if ($hintAlias !== null) {
                $attr->setShortDescription($definitionRow[$hintAlias]);
            }
            if ($requiredAlias !== null) {
                $attr->setRequired($definitionRow[$requiredAlias]);
            }
            // Add attribute groups
            if($groupsAlias !== null) {
                $delimiter = $this->getObject()->getAttribute($groupsAlias)->getValueListDelimiter();
                $groups = explode($delimiter, $definitionRow[$groupsAlias] ?? '');
                foreach ($groups as $groupAlias) {
                    try {
                        $targetObject->getAttributeGroup($groupAlias)->add($attr);
                    } catch (MetaAttributeGroupNotFoundError $e) {
                        // Ignore missing attribute groups
                    }
                }
            }
            
            // Attach the attribute to the object
            $targetObject->addAttribute($attr);
            $attrs[] = $attr;
            $logBook->addLine('Added "' . $attr->getAlias() . '" with data address "' . $attr->getDataAddress() . '" of type "' . $typeKey . '(' . $attr->getDataType()->getAliasWithNamespace() . ')".');
        }

        $logBook->addIndent(-1);

        return $attrs;
    }

    /**
     * 
     * @param MetaAttributeInterface[] $attributes
     * @return CustomAttributeDefinitionBehavior
     */
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
     * 
     * @return string[]
     */
    protected function getAttributeGroups() : array
    {
        $aliases = [];
        foreach ($this->findOwnerObjects() as $object) {
            foreach ($object->getAttributeGroups() as $grp) {
                $aliases[] = $grp->getAliasWithNamespace();
            }
        }
        $aliases = array_unique($aliases);
        // If array_unique() produces gaps, the array is recognized as an asscociative array
        // from this point on. This makes trouble: for example, the InputSelect widget
        // produced for the default editor interprets it as key-value pairs and not
        // as a simple list of values. To avoid this, reindex the array here.
        $aliases = array_values($aliases);
        return $aliases;
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
     * You can set set any UXON properties of an attribute here - including complex ones
     * like `data_type`, `relation`, etc. Use placeholders to include data of the
     * definition-object. This way, you are free to make any parts of the resulting
     * custom attributes controllable from the definition - not only those explicitly
     * supported by `xxx_attribute` properties of this behavior.
     *
     * The following placeholders can be used in the attribute model:
     *
     * - `[#~custom_attribute_alias#]`
     * - `[#~custom_attribute_name#]`
     * - `[#<attribute_alias_of_definition_object>#]`
     * - `[#=Formula()#]`
     * 
     * @uxon-property type_models
     * @uxon-type \exface\core\CommonLogic\Model\CustomAttribute[]
     * @uxon-template {"":{"inherits":"","data_type":"exface.Core.String","readable":true,"writable":true,"copyable":true,"editable":true,"required":false,"hidden":false,"sortable":true,"filterable":true,"aggregatable":true,"default_aggregate_function":"","default_sorter_dir":"ASC","value_list_delimiter":",","default_display_order":""}}
     *
     * @uxon-placeholder [#~custom_attribute_name#]
     * @uxon-placeholder [#~custom_attribute_alias#]
     * @uxon-placeholder [#<metamodel:attribute>#]
     *
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setTypeModels(UxonObject $uxon) : CustomAttributeDefinitionBehavior
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
     * Loops, and empty or invalid parent keys resolve to `array_merge($model, $this->getAttributeDefaults())`.
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
        
        // If the model has no explicit parent OR that parent is invalid, inherit from base.
        $parentKey = $model[self::KEY_INHERITS_FROM];
        if(!$parentKey || $parentKey === $key || !key_exists($parentKey, $allModels)) {
            return [$key => $this->mergeTypeModels($this->getAttributeDefaults(), $model)];
        }

        // If the model has an explicit parent that has already been resolved,
        // we can inherit from it directly.
        if(key_exists($parentKey, $resolvedModels)) {
            return [$key => $this->mergeTypeModels($resolvedModels[$parentKey], $model)];
        }
        
        // Otherwise, we have to resolve the parent model recursively.
        // We add ourselves to the resolved list to prevent loops. 
        // This is safe, because each type model has a single root.
        $resolvedModels[$key] = $this->mergeTypeModels($this->getAttributeDefaults(), $model);
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
        // Merge attribute groups.
        if($this->getModelsInheritGroups()) {
            $groupsLeft = $left[self::KEY_GROUPS];
            $groupsRight = $right[self::KEY_GROUPS];
            
            if (empty($groupsRight)) {
                $right[self::KEY_GROUPS] = $groupsLeft;
            } else if (!empty($groupsLeft)) {
                $right[self::KEY_GROUPS] = array_unique(array_merge(
                    $groupsLeft, $groupsRight
                ));
            }
        } 
        
        return array_merge($left, $right);
    }

    /**
     * @return string
     */
    protected function getTypeAttributeAlias() : ?string
    {        
        return $this->attributeTypeModelAlias;
    }

    protected function hasAttributeTypeModels() : bool
    {
        return $this->attributeTypeModelAlias !== null;
    }

    /**
     * The attribute alias of the definition object that holds the type model key. 
     * 
     * Type models simplify the configuration of custom attributes, by assigning meaningful defaults to most attribute
     * properties.
     *
     * @uxon-property type_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    protected function setTypeAttribute(string $alias) : static
    {
        $this->attributeTypeModelAlias = $alias;
        return $this;
    }

    /**
     * Alias of an attribute, that will store assignments to attribute groups for every custom attribute
     * 
     * @uxon-property groups_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    protected function setGroupsAttribute(string $alias) : CustomAttributeDefinitionBehavior
    {
        $this->attributeGroupsAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    protected function getAttributeGroupsAttributeAlias() : ?string
    {
        return $this->attributeGroupsAlias;
    }

    /**
     * @return string
     */
    protected function getNameAttributeAlias() : string
    {
        if(! $this->attributeNameAlias) {
            throw new BehaviorConfigurationError($this, $this->getMissingPropertyMessage("name_attribute"));
        }
        
        return $this->attributeNameAlias;
    }

    /**
     * The attribute of the definition object that holds the name of a custom attribute.
     * 
     * @uxon-property name_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $alias
     * @return $this
     */
    protected function setNameAttribute(string $alias) : static
    {
        $this->attributeNameAlias = $alias;
        return $this;
    }

    /**
     * 
     * @return string|null
     */
    protected function getAliasAttributeAlias() : ?string
    {
        return $this->attributeAliasAlias;
    }

    /**
     * The attributegetRequiredAttributeAliasthe custom attribute to be generated.
     * 
     * @uxon-property alias_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $attributeAlias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAliasAttribute(string $attributeAlias) : CustomAttributeDefinitionBehavior
    {
        $this->attributeAliasAlias = $attributeAlias;
        return $this;
    }

    /**
     * @return string
     */
    protected function getDataAddressAttributeAlias() : ?string
    {
        return $this->attributeStorageKeyAlias;
    }

    /**
     * 
     * @return bool
     */
    protected function hasDataAddresses() : bool
    {
        return $this->attributeStorageKeyAlias !== null;
    }

    /**
     * The attribute alias of the definition object that holds the data address of a custom attribute. 
     * 
     * The data address is used to generate the data address and the technical alias of the custom attribute.
     *
     * @uxon-property data_address_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $alias
     * @return $this
     */
    protected function setDataAddressAttribute(string $alias) : static
    {
        $this->attributeStorageKeyAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    protected function getHintAttributeAlias() : ?string
    {
        return $this->attributeHintAlias;
    }

    /**
     * The attribute alias of the definition object that holds the short description of a custom attribute.
     * 
     * The short description is used for tooltips and info panels, when working with the custom attribute.
     * 
     * @uxon-property hint_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    protected function setHintAttribute(string $alias) : static
    {
        $this->attributeHintAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    protected function getRequiredAttributeAlias() : ?string
    {
        return $this->attributeRequiredAlias;
    }

    /**
     * The attribute of the definition object that determines whether a custom attribute is required.
     * 
     * @uxon-property required_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    protected function setRequiredAttribute(string $alias) : static
    {
        $this->attributeRequiredAlias = $alias;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getRelationPathToOwnerObject() : ?string
    {
        return $this->attributeOwnerObjectAlias;
    }

    /**
     * If attributes for different objects are stored in the same place, you will need a link to the target-object on each attribute.
     * 
     * You only need to set a value for this property, if you are storing custom attribute
     * definitions for more than one MetaObject in the same table.
     * 
     * @uxon-property relation_to_owner_object
     * @uxon-type metamodel:relation
     * 
     * @param string|null $alias
     * @return $this
     */
    protected function setRelationToOwnerObject(?string $alias) : static
    {
        $this->attributeOwnerObjectAlias = $alias;
        return $this;
    }

    /**
     * If TRUE, type models will inherit attribute groups from their parents (default is FALSE).
     * 
     * @uxon-property models_inherit_groups
     * @uxon-type boolean
     * @uxon-template false
     * 
     * @param bool $value
     * @return $this
     */
    protected function setModelsInheritGroups(bool $value) : CustomAttributeDefinitionBehavior
    {
        $this->modelsInheritGroups = $value;
        return $this;
    }

    /**
     * @return bool
     */
    protected function getModelsInheritGroups() : bool
    {
        return $this->modelsInheritGroups;
    }

    /**
     * An associative array with `[TypeModelKey => []]` that contains some basic type model definitions
     * 
     * @return string[][]
     */
    protected function getDefaultTypeModels() : array
    {
        return [
            'Text' => [
                self::KEY_DATA_TYPE => StringDataType::class,
            ],
            'Number' => [
                self::KEY_DATA_TYPE => NumberDataType::class,
                "default_editor_uxon" => ["widget_type" => "InputNumber"]
            ],
            'Integer' => [
                self::KEY_DATA_TYPE => IntegerDataType::class,
                "default_editor_uxon" => ["widget_type" => "InputNumber"]
            ],
            'Boolean' => [
                self::KEY_DATA_TYPE => BooleanDataType::class,
                "default_editor_uxon" => ["widget_type" => "InputCheckBox"]
            ],
            'Date' => [
                self::KEY_DATA_TYPE => DateDataType::class,
                "default_editor_uxon" => ["widget_type" => "InputDate"]
            ],
            'DateTime' => [
                self::KEY_DATA_TYPE => DateTimeDataType::class,
                "default_editor_uxon" => ["widget_type" => "InputDateTime"]
            ],
            'Time' => [
                self::KEY_DATA_TYPE => TimeDataType::class,
                "default_editor_uxon" => ["widget_type" => "InputTime"]
            ]
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
    protected function getAttributeDefaults(CustomAttributesDefinition $definition = null) : array
    {
        // TODO make defaults depend on behaviors settings - e.g. readable if data address known
        $globalDefaults = $this->attributeDefaults;
        
        if ($definition !== null) {
            $defaults = array_merge($globalDefaults, $definition->getAttributeDefaults()->toArray());
        } else {
            $defaults = $globalDefaults;
        }
        return $defaults;
    }

    /**
     * Set the default properties of attributes to be created
     *
     * You can set set any UXON properties of an attribute here - including complex ones
     * like `data_type`, `relation`, etc. Use placeholders to include data of the
     * definition-object. This way, you are free to make any parts of the resulting
     * custom attributes controllable from the definition - not only those explicitly
     * supported by `xxx_attribute` properties of this behavior.
     *
     * The following placeholders can be used in the attribute model:
     *
     * - `[#~custom_attribute_alias#]`
     * - `[#~custom_attribute_name#]`
     * - `[#<attribute_alias_of_definition_object>#]`
     * - `[#=Formula()#]`
     * 
     * @uxon-property attribute_defaults
     * @uxon-type \exface\core\CommonLogic\Model\CustomAttribute
     * @uxon-template {"editable": false, "required": false, "filterable": false, "sortable": false, "aggregatable": false, "value_list_delimiter": ","}
     *
     * @uxon-placeholder [#~custom_attribute_name#]
     * @uxon-placeholder [#~custom_attribute_alias#]
     * @uxon-placeholder [#<metamodel:attribute>#]
     *
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeDefaults(UxonObject $uxon) : CustomAttributeDefinitionBehavior
    {
        $this->attributeDefaults = $uxon->toArray();
        return $this;
    }

    /**
     * Apply filters when reading custom attribute definitions - e.g. if an object only needs some of them.
     * 
     * @uxon-property filters
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setFilters(UxonObject $uxon) : CustomAttributeDefinitionBehavior
    {
        $this->filtersUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    protected function getFiltersUxon() : ?UxonObject
    {
        return $this->filtersUxon;
    }

    /**
     * Apply sorting when reading custom attribute definitions - e.g. if there is a special sequence column.
     * 
     * @uxon-property sorters
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSorter[]
     * @uxon-template [{"attribute_alias": "","direction": "ASC"}]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setSorters(UxonObject $uxon) : CustomAttributeDefinitionBehavior
    {
        $this->sortersUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    protected function getSortersUxon() : ?UxonObject
    {
        return $this->sortersUxon;
    }

    protected function getAliasFromName(string $name) : string
    {
        switch ($this->getAliasGeneratorType()) {
            case self::ALIAS_GENERATOR_CAMEL:
                return MetamodelAliasDataType::generateAlias($name, null, true);
            case self::ALIAS_GENERATOR_UNDERSCORE:
                return MetamodelAliasDataType::generateAlias($name, null, false);
            case self::ALIAS_GENERATOR_UPPER_CASE:
                return MetamodelAliasDataType::generateAlias($name, CASE_UPPER, false);
            case self::ALIAS_GENERATOR_LOWER_CASE:
                return MetamodelAliasDataType::generateAlias($name, CASE_LOWER, false);
            default:
                throw new BehaviorConfigurationError($this, 'Invalid alias generator type "' . $this->getAliasGeneratorType() . '"!');
        }
    }

    /**
     * 
     * @return string
     */
    protected function getAliasGeneratorType() : string
    {
        return $this->aliasGeneratorType ?? self::ALIAS_GENERATOR_UNDERSCORE;
    }

    /**
     * Generate aliases for the attributes automatically from their names.
     * 
     * @uxon-property alias_generator
     * @uxon-type [CamelCase,UPPER_CASE,lower_case,Under_Score]
     * @uxon-default Under_Score
     * 
     * @param string $aliasGeneratorType
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAliasGeneratorType(string $aliasGeneratorType) : CustomAttributeDefinitionBehavior
    {
        $this->aliasGeneratorType = $aliasGeneratorType;
        return $this;
    }

    // Compatibility methods for legacy syntax - remove after 30.06.2025

    /**
     * @deprecated use `setNameAttribute()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeNameAlias(string $alias) : static
    {
        return $this->setNameAttribute($alias);
    }

    /**
     * @deprecated use `setHintAttribute()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeHintAlias(string $alias) : static
    {
        return $this->setHintAttribute($alias);
    }

    /**
     * @deprecated use `setRequiredAttribute()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeRequiredAlias(string $alias) : static
    {
        return $this->setRequiredAttribute($alias);
    }

    /**
     * @deprecated use `setDataAddressAttribute()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeStorageKeyAlias(string $alias) : static
    {
        return $this->setDataAddressAttribute($alias);
    }

    /**
     * @deprecated use `setTypeAttribute()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeTypeModelAlias(string $alias) : static
    {
        return $this->setTypeAttribute($alias);
    }

    /**
     * @deprecated use `setRelationToOwnerObject()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeOwnerObjectAlias(?string $alias) : static
    {
        return $this->setRelationToOwnerObject($alias);
    }

    /**
     * @deprecated use `setCategoryAttribute()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setAttributeCategoryAlias(string $alias) : CustomAttributeDefinitionBehavior
    {
        return $this->setGroupsAttribute($alias);
    }

    /**
     * @deprecated use `setGroupAttribute()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setCategoryAttribute(string $alias) : CustomAttributeDefinitionBehavior
    {
        $this->setGroupsAttribute($alias);
        return $this;
    }

    /**
     * @deprecated use `setModelsInheritGroups()` instead
     * @param string $alias
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setModelsInheritCategories(bool $value) : CustomAttributeDefinitionBehavior
    {
        $this->modelsInheritGroups = $value;
        return $this;
    }
}