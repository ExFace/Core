<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\CustomAttribute;
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
use exface\Core\Interfaces\Model\Behaviors\CustomAttributeLoaderInterface;

/**
 * Automatically adds custom attributes to the object, whenever it is loaded from into memory.
 * 
 * ### Usage Modes
 * 
 * The current implementation supports loading custom attribute definitions from two different sources, depending on
 * the configuration of this behavior:
 * 
 * 1. **From an exclusive definition table:** If you define a value for `definition_object_alias`, the custom attribute
 *  definitions will be loaded from that object. It MUST have a `CustomAttributeDefinitionBehavior` attached to it.
 *  This is very fast and can handle a wide range of data types, but requires you to set up the definition object,
 *  behavior and table. RECOMMENDED.
 * 
 * 2. **From data:** If you do not define a value for `definition_object_alias` the behavior will instead
 * try to deduce its custom attribute definitions from the data stored in the data address of `json_attribute_alias`.
 * This requires loading and parsing the entire data set, which is very slow. In addition, this approach can only
 * produce attributes with data type string. NOT RECOMMENDED.
 * 
 */
class CustomAttributesJsonBehavior 
    extends AbstractBehavior
    implements CustomAttributeLoaderInterface
{
    private bool $processed = false;
    private ?string $jsonDefinitionObjectAlias = null;
    private ?string $jsonAttributeAlias = null;
    private ?string $jsonDataAddress = null;

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
        if($this->isDisabled() || $this->processed || !$event->getObject()->isExactly($this->getObject())) {
            return;
        }
        $this->processed = true;
        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logBook->addLine('Object loaded, checking for custom attributes...');

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));

        $definitionObjectAlias = $this->getDefinitionObjectAlias() ?? $this->getObject()->getAliasWithNamespace();
        if($this->getObject()->isExactly($definitionObjectAlias)) {
            $this->loadAttributesFromData($logBook);
        } else {
            $this->loadAttributesFromDefinition($logBook, $definitionObjectAlias);
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logBook));
    }

    /**
     * Loads custom attributes from an explicit definition as associative array `[AttributeAlias => DataAddress]`.
     *
     * NOTE: This is the default behavior, because it is flexible and fast.
     *
     * @param BehaviorLogBook $logBook
     * @param string          $definitionObjectAlias
     * @return array
     */
    protected function loadAttributesFromDefinition(BehaviorLogBook $logBook, string $definitionObjectAlias) : array
    {
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $definitionObjectAlias);
        $definitionObject = $dataSheet->getMetaObject();
        
        if($definitionObject->getBehaviors()->findBehavior(CustomAttributesJsonBehavior::class)) {
            throw new BehaviorRuntimeError(
                $this,
                'Loading custom attributes from objects with custom attributes is not allowed!',
                null,
                null,
                $logBook);
        }

        $definitionBehavior = $definitionObject->getBehaviors()->findBehavior(CustomAttributeDefinitionBehavior::class);
        if(! $definitionBehavior instanceof CustomAttributeDefinitionBehavior) {
            throw new BehaviorRuntimeError(
                $this,
                'Could not find behavior of type "' . CustomAttributeDefinitionBehavior::class . '" on MetaObject "' . $definitionObjectAlias . '"!',
                null,
                null,
                $logBook);
        }
        
        return $definitionBehavior->addCustomAttributes(
            $this->getObject(), 
            $this, 
            $logBook);
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
            $attribute = MetaObjectFactory::addAttributeTemporary(
                $targetObject,
                $alias,
                $alias,
                $address,
                $dataType,
                CustomAttribute::class);

            $attribute->setFilterable(true);
            $attribute->setSortable(true);
            $attribute->setEditable(true);
            $attribute->setWritable(true);
        }
        $logBook->addIndent(-1);

        return $customAttributes;
    }

    /**
     * Define from which object this behavior should try to load its custom attribute definitions.
     * 
     * The definition object MUST have a behavior of type `CustomAttributeDefinitionBehavior` attached to it!
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

    /**
     * @return string|null
     */
    public function getDefinitionObjectAlias() : ?string
    {
        return $this->jsonDefinitionObjectAlias;
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

    /**
     * @return string
     */
    public function getJsonAttributeAlias() : string
    {
        return $this->jsonAttributeAlias;
    }

    function getCustomAttributeDataAddressPrefix(): string
    {
        if(!$this->jsonDataAddress) {
            $jsonAttribute = $this->getObject()->getAttribute($this->getJsonAttributeAlias());
            $this->jsonDataAddress = $jsonAttribute->getDataAddress();
        }
        
        return $this->jsonDataAddress . '::$.';
    }

    public function customAttributeStorageKeyToAlias(string $storageKey) : string
    {
        if($keyWithoutPrefix = StringDataType::substringAfter($storageKey, '.')){
            $storageKey = $keyWithoutPrefix;
        }
        
        return StringDataType::convertCasePascalToUnderscore($storageKey);
    }
    
    public function getCustomAttributeDataAddress(string $storageKey) : string
    {
        if($keyWithoutPrefix = StringDataType::substringAfter($storageKey, '.')){
            $storageKey = $keyWithoutPrefix;
        }
        
        return $this->getCustomAttributeDataAddressPrefix() . $storageKey;
    }
}