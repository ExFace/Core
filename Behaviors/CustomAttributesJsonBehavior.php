<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Automatically adds custom attributes to the object, whenever it is loaded from into memory.
 * 
 * ### Usage Modes
 * 
 * The current implementation supports loading custom attribute definitions from three different sources, depending on the
 * configuration of this behavior:
 * 
 * 1. **From Data (implicit):** If you do not define a value for `definition_object_alias` the behavior will instead try to
 * deduce its custom attribute definitions from the data stored in the data address of `json_attribute_alias`. This requires loading and parsing 
 * the entire data set, which is very slow. NOT RECOMMENDED.
 * 
 * 2. **From an exclusive definition table (explicit):** If you define a value for `definition_object_alias` and `definition_attribute_alias`, 
 * but leave `definition_owner_attribute_alias` undefined, the behavior will assume that you provided a specialized table that only contains
 * custom attribute definitions for the object it is attached to. This is very fast, but requires you to set up an exclusive table for
 * this MetaObject. RECOMMENDED.
 * 
 * 3. **From a general definition table (explicit):** If you also define a value for `definition_owner_attribute_alias`, the behavior will assume that you
 * provided a general definition table that contains custom attribute definitions for any number of MetaObjects. This is reasonably fast and requires
 * little setup. RECOMMENDED.
 * 
 * ### TO DOs
 * 
 * - The DataType of all JSON-Attributes is hard-coded to be `Text`. We should update this with an option to load a data-type from the definition.
 * - If `definition_object_alias` references an object, that has itself a custom attributes behavior an error will be thrown.
 * In rare cases this might even result in infinite recursion. This happens, because the object referenced in `definition_object_alias` must be
 * loaded into memory, which in turn would trigger a new instance of this behavior.
 * TODO geb 2025-27-01: How to properly guard against this? (Idea: Static list?)
 */
class CustomAttributesJsonBehavior extends AbstractBehavior
{
    private bool $processed = false;

    private ?string $jsonDefinitionObjectAlias = null;

    private ?string $jsonDefinitionAttributeAlias = null;
    private string $jsonAttributeAlias;
    private ?string $definitionOwnerAttributeAlias = null;

    protected function registerEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedAddCustomAttributes'],
            $this->getPriority())
        ;

        return $this;
    }

    protected function unregisterEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedAddCustomAttributes']
        );

        return $this;
    }

    /**
     * Loads and adds temporary custom attributes from JSON definitions to the object this behavior is attached to.
     * 
     * @param OnMetaObjectLoadedEvent $event
     * @return void
     */
    public function onLoadedAddCustomAttributes(OnMetaObjectLoadedEvent $event) : void
    {
        if($this->isDisabled() || $this->processed) {
            return;
        }
        $this->processed = true;

        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logBook->addLine('Object loaded, checking for custom attributes...');

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));

        $customAttributes = $this->loadAttributesFromDefinition($logBook);
        $this->addAttributes($customAttributes, $logBook);

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logBook));
    }

    /**
     * Loads custom attributes from an explicit definition.
     * 
     * NOTE: This is the default behavior, because it is flexible and fast.
     * 
     * @param BehaviorLogBook $logBook
     * @return array
     */
    protected function loadAttributesFromDefinition(BehaviorLogBook $logBook) : array
    {
        $definitionObjectAlias = $this->getDefinitionObjectAlias() ?? $this->getObject()->getAliasWithNamespace();
        if($this->getObject()->isExactly($definitionObjectAlias)) {
            return $this->loadAttributesFromData($logBook);
        }

        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $definitionObjectAlias);
        $definitionObject = $dataSheet->getMetaObject();
        
        foreach ($definitionObject->getBehaviors() as $behavior) {
            if($behavior instanceof CustomAttributesJsonBehavior) {
                throw new BehaviorRuntimeError(
                    $this, 
                    'Loading custom attributes from objects with custom attributes is not allowed!',
                    null, 
                    null,
                    $logBook);
            }
        }

        try {
            $definitionAttributeAlias = $this->getDefinitionAttributeAlias();
            $definitionAttribute = $definitionObject->getAttribute($definitionAttributeAlias);
            $dataSheet->getColumns()->addFromAttribute($definitionAttribute);
            $logBook->addLine('Initialized definition attribute: "' . $definitionAttributeAlias . '".');

            $jsonAttribute = $this->getObject()->getAttribute($this->getJsonAttributeAlias());
            $dataAddress = $jsonAttribute->getDataAddress();
            $logBook->addLine('Generated data address: "' . $dataAddress . '".');

            if($ownerAttributeAlias = $this->getOwnerAttributeAlias()) {
                $logBook->addLine('Owner attribute alias: "' . $ownerAttributeAlias . '".');
                $ownerAttribute = $definitionObject->getAttribute($ownerAttributeAlias);
                $dataSheet->getColumns()->addFromAttribute($ownerAttribute);
                $dataSheet->getFilters()->addConditionFromAttribute($ownerAttribute, $this->getObject()->getAliasWithNamespace());
                $logBook->addLine('Initialized owner attribute. Loading only attributes with "' . $ownerAttributeAlias . '" == "' . $this->getObject()->getAliasWithNamespace() . '".');
            } else {
                $logBook->addLine('Owner attribute undefined. Loading all attributes present in "' . $definitionObjectAlias . '".');
            }
        } catch (MetaAttributeNotFoundError $error) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attributes.', null, $error, $logBook);
        }

        $dataSheet->dataRead();
        $logBook->addDataSheet('Definitions', $dataSheet);
        $logBook->addLine('Successfully loaded custom attribute definitions (see "Definitions").');

        $customAttributes = [];
        foreach ($dataSheet->getColumnValues($definitionAttributeAlias) as $customAttributeAlias) {
            if(empty($customAttributeAlias)) {
                continue;
            }

            $customAttributes[$customAttributeAlias] = $dataAddress . '::$.' . $customAttributeAlias;
        }

        return $customAttributes;
    }

    /**
     * Deduces custom attributes from data stored in the `json_attribute_alias`.
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
            $jsonAttribute = $this->getObject()->getAttribute($jsonAttributeAlias);
            $logBook->addLine('Initialized definition attribute: "' . $jsonAttributeAlias . '".');
            
            $dataAddress = $jsonAttribute->getDataAddress();
            $dataSheet->getColumns()->addFromAttribute($jsonAttribute);
            $logBook->addLine('Generated data address: "' . $dataAddress . '".');
        } catch (MetaAttributeNotFoundError $error) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attributes.', null, $error, $logBook);
        }

        $dataSheet->dataRead();
        $logBook->addDataSheet('Definitions', $dataSheet);
        $logBook->addLine('Successfully loaded custom attribute definitions (see "Definitions").');
        
        $customAttributes = [];
        foreach ($dataSheet->getColumnValues($jsonAttributeAlias) as $json) {
            if(empty($json) || $json === '{}') {
                continue;
            }

            foreach (json_decode($json) as $attribute => $value) {
                if(key_exists($attribute, $customAttributes)) {
                    continue;
                }

                $customAttributes[$attribute] = $dataAddress . '::$.' . $attribute;
            }
        }

        return $customAttributes;
    }

    /**
     * Adds the list of attributes to the object this behavior is attached to.
     * 
     * @param array           $customAttributes
     * @param BehaviorLogBook $logBook
     * @return void
     */
    protected function addAttributes(array $customAttributes, BehaviorLogBook $logBook) : void
    {
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), StringDataType::class);
        foreach ($customAttributes as $alias => $address) {
            $logBook->addLine('Adding attribute "' . $alias . '" with data address "' . $address . '".');
            $attribute = MetaObjectFactory::addAttributeTemporary(
                $this->getObject(), 
                $alias, 
                $alias, 
                $address, 
                $dataType);
            
            $attribute->setFilterable(true);
            $attribute->setSortable(true);
        }
    }

    /**
     * Define from which object this behavior should try to load its custom attribute definitions.
     * 
     * @uxon-property definition_object_alias
     * @uxon-type metamodel:object
     *
     * @param string|null $alias
     * @return CustomAttributesJsonBehavior
     */
    public function setDefinitionObjectAlias(?string $alias) : CustomAttributesJsonBehavior
    {
        $this->jsonDefinitionObjectAlias = $alias;
        return $this;
    }

    public function getDefinitionObjectAlias() : ?string
    {
        return $this->jsonDefinitionObjectAlias;
    }

    /**
     * Define the attribute where the actual definition for each custom attribute can be found.
     * 
     * This attribute belongs to the object identified with `definition_object_alias`.
     * 
     * @uxon-property definition_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string|null $alias
     * @return CustomAttributesJsonBehavior
     */
    public function setDefinitionAttributeAlias(?string $alias) : CustomAttributesJsonBehavior
    {
        $this->jsonDefinitionAttributeAlias = $alias;
        return $this;
    }

    public function getDefinitionAttributeAlias() : ?string
    {
        return $this->jsonDefinitionAttributeAlias;
    }

    /**
     * Define the attribute alias where the actual JSON data is stored. 
     * 
     * This attribute belongs to the object this behavior is attached to.
     * 
     * @uxon-property json_attribute_alias 
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    public function setJsonAttributeAlias(string $alias) : CustomAttributesJsonBehavior
    {
        $this->jsonAttributeAlias = $alias;
        return $this;
    }
    
    public function getJsonAttributeAlias() : string
    {
        return $this->jsonAttributeAlias;
    }

    /**
     * Define the attribute alias used to store the owner of a custom attribute definition. 
     * 
     * This attribute belongs to the object identified with `definition_object_alias`.
     * If you want to store custom attribute definitions for multiple meta objects
     * in the same table, you need to assign a value to this property.
     * Ignore this property if you have separate definition tables for each meta object.
     * 
     * Default value is `""` (NULL).
     * 
     * @uxon-property definition_owner_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string|null $alias
     * @return $this
     */
    public function setDefinitionOwnerAttributeAlias(?string $alias) : CustomAttributesJsonBehavior
    {
        $this->definitionOwnerAttributeAlias = $alias;
        return $this;
    }
    
    public function getDefinitionOwnerAttributeAlias() : ?string
    {
        return $this->definitionOwnerAttributeAlias;
    }
}