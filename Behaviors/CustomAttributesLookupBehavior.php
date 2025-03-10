<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

/**
 * 
 * ## Examples
 * 
 * Concider construction reports that can be allocated to multiple locations: e.g. a report
 * is about a certain construction segment, which is part of a construction site, which is part
 * of a project, etc. All these locations are attributes of the report from the point of view
 * of the business. But if the construction hierarchy is dynamic, technically they will not
 * be stored as columns of the report table, but rather in a mapping table like `REPORT_LOCATION`.
 * Each report would have multiple entries in this table, each with a different location type.
 * 
 * Now our goal is to generate custom report attributes for every location type.
 *  
 * ```
 *  {
 *      "definition_object_alias": "my.App.LOCATION_TYPE",
 *      "relation_to_values_object": "REPORT_LOCATION__LOCATION",
 *      "values_content_column": "REPORT_LOCATION__REPORT",
 *      "values_attribute_alias_column": "LOCATION_TYPE__NAME",
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 */
class CustomAttributesLookupBehavior extends AbstractBehavior
{
    private bool $processed = false;
    private ?string $definitionObjectAlias = null;

    private ?UxonObject $definitionFiltersUxon = null;

    private ?MetaRelationPathInterface $relationPathToValues = null;

    private $attributes = [];

    private $valueLookupUxon = null;

    private $valueAttributeAliasColumnAlias = null;

    private $valueContentColumnAlias = null;

    protected function registerEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedAddCustomAttributes'],
            $this->getPriority()
        );
        $this->getWorkbench()->eventManager()->addListener(
            OnReadDataEvent::getEventName(),
            [$this,'onReadDataJoinAttributes'],
            $this->getPriority()
        );

        return $this;
    }

    protected function unregisterEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedAddCustomAttributes']
        );
        $this->getWorkbench()->eventManager()->removeListener(
            OnReadDataEvent::getEventName(),
            [$this,'onReadDataJoinAttributes']
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
        $this->loadAttributesFromDefinition($definitionObjectAlias, $logBook);

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logBook));
    }

    public function onReadDataJoinAttributes(OnReadDataEvent $event) 
    {
        if($this->isDisabled()) {
            return;
        }

        $eventSheet = $event->getDataSheet();
        if (! $eventSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        if ($eventSheet->isEmpty()) {
            return;
        }

        $requiredAttrs = [];
        foreach ($eventSheet->getColumns() as $col) {
            if ($col->isAttribute() && in_array($col->getAttribute(), $this->attributes)) {
                $requiredAttrs[] = $col->getAttribute();
            }
        }
        if (empty($requiredAttrs)) {
            return;
        }
        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));

        $lookupSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->getValuesDataUxon() ?? new UxonObject(), $this->getRelationPathToValuesObject()->getEndObject());
        $lookupSheet->getFilters()->addConditionFromValueArray($this->getRelationPathToValuesObject()->reverse()->__toString(), $eventSheet->getUidColumn()->getValues());
        $lookupKeyCol = $lookupSheet->getColumns()->addFromExpression($this->getRelationPathToValuesObject()->reverse()->__toString());
        $lookupContentCol = $lookupSheet->getColumns()->addFromExpression($this->getValuesContentColumnAlias());
        $lookupAliasCol = $lookupSheet->getColumns()->addFromExpression($this->getValuesAttributeAliasColumnAlias());
        $lookupSheet->dataRead();
        foreach ($lookupSheet->getRows() as $row) {
            $key = $row[$lookupKeyCol->getName()];
            $eventIdx = $eventSheet->getUidColumn()->findRowByValue($key);
            if ($eventIdx === null) {
                continue;
            }
            $eventSheet->setCellValue($row[$lookupAliasCol->getName()], $eventIdx, $row[$lookupContentCol->getName()]);
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
    protected function loadAttributesFromDefinition(string $definitionObjectAlias, LogBookInterface $logBook) : array
    {
        $definitionObject = MetaObjectFactory::createFromString($this->getWorkbench(), $definitionObjectAlias);
        
        if($definitionObject->getBehaviors()->findBehavior(CustomAttributesJsonBehavior::class)) {
            throw new BehaviorRuntimeError(
                $this,
                'Loading custom attributes from objects with custom attributes is not allowed!',
                null,
                null,
                $logBook
            );
        }

        $definitionBehavior = $definitionObject->getBehaviors()->findBehavior(CustomAttributeDefinitionBehavior::class);
        if(! $definitionBehavior instanceof CustomAttributeDefinitionBehavior) {
            throw new BehaviorRuntimeError(
                $this,
                'Could not find behavior of type "' . CustomAttributeDefinitionBehavior::class . '" on MetaObject "' . $definitionObjectAlias . '"!',
                null,
                null,
                $logBook
            );
        }
        
        if (null !== $filtersUxon = $this->getDefinitionFiltersUxon()) {
            $definitionBehavior->setFilters($filtersUxon);
        }

        $this->attributes = $definitionBehavior->addCustomAttributes(
            $this->getObject(), 
            $logBook
        );

        foreach ($this->attributes as $attr) {
            $attr->setWritable(false);
        }

        return $this->attributes;
    }

    /**
     * The object, that contains the definitions of the custom attributes and has a CustomAttributeDefinitionBehavior
     * 
     * @uxon-property definition_object_alias
     * @uxon-type metamodel:object
     * @uxon-required true
     * 
     * @param string|null $alias
     * @return CustomAttributesLookupBehavior
     */
    protected function setDefinitionObjectAlias(?string $alias) : CustomAttributesLookupBehavior
    {
        $this->definitionObjectAlias = $alias;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getDefinitionObjectAlias() : string
    {
        return $this->definitionObjectAlias;
    }

    /**
     * Relation from this object to the object, that contains the values of the generic attributes
     * 
     * @uxon-property relation_to_values_object
     * @uxon-type metamodel:relation
     * 
     * @param mixed $relPath
     * @return CustomAttributesLookupBehavior
     */
    protected function setRelationToValuesObject(?string $relPath) : CustomAttributesLookupBehavior
    {
        $this->relationPathToValues = RelationPathFactory::createFromString($this->getObject(), $relPath);
        return $this;
    }

    /**
     * 
     * @return MetaRelationPathInterface|null
     */
    protected function getRelationPathToValuesObject() : MetaRelationPathInterface
    {
        return $this->relationPathToValues;
    }

    /**
     * Custom data sheet to lookup the values of the attributes
     * 
     * If not set, it will be generated automatically.
     * 
     * @uxon-property values_data
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "filters": {"operator": "AND","conditions":[{"expression": "","comparator": "=","value": ""}]}}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesLookupBehavior
     */
    protected function setValuesData(UxonObject $uxon) : CustomAttributesLookupBehavior
    {
        $this->valueLookupUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject
     */
    protected function getValuesDataUxon() : ?UxonObject
    {
        return $this->valueLookupUxon;
    }

    /**
     * Column of the lookup data sheet, that will contain the aliases of the custom attributes
     * 
     * @uxon-property values_attribute_alias_column
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $col
     * @return CustomAttributesLookupBehavior
     */
    protected function setValuesAttributeAliasColumn(string $col) : CustomAttributesLookupBehavior
    {
        $this->valueAttributeAliasColumnAlias = $col;
        return $this;
    }

    /**
     * 
     * @return string
     */
    protected function getValuesAttributeAliasColumnAlias() : string
    {
        return $this->valueAttributeAliasColumnAlias;
    }

    /**
     * Column of the lookup data sheet, that will contain the values of the custom attributes
     * 
     * @uxon-property values_content_column
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $col
     * @return CustomAttributesLookupBehavior
     */
    protected function setValuesContentColumn(string $col) : CustomAttributesLookupBehavior
    {
        $this->valueContentColumnAlias = $col;
        return $this;
    }

    /**
     * 
     * @return string
     */
    protected function getValuesContentColumnAlias() : string
    {
        return $this->valueContentColumnAlias;
    }

    /**
     * Apply filters when reading custom attribute definitions.
     * 
     * @uxon-property definition_filters
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"base_object_alias": "", "operator": "AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributeDefinitionBehavior
     */
    protected function setDefinitionFilters(UxonObject $uxon) : CustomAttributesLookupBehavior
    {
        $this->definitionFiltersUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    protected function getDefinitionFiltersUxon() : ?UxonObject
    {
        return $this->definitionFiltersUxon;
    }
}