<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Model\OnMetaObjectLoadedEvent;
use exface\Core\Events\Widget\OnUiActionWidgetInitEvent;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Widgets\Container;

/**
 * Automatically adds custom attributes to the object, whenever it is loaded from into memory.
 * 
 * 
 * ## Examples
 * 
 * ```
 *  {
 *      "attribute_object_alias": "my.App.ART", // The object, that will receive the custom attributes
 *      "relation_to_values_object": "ATTR_VAL", // The object, that contins values of the attribute (?)
 *      "attribute_name_alias": "NAME", // Attribute of the definition object, that contins the future attribute name
 *      "attribute_hint_alias": "DESCRIPTION", // Attribute of the definition object, that contins the future attribute description
 *      "attribute_required_alias": "REQUIRED", // Attribute of the definition object, that contins the future attribute alias
 *      "attribute_type_alias": "TYPE", // Attribute of the definition object, that contins the future attribute type 
 *      "attribute_type_models": {
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

    private $typeDefs = [];

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
        // TODO if this widget is based on the behavior object AND it contains editable fields

        if ($widget instanceof Container) {
            foreach ($widget->getInputWidgets() as $input) {
                // TODO if input is `attribute_type_alias` - make sure, it shows a dorpdown with the
                // available types in this behavior (e.g. DATUM, PRIO, USER from above).
            }
        }
    }

    /**
     * Summary of getAttributes
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface[]
     */
    public function getCustomAttributes(MetaObjectInterface $targetObject) : array
    {
        $attrs = [];
        $sheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getObject());
        $sheet->getColumns()->addMultiple([
            // TODO all the attributes defined in this behavior
        ]);
        foreach ($sheet->getRows() as $row) {
            $name = $row['...'];
            $alias = $row['...'];
            $template = $row['TYPE']; // The key of the data type model stored in the definition row
            $attr = MetaObjectFactory::addAttributeTemporary($targetObject, $name, $alias, $template['DATATYPE']);
            unset($template['DATATYPE']);
            // TODO add this method to Attribute class.
            $attr->importUxonObject(new UxonObject([$template]));
            $attrs[] = $attr;
        }
        return $attrs;
    }

    protected function getTypeDefinition(string $typeKey) : array
    {
        return $this->typeDefs[$typeKey] ?? null;
    }

    protected function setAttributeTypeModels(UxonObject $uxon) : CustomAttributeDefinitionBehavior
    {
        $this->typeDefs = array_merge($uxon->toArray(), $this->getDefaultTypeModels());
        return $this;
    }

    protected function getDefaultTypeModels() : array
    {
        return [
            'DATE' => [
                "DATATYPE" => "exface.Core.Date"
            ],
            'TEXT' => [
                "DATATYPE" => "exface.Core.String"
            ],
            'NUMBER' => [
                "DATATYPE" => "exface.Core.Number"
            ],
            'TIME' => [
                "DATATYPE" => "exface.Core.Time"
            ]
        ];
    }

    // TODO move the below to the CustomAtteributeJsonBehavior

    protected function findAttributeDefinitionBehavior(MetaObjectInterface $obj) : ?CustomAttributeDefinitionBehavior
    {
        $hits = $obj->getBehaviors()->getByPrototypeClass(CustomAttributeDefinitionBehavior::class);
        if ($hits->isEmpty()) {
            return null;
        }
        if ($hits->count() > 1) {
            // TODO throw exception
        }

        return $hits->getFirst();
    }
}