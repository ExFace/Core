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
        if( empty($this->getJsonDefinitionAttributeAlias()) ) {
            return [];
        }
        
        $definitionObjectAlias = $this->getJsonDefinitionObjectAlias() ?? $this->getObject()->getAliasWithNamespace();
        
        if(!$this->getObject()->isExactly($definitionObjectAlias)) {
            return $this->loadAttributesFromDefinition($logBook);
        }

        try {
            $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getObject());
            $jsonAttribute = $this->getObject()->getAttribute($this->getJsonDefinitionAttributeAlias());
        } catch (MetaAttributeNotFoundError $error) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attributes.', null, $error, $logBook);
        }

        $dataAddress = $jsonAttribute->getDataAddress();
        $dataSheet->getColumns()->addFromAttribute($jsonAttribute);
        $dataSheet->dataRead();

        $customAttributes = [];
        foreach ($dataSheet->getColumnValues($this->getJsonDefinitionAttributeAlias()) as $json) {
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
        if( empty($this->getJsonDefinitionAttributeAlias()) || empty($this->getJsonDefinitionObjectAlias()) ) {
            return [];
        }

        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getJsonDefinitionObjectAlias());
        $object = $dataSheet->getMetaObject();

        try {
            $jsonAttribute = $object->getAttribute($this->getJsonDefinitionAttributeAlias());
        } catch (MetaAttributeNotFoundError $error) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attributes.', null, $error, $logBook);
        }

        $dataAddress = $jsonAttribute->getDataAddress();
        $dataSheet->getColumns()->addFromAttribute($jsonAttribute);
        $dataSheet->getColumns()->addFromUidAttribute();
        $dataSheet->getFilters()->addConditionFromAttribute($dataSheet->getUidColumn()->getAttribute(), $object->getId());
        $dataSheet->dataRead();

        $customAttributes = [];
        foreach ($dataSheet->getColumnValues($this->getJsonDefinitionAttributeAlias()) as $json) {
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
     * @uxon-property json_definition_object_alias
     * @uxon-type metamodel:object
     *
     * @param string|null $alias
     * @return CustomAttributesJsonBehavior
     */
    public function setJsonDefinitionObjectAlias(?string $alias) : CustomAttributesJsonBehavior
    {
        $this->jsonDefinitionObjectAlias = $alias;
        return $this;
    }

    public function getJsonDefinitionObjectAlias() : ?string
    {
        return $this->jsonDefinitionObjectAlias;
    }

    /**
     * @uxon-property json_definition_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string|null $alias
     * @return CustomAttributesJsonBehavior
     */
    public function setJsonDefinitionAttributeAlias(?string $alias) : CustomAttributesJsonBehavior
    {
        $this->jsonDefinitionAttributeAlias = $alias;
        return $this;
    }

    public function getJsonDefinitionAttributeAlias() : ?string
    {
        return $this->jsonDefinitionAttributeAlias;
    }
}