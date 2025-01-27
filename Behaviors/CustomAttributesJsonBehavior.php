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
 * ### Design Considerations
 *
 * 1. Somebody defines a CA (CustomAttribute)
 *      - this can happen anywhere in PUI
 *      - Nobody will know this has happened (by default)
 *      - &rarr; We need a central CONFIG that stores and exposes these definitions
 * 2. Object is loaded into memory
 *      - this may happen without loading its data
 *      - we need to add any CAs to the object at this point to ensure designers and users can understand them
 *      - &rarr; We need to load CA info from the central DEFINITION
 *
 * **Question:** Where should we store the CA DEFINITION?
 *
 * - **Explicit:** Add an JSON column to the MetaObject table, that stores CA structure per MetaObject directly.
 * Clean from a PUI perspective, but messy in terms of maintenance and separation of concerns.
 * - **Implicit:** CA structure is derived from actual data [ row -> JSON-Colum (data and structure)]
 * Clean in terms of maintenance and separation of concerns, but requires loading data whenever we wish to
 * reason about CA structure. It is also difficult to deduce the structure, since data may be incomplete.
 * - **Table:** Create a special table PER PROJECT, where CA structure is stored like [ MetaObjectId -> CA Definitions
 * ]. We need to discuss pros and cons for this one.
 * - Any other options?
 */
class CustomAttributesJsonBehavior extends AbstractBehavior
{
    private bool $processed = false;

    private ?string $jsonDefinitionObjectAlias = null;

    private ?string $jsonDefinitionAttributeAlias = null;
    private string $jsonAttributeAlias;
    private ?string $ownerAttributeAlias = null;

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

    protected function loadAttributesFromData(BehaviorLogBook $logBook): array
    {
        if(empty($this->getJsonAttributeAlias()) ) {
            return [];
        }

        try {
            $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getObject());
            $jsonAttributeAlias = $this->getJsonAttributeAlias();
            $jsonAttribute = $this->getObject()->getAttribute($jsonAttributeAlias);
        } catch (MetaAttributeNotFoundError $error) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attributes.', null, $error, $logBook);
        }

        $dataAddress = $jsonAttribute->getDataAddress();
        $dataSheet->getColumns()->addFromAttribute($jsonAttribute);
        $dataSheet->dataRead();

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
    
    protected function loadAttributesFromDefinition(BehaviorLogBook $logBook) : array
    {
        $definitionObjectAlias = $this->getDefinitionObjectAlias() ?? $this->getObject()->getAliasWithNamespace();
        if(empty($definitionObjectAlias)) {
            return [];
        }
        
        if($this->getObject()->isExactly($definitionObjectAlias)) {
            return $this->loadAttributesFromData($logBook);
        }
        
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $definitionObjectAlias);
        $definitionObject = $dataSheet->getMetaObject();
        
        try {
            $definitionAttributeAlias = $this->getDefinitionAttributeAlias();
            $definitionAttribute = $definitionObject->getAttribute($definitionAttributeAlias);
            $dataSheet->getColumns()->addFromAttribute($definitionAttribute);
            
            $jsonAttribute = $this->getObject()->getAttribute($this->getJsonAttributeAlias());
            $dataAddress = $jsonAttribute->getDataAddress();
            
            if($ownerAttributeAlias = $this->getOwnerAttributeAlias()) {
                $ownerAttribute = $definitionObject->getAttribute($ownerAttributeAlias);
                $dataSheet->getColumns()->addFromAttribute($ownerAttribute);
                $dataSheet->getFilters()->addConditionFromAttribute($ownerAttribute, $this->getObject()->getAliasWithNamespace());
            }
        } catch (MetaAttributeNotFoundError $error) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attributes.', null, $error, $logBook);
        }
        
        $dataSheet->dataRead();

        $customAttributes = [];
        foreach ($dataSheet->getColumnValues($definitionAttributeAlias) as $customAttributeAlias) {
            if(empty($customAttributeAlias)) {
                continue;
            }
            
            $customAttributes[$customAttributeAlias] = $dataAddress . '::$.' . $customAttributeAlias;
        }

        return $customAttributes;
    }

    protected function addAttributes(array $customAttributes, BehaviorLogBook $logBook) : void
    {
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), StringDataType::class);
        foreach ($customAttributes as $alias => $address) {
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
     * @uxon-property owner_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string|null $alias
     * @return $this
     */
    public function setOwnerAttributeAlias(?string $alias) : CustomAttributesJsonBehavior
    {
        $this->ownerAttributeAlias = $alias;
        return $this;
    }
    
    public function getOwnerAttributeAlias() : ?string
    {
        return $this->ownerAttributeAlias;
    }
}