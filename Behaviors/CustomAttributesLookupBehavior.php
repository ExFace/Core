<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\Behaviors\CustomAttributesDefinition;
use exface\Core\CommonLogic\Model\Behaviors\CustomAttributesLookup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Adds read-only custom attributes to an object and fills them with values from a lookup data sheet.
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
 * The following behavior config will generate custom report attributes for every location type. 
 * For convenience we put them all into the attribute group `my.App.LOCATIONS`, that we have created 
 * reviously for the report object. This group will allow us to quickly place all location in table
 * `DataColumnGroup` or a `WidgetGroup`.
 *  
 * ```
 *  {
 *      "attributes_definition": {
 *          "object_alias": "my.App.LOCATION_TYPE"
 *      },
 *      "attribtues_defaults": {
 *          "groups": "my.App.LOCATIONS"
 *      },
 *      "values_lookup": {
 *          "object_alias": "my.App.REPORT_LOCATION",
 *          "relation_to_behavior_object": "REPORT_LOCATION__LOCATION",
 *          "values_content_column": "REPORT_LOCATION__REPORT",
 *          "values_attribute_alias_column": "LOCATION_TYPE__NAME"
 *      }
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 */
class CustomAttributesLookupBehavior extends AbstractBehavior
{
    private ?CustomAttributesDefinition $attributeDefinition = null;

    private ?CustomAttributesLookup $valuesLookup = null;

    private $attributes = null;
    private ?UxonObject $definitionDefaults = null;

    protected function registerEventListeners(): BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(
            OnMetaObjectLoadedEvent::getEventName(),
            [$this,'onLoadedAddAttributesToObject'],
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
            [$this,'onLoadedAddAttributesToObject']
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
    public function onLoadedAddAttributesToObject(OnMetaObjectLoadedEvent $event) : void
    {
        if($this->isDisabled() || ! empty($this->attributes) || !$event->getObject()->isExactly($this->getObject())) {
            return;
        }

        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logBook->addLine('Object loaded, checking for custom attributes...');

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));
        
        $definition = $this->getAttributesDefinition();
        $definitionBehavior = $definition->getDefinitionBehavior();
        $this->attributes = $definitionBehavior->addAttributesToObject($this->getObject(), $definition, $logBook);

        foreach ($this->attributes as $attr) {
            // This behavior definitely cannot produce attributes, that are writable, sortable or aggregatable,
            // so set the properties to false here regardless of attribute models or attribute defaults.
            $attr->setWritable(false);
            $attr->setSortable(false);
            $attr->setAggregatable(false);
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logBook));
    }

    /**
     * Joins custom attribute values from a lookup data sheet to the data sheet of the object this behavior is attached to
     * 
     * @param \exface\Core\Events\DataSheet\OnReadDataEvent $event
     * @throws \exface\Core\Exceptions\Behaviors\BehaviorRuntimeError
     * @return void
     */
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
        
        $lookup = $this->getValuesLookup();
        $lookupSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $lookup->getValuesDataSheetUxon() ?? new UxonObject(), $lookup->getObject());
        $eventSheetKeyAttr = $lookup->getRelationPathToBehaviorObject()->getRelationLast()->getRightKeyAttribute();
        $eventSheetKeyCol = $eventSheet->getColumns()->getByAttribute($eventSheetKeyAttr);
        if (! $eventSheetKeyCol) {
            throw new BehaviorRuntimeError($this, 'Cannot load custom attribute values: no key column ' . $eventSheetKeyAttr->__toString() . ' in event data!', null, null, $logBook);
        }
        $lookupSheet->getFilters()->addConditionFromValueArray($lookup->getRelationPathToBehaviorObject()->__toString(), $eventSheetKeyCol->getValues());
        $lookupKeyCol = $lookupSheet->getColumns()->addFromExpression($lookup->getRelationPathToBehaviorObject()->__toString());
        $lookupContentCol = $lookupSheet->getColumns()->addFromExpression($lookup->getValuesContentColumnAlias());
        $lookupContentName = $lookupContentCol->getName();
        $lookupAliasCol = $lookupSheet->getColumns()->addFromExpression($lookup->getValuesAttributeAliasColumnAlias());
        $lookupAliasName = $lookupAliasCol->getName();

        $additionalCols = [];
        foreach ($lookup->getAdditionalColumns() as $lookupCol) {
            $additionalCols[] = $lookupSheet->getColumns()->addFromExpression($lookupCol->getLookupExpression(), null, true);
        }

        $lookupSheet->dataRead();
        $logBook->addDataSheet('Custom attribute values', $lookupSheet);

        $delim = $lookupAliasCol->getAttribute()->getValueListDelimiter();
        foreach ($lookupSheet->getRows() as $row) {
            $key = $row[$lookupKeyCol->getName()];
            $eventRowIdx = $eventSheet->getUidColumn()->findRowByValue($key);
            if ($eventRowIdx === null) {
                continue;
            }
            $val = $eventSheet->getCellValue($row[$lookupAliasName], $eventRowIdx);
            $val .= ($val !== null ? $delim : '') . $row[$lookupContentName];
            $eventSheet->setCellValue($row[$lookupAliasName], $eventRowIdx, $val);

            foreach ($additionalCols as $i => $additionalCol) {
                $lookupCol = $lookup->getAdditionalColumns()[$i];
                $addtionalColName = $lookupCol->getColumnName([
                    CustomAttributeDefinitionBehavior::PLACEHOLDER_ALIAS => $row[$lookupAliasName]
                ]);
                $eventSheet->setCellValue($addtionalColName, $eventRowIdx, $row[$additionalCol->getName()]);
            }
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logBook));
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
     * @return CustomAttributesLookupBehavior
     */
    protected function setAttributesDefinition(UxonObject $uxon) : CustomAttributesLookupBehavior
    {
        if (null !== $objAlias = $uxon->getProperty('object_alias')) {
            if ($this->getObject()->isExactly($objAlias)) {
                throw new BehaviorConfigurationError($this, 'Cannot define CustomAttributesLookupBehavior for object ' . $this->getObject()->__toString() . ': the attributes_definition points to the same object - this will not work!');
            }
        }
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
     * Change the default properties of attributes to be created
     * 
     * @uxon-property attributes_defaults
     * @uxon-type \exface\Core\CommonLogic\Model\Attribute
     * @uxon-template {"groups": [""], "value_list_delimiter": ","}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesLookupBehavior
     */
    protected function setAttributesDefaults(UxonObject $uxon) : CustomAttributesLookupBehavior
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
            // TODO allow filtering for custom lookup attribtues somehow. For now, filterable is false by default.
            'filterable' => false,
            'value_list_delimiter' => EXF_LIST_SEPARATOR
        ]);
    }

    /**
     * Where to find values for the custom attributes
     * 
     * @uxon-property values_lookup
     * @uxon-type \exface\Core\CommonLogic\Model\Behaviors\CustomAttributesLookup
     * @uxon-required true
     * @uxon-template {"object_alias": "", "relation_to_behavior_object": "", "values_attribute_alias_column": "", "values_content_column": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesLookupBehavior
     */
    protected function setValuesLookup(UxonObject $uxon) : CustomAttributesLookupBehavior
    {
        $this->valuesLookup = new CustomAttributesLookup($this, $uxon);
        return $this;
    }

    /**
     * 
     * @return CustomAttributesLookup
     */
    public function getValuesLookup() : CustomAttributesLookup
    {
        return $this->valuesLookup;
    }
}