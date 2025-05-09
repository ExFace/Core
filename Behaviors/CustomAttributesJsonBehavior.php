<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\Behaviors\CustomAttributesDefinition;
use exface\Core\CommonLogic\Model\CustomAttribute;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Allows to create additional "custom" attributes in master data and store their values in a single JSON column.
 * 
 * Major business objects often require a lot of attributes, that do not play a direct role in the logic of an
 * app, but rather just keep some information important for the user - reference numbers, notes, categories of
 * something, etc. Very often users will come up with new attributes of this sort regularly, require changes, etc.
 * You can greatly simplify keeping track of these attribtues by adding custom attributes to your object. Instead
 * of creating a data source column for every one of the attributes, with this behavior you will just need a
 * single JSON column and a meta object holding the definitions of these custom attributes. Each attributes defined
 * in the data of that definition-object will act just like a regular attribute in your meta model, but will be
 * stored in the JSON and can be modified/extended any time without changes to the model of the app.
 * 
 * In order tu use JSON custom attributes, you will need two behaviors:
 * 
 * - This `CustomAttributesJsonBehavior` also called "storage-behavior" in the context of custom attributes
 * - A `CustomAttributesDefinitionBehavior` of the object that will hold the available attributes names and settings
 * in your app - referred to as "definition-behavior".
 * 
 * ## Regular attributes vs. custom JSON attributes
 * 
 * Main differences between normal attributes and JSON custom attributes:
 * 
 * - Regular meta attributes are defined by designers inside the meta object. JSON custom attributes are created
 * in master data by designers or even by power users. They are not part of the app model, will not be exported
 * and can even be different on different installations.
 * - Adding a regular attribute requires a data source change (e.g. SQL migration) and an update of the app, while
 * adding a custom attribute can happen on-the-fly any time. As soon, as the attribute definition is saved, the
 * object will have a new attribute and it will be ready to use.
 * - Since custom attributes are not part of the model, you should not use them via `attribute_alias` in pages,
 * actions, etc. directly. Instead, create attribute groups, let the creator of the attributes choose, which groups
 * the attribute should be part of and use these groups in the UI and app logic instead of individual attributes.
 * - Custom attributes are much easier to create than regular ones: all you need is a name, and a type and optionally
 * some other things like attribute groups. The designer of the app decides, what options of custom attributes can be
 * set, what types there are, etc. This is done in the definiton-behavior. After that, users can simply pick their
 * desired options and save the attribute in a very simplified way.
 * 
 * ## Setting up custom JSON attributes for an object
 * 
 * To use custom JSON attributes, follow these steps.
 * 
 * ### 1. Create a place to store attribute definitions
 * 
 * Create a data source and a meta object to store your attribute definitions: e.g. a SQL table. Every data row
 * will represent one custom attribute. Use the following recommended columns:
 * 
 *  - `Name` - the future attribute name (e.g. for widget captions)
 *  - `Alias` - any attribute needs an alias, although it is much less important for custom attributes. You can
 * autogenerate it using the `AliasGeneratingBehavior`.
 *  - `DataAddress` - this will be the key in the JSON storage. You can auto-generate it the same way as the alias.
 * Although, the address can be auto-generated, it is recommended to store it separately nevertheless. If the user
 * chooses to rename the attribute, the address must stay the same in order to keep the values. You can also use this
 * address to add calculated attributes. This is probably too complicated for users, but designer can even add
 * `@T-SQL:` or `@MySQL:` data addresses to for calculated custom attributes. 
 *  - `Description` - This will be used as hint (tooltip) in widgets - it is very helpful!
 *  - `Type` - that will be one of the predefined types of attributes: text, checkbox, number, date, dropdown to pick a 
 * user, etc. We will dive in to this later.
 *  - `Groups` - this will hold a comma-separated list of attribute group aliases
 *  - `OrderNo` - to control the order in which the custom attributes will be displayed
 * 
 * You can also add other options if you like you users to have more control - e.g. a `RequiredFlag` to control if the
 * attribute should be required or not. See `CustomAttributesDefinitioBehavior` for a full list of options.
 * 
 * ### 2. Configure the `CustomAttributesDefinitionBehavior` for the definition-object
 * 
 * Now the meta object created in the previous step needs the `CustomAttributesDefinitionBehavior` to tell the workbench
 * to create custom attributes from its data. You will need to tell this behavior, where it can find the name of the 
 * future attribute, its data address, etc.
 * 
 * ```
 * {
 *  "name_attribute": "Name",
 *  "alias_attribute": "Alias",
 *  "data_address_attribute": "DataAddress",
 *  "hint_attribute": "Description",
 *  "type_attribute": "Type",
 *  "groups_attribute": "Groups",
 *  "sorters": [
 *      {"attribute_alias": "OrderNo", "direction": "asc"}
 *  ]
 * }
 * 
 * ```
 * 
 * ### 3. Create a JSON attribute for your main object
 * 
 * The object, that you want to get the custom attributes now needs a place to store tham - e.g. a SQL column and a 
 * corresponding attribute. In most cases a large text column will be enough. We will store a JSON with all the custom
 * attribute values there.
 * 
 * ### 4. Give the main object the `CustomAttributesJsonBehavior`
 * 
 * Now you can configre this `CustomAttributesJsonBehavior`: 
 * 
 * ```
 * {
 *  "json_attribute_alias": "CustomAttributesJSON",
 *  "attributes_definition": {
 *      "object_alias":"my.App.CustomAttribute"
 *  }
 * }
 * 
 * ```
 * 
 * ### 5. Make custom attributes visible
 * 
 * Now that you have a way to create custom attributes and store their values, make sure they actually appear in the UI.
 * Here are some recommendations.
 * 
 * #### Register all custom attributes as optional columns
 * 
 * Add a `WidgetModifyingBehavior` to the object, that gets the attributes:
 * 
 * ```
 * {
 *  "add_columns": [
 *      {"attribute_group_alias": "~CUSTOM"}
 *  ]
 * }
 * 
 * ```
 * 
 * Now every table widget of you main object will get ALL custom attributes as optional columns, that users can pick
 * in the configurator.
 * 
 * #### Create attribute groups for important usages
 * 
 * If some of the custom attributes are expected to be more important than others, create attribute groups to allow
 * users to control, where their custom attributes are going to be shown. For example:
 * 
 * - `InfoDialogHeaderColumn1` - if the attribute is placed here, it will appear in the header of the main object
 * info dialog in the first column
 * - `InfoDialogHeaderColumn2` - less important attributes go to second column
 * - `VisibleInAllTables` - if placed here, the attribute will be visible in tables right away instead of being
 * an optional column. Of course, you will need to place this attribute group in the column of every widget, that
 * it should effect now: `{"attribute_group_alias": "my.App.VisibleInAllTables"}`
 * 
 * ## SQL data addresses
 * 
 * JSON custom attributes are often used in SQL based data sources. Just like regular attributes can use custom
 * SQL in their data addresses, you can use that in custom attributes addresses as well. However, in custom
 * attribtues the **SQL dialect prefix is required** while being optional in regular attributes. 
 * 
 * Make sure, your data address starts with `@T-SQL:` or `@MySQL:` depending on your query builder. You can even 
 * use placeholders like `[#~alias#]` - just like you would do in regular SQL attributes.
 * 
 * **NOTE:** Custom attributes with SQL data addresses will automatically become non-editable!
 * 
 * ## Raw data mode
 * 
 * If you do not define a value for `attribute_defition` the behavior will instead try to deduce its custom attribute 
 * definitions from the data stored in the data address of `json_attribute_alias`.
 * 
 * This requires loading and parsing the entire data set, which is very slow. In addition, this approach can only
 * produce attributes with data type string. NOT RECOMMENDED in most cases!
 * 
 * @author Georg Bieger, Andrej Kabachnik
 */
class CustomAttributesJsonBehavior extends AbstractBehavior
{
    private ?CustomAttributesDefinition $attributeDefinition = null;
    private ?string $jsonAttributeAlias = null;
    private ?UxonObject $definitionDefaults = null;

    protected function registerEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedAddAttributesToObject'],
            $this->getPriority()
        );

        return $this;
    }

    protected function unregisterEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedAddAttributesToObject']
        );

        return $this;
    }

    /**
     * Loads and adds temporary custom attributes from JSON definitions to the object this behavior is attached to.
     * 
     * @param OnMetaObjectLoadedEvent $event
     * @return void
     */
    public function onLoadedAddAttributesToObject(OnMetaObjectLoadedEvent $event) : void
    {
        if($this->isDisabled() || ! $event->getObject()->isExactly($this->getObject())) {
            return;
        }

        $obj = $event->getObject();

        // See if the object instance already has attributes generated by this behavior. If so, exit here.
        foreach ($obj->getAttributes() as $attr) {
            if (($attr instanceof CustomAttribute) && $attr->getSource() === $this) {
                return;
            }
        }

        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logBook->addLine('Object loaded, checking for custom attributes...');

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));

        $definition = $this->getAttributesDefinition();
        if($this->getObject()->isExactly($definition->getDefinitionsObject())) {
            $this->loadAttributesFromData($logBook);
        } else {
            $this->loadAttributesFromDefinition($definition, $logBook);
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logBook));
    }

    /**
     * Loads custom attributes from an explicit definition as associative array `[AttributeAlias => DataAddress]`.
     *
     * NOTE: This is the default behavior, because it is flexible and fast.
     *
     * @param string $definitionObjectAlias
     * @param BehaviorLogBook $logBook
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface[]
     */
    protected function loadAttributesFromDefinition(CustomAttributesDefinition $definition, BehaviorLogBook $logBook) : array
    {
        $definitionObject = $definition->getDefinitionsObject();
        
        if($definitionObject->getBehaviors()->findBehavior(CustomAttributesJsonBehavior::class) !== null) {
            throw new BehaviorRuntimeError(
                $this,
                'Loading custom attributes from objects with custom attributes is not allowed!',
                null,
                null,
                $logBook);
        }

        $definitionBehavior = $definition->getDefinitionBehavior();
        
        $attrs = $definitionBehavior->addAttributesToObject(
            $this->getObject(), 
            $definition, 
            $logBook
        );

        foreach ($attrs as $attr) {
            if ($this->isNonJsonDataAddress($attr->getDataAddress())) {
                $attr->setEditable(false);
            }
            $attr->setDataAddress($this->getCustomAttributeDataAddress($attr->getDataAddress()));
        }
        return $attrs;
    }

    /**
     * Deduces custom attributes from data stored in the `json_attribute_alias` as associative array `[AttributeAlias
     * => DataAddress]`.
     * 
     * NOTE: This mode is very slow and is only viable as a fallback.
     * 
     * @param BehaviorLogBook $logBook
     * @return array
     */
    protected function loadAttributesFromData(BehaviorLogBook $logBook): array
    {
        $logBook->addLine('"definition_object_alias" was undefined. Loading custom attribute definitions from data instead.');
        
        try {
            $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getObject());
            $jsonAttributeAlias = $this->getJsonAttributeAlias();
        } catch (MetaAttributeNotFoundError $error) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attributes:', null, $error, $logBook);
        }

        $dataSheet->dataRead();
        $logBook->addDataSheet('Definitions', $dataSheet);
        $logBook->addLine('Successfully loaded custom attribute definitions (see "Definitions").');
        
        $customAttributes = [];
        foreach ($dataSheet->getColumnValues($jsonAttributeAlias) as $json) {
            if(empty($json) || $json === '{}') {
                continue;
            }

            foreach (json_decode($json) as $storageKey => $value) {
                if(key_exists($storageKey, $customAttributes)) {
                    continue;
                }

                $customAttributes[$storageKey] = $this->getCustomAttributeDataAddress($storageKey);
            }
        }

        $logBook->addLine("Adding custom attributes...");
        $logBook->addIndent(1);
        $targetObject = $this->getObject();
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), StringDataType::class);
        foreach ($customAttributes as $alias => $address) {
            $logBook->addLine('Adding attribute "' . $alias . '" with data address "' . $address . '".');
            $attribute = new CustomAttribute($targetObject, $alias, $alias, $this);
            $attribute->setDataAddress($address);
            $attribute->setDataType($dataType);
            $attribute->setFilterable(true);
            $attribute->setSortable(true);
            $attribute->setEditable(true);
            $attribute->setWritable(true);
            $targetObject->getAttributes()->add($attribute);
        }
        $logBook->addIndent(-1);

        return $customAttributes;
    }

    /**
     * Where to find the corresponding CustomAttributeDefinitionBehavior
     * 
     * @uxon-property attributes_definition
     * @uxon-type \exface\Core\CommonLogic\Model\Behaviors\CustomAttributesDefinition
     * @uxon-required true
     * @uxon-template {"object_alias": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesJsonBehavior
     */
    protected function setAttributesDefinition(UxonObject $uxon) : CustomAttributesJsonBehavior
    {
        $this->attributeDefinition = new CustomAttributesDefinition($this, $uxon);
        if (null !== $defs = $this->getAttributesDefaults()) {
            $this->attributeDefinition->setAttributeDefaults($defs);
        }
        return $this;
    }

    /**
     * 
     * @return CustomAttributesDefinition|null
     */
    protected function getAttributesDefinition() : CustomAttributesDefinition
    {
        return $this->attributeDefinition;
    }

    /**
     * Define the attribute alias where the actual JSON data is stored. It must belong to the object this behavior is attached to.
     * 
     * @uxon-property json_attribute_alias 
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    protected function setJsonAttributeAlias(string $alias) : CustomAttributesJsonBehavior
    {
        $this->jsonAttributeAlias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    protected function getJsonAttributeAlias() : string
    {
        return $this->jsonAttributeAlias;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\CustomAttributeLoaderInterface::getCustomAttributeDataAddress()
     */
    protected function getCustomAttributeDataAddress(string $jsonKey) : string
    {
        if ($this->isNonJsonDataAddress($jsonKey)) {
            return $jsonKey;
        }

        if (! StringDataType::startsWith($jsonKey, '$.')) {
            $jsonPath = '$.' . $jsonKey;
        } else {
            $jsonPath = $jsonKey;
        }
        $jsonAttribute = $this->getObject()->getAttribute($this->getJsonAttributeAlias());
        return $jsonAttribute->getDataAddress() . '::' . $jsonPath;
    }

    /**
     * Returns TRUE if the given data address is NOT a JSON path or JSON key
     * 
     * @param string $address
     * @return bool
     */
    protected function isNonJsonDataAddress(string $address) : bool
    {
        return StringDataType::startsWith($address, '@');
    }

    /**
     * Change the default properties of attributes to be created
     * 
     * @uxon-property attributes_defaults
     * @uxon-type \exface\Core\CommonLogic\Model\Attribute
     * @uxon-template {"groups": [""], "writable": true, "copyable": true, "editable": true, "required": false, "filterable": true, "sortable": true, "aggregatable": false, "value_list_delimiter": ","}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesJsonBehavior
     */
    protected function setAttributesDefaults(UxonObject $uxon) : CustomAttributesJsonBehavior
    {
        $this->definitionDefaults = $uxon;
        if ($this->attributeDefinition instanceof CustomAttributesDefinition) {
            $this->attributeDefinition->setAttributeDefaults($uxon);
        }
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    protected function getAttributesDefaults() : UxonObject
    {
        return $this->definitionDefaults ?? new UxonObject([
            // BASIC FLAGS
            "writable" => true,
            "copyable" => true,
            "editable" => true,
            "required" => false,
            "filterable" => true,
            "sortable" => true,
            "aggregatable" => false,
            // DEFAULTS
            "value_list_delimiter" => EXF_LIST_SEPARATOR,
        ]);
    }
}