<?php

namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

/**
 * Defines, how to read the values for the custom attributes
 * 
 * @author Andrej Kabachnik
 */
class CustomAttributesLookup implements iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;

    private ?BehaviorInterface $behavior = null;
    private ?MetaObjectInterface $lookupObject = null;

    private ?string $relationStringToBehaviorObject = null;

    private ?MetaRelationPathInterface $relationToBehaviorObject = null;

    private $valueLookupUxon = null;

    private $valueAttributeAliasColumnAlias = null;

    private $valueContentColumnAlias = null;

    private $additionalColumns = null;

    private $additionalColumnsUxon = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Model\BehaviorInterface $behavior
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     */
    public function __construct(BehaviorInterface $behavior, UxonObject $uxon)
    {
        $this->behavior = $behavior;
        $this->importUxonObject($uxon);
    }

    /**
     * 
     * @return BehaviorInterface
     */
    protected function getBehavior() : BehaviorInterface
    {
        return $this->behavior;
    }

    /**
     * 
     * @return MetaObjectInterface
     */
    protected function getBehaviorObject() : MetaObjectInterface
    {
        return $this->behavior->getObject();
    }

    /**
     * Returns the meta object, where the custom attribute values are stored
     * 
     * @return MetaObjectInterface
     */
    public function getObject() : MetaObjectInterface
    {
        return $this->lookupObject;
    }

    /**
     * Alias of the object, that contains the values of the generic attributes
     * 
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * @uxon-required true
     * 
     * @param string $aliasWithNamespace
     * @return CustomAttributesLookup
     */
    protected function setObjectAlias(string $aliasWithNamespace) : CustomAttributesLookup
    {
        $this->lookupObject = MetaObjectFactory::createFromString($this->getBehavior()->getWorkbench(), $aliasWithNamespace);
        return $this;
    }

    /**
     * Relation from the values-object to the object of the custom attributes (behavior object)
     * 
     * @uxon-property relation_to_behavior_object
     * @uxon-type metamodel:relation
     * 
     * @param mixed $relPath
     * @return CustomAttributesLookup
     */
    protected function setRelationToBehaviorObject(?string $relPath) : CustomAttributesLookup
    {
        $this->relationStringToBehaviorObject = $relPath;
        $this->relationToBehaviorObject = null;
        return $this;
    }

    /**
     * 
     * @return MetaRelationPathInterface|null
     */
    public function getRelationPathToBehaviorObject() : MetaRelationPathInterface
    {
        if ($this->relationToBehaviorObject === null) {
            if ($this->relationStringToBehaviorObject === null) {
                throw new BehaviorConfigurationError($this->getBehavior(), 'Missing `relation_to_behavior_object` property for custom attributes lookup');
            }
            $this->relationToBehaviorObject = RelationPathFactory::createFromString($this->getObject(), $this->relationStringToBehaviorObject);;
        }
        return $this->relationToBehaviorObject;
    }

    /**
     * Custom data sheet to lookup the values of the attributes
     * 
     * If not set, it will be generated automatically.
     * 
     * @uxon-property values_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"filters": {"operator": "AND","conditions":[{"expression": "","comparator": "=","value": ""}]}}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesLookup
     */
    protected function setValuesDataSheet(UxonObject $uxon) : CustomAttributesLookup
    {
        $this->valueLookupUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return UxonObject|null
     */
    public function getValuesDataSheetUxon() : ?UxonObject
    {
        return $this->valueLookupUxon;
    }

    /**
     * Column of the lookup data sheet, that will contain the aliases of the custom attributes
     * 
     * @uxon-property values_attribute_alias_column
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $col
     * @return CustomAttributesLookup
     */
    protected function setValuesAttributeAliasColumn(string $col) : CustomAttributesLookup
    {
        $this->valueAttributeAliasColumnAlias = $col;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getValuesAttributeAliasColumnAlias() : string
    {
        return $this->valueAttributeAliasColumnAlias;
    }

    /**
     * Column of the lookup data sheet, that will contain the values of the custom attributes
     * 
     * @uxon-property values_content_column
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $col
     * @return CustomAttributesLookup
     */
    protected function setValuesContentColumn(string $col) : CustomAttributesLookup
    {
        $this->valueContentColumnAlias = $col;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getValuesContentColumnAlias() : string
    {
        return $this->valueContentColumnAlias;
    }

    /**
     * Summary of getAdditionalColumns
     * @return CustomAttributesLookupColumn[]
     */
    public function getAdditionalColumns() : array
    {
        if ($this->additionalColumns === null) {
            $this->additionalColumns = [];
            if ($this->additionalColumnsUxon instanceof UxonObject) {
                foreach ($this->additionalColumnsUxon as $colUxon) {
                    $this->additionalColumns[] = new CustomAttributesLookupColumn($this, $colUxon);
                }
            }
        }
        return $this->additionalColumns;
    }

    /**
     * Additional columns to read with the values - e.g. a color column for ColoIndicator widgets.
     * 
     * For example, if you have a set of custom attributes, that should have a ColorIndicator 
     * as default display widget and that ColorIndicator uses a color from a specific data
     * column, you will need that column every time you want to display any of the custom
     * attributes. However, the color data column will likely depend on the data of the 
     * custom attribute, so it needs to be loaded with the data and transposed.
     * 
     * For example, lets assume we have a generic KPI system for clients. There is an object
     * `CLIENT_KPI`, that contains `VALUE` and `COLOR` for every KPI of a specific client. There
     * is also a `KPI_DEFINITION` object with a `CustomAttributesDefinitionBehavior`, that holds
     * information about every possible KPI. Now we need every KPI to be displayed as a 
     * ColorIndicator with the correct color:
     * 
     * ```
     * {
     *   "attributes_definition": {
     *     "object_alias": "my.APP.KPI_DEFINITION",
     *   },
     *   "attributes_defaults": {
     *     "default_display_widget": {
     *       "widget_type": "ColorIndicator",
     *       "color": {
     *         "data_column_name": "_[#~custom_attribute_alias#]_COLOR"
     *       }
     *     }
     *   },
     *   "values_lookup": {
     *     "object_alias": "my.APP.CLIENT_KPIS",
     *     "relation_to_behavior_object": "CLIENT",
     *     "values_attribute_alias_column": "KPI_DEFINITION__ALIAS",
     *     "values_content_column": "VALUE",
     *     "additional_columns": [
     *       {
     *         "attribute_alias": "COLOR",
     *         "column_name": "_[#~custom_attribute_alias#]_COLOR"
     *       }
     *     ]
     *   }
     * }
     * 
     * ```
     * 
     * @uxon-property additional_columns
     * @uxon-type \exface\Core\CommonLogic\Model\Behaviors\CustomAttributesLookupColumn[]
     * @uxon-template [{"attribute_alias": "", "column_name": ""}]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return CustomAttributesLookup
     */
    protected function setAdditionalColumns(UxonObject $uxon) : CustomAttributesLookup
    {
        $this->additionalColumnsUxon = $uxon;
        $this->additionalColumns = null;
        return $this;
    }


    /**
     * 
     * @return UxonObject
     */
    public function exportUxonObject() : UxonObject
    {
        $uxon = new UxonObject([
            'object_alias' => $this->getObject()->getAliasWithNamespace(),
            'relation_to_behavior_object' => $this->getRelationPathToBehaviorObject()->toString(),
            'values_attribute_alias_column' => $this->getValuesAttributeAliasColumnAlias(),
            'values_content_column' => $this->getValuesContentColumnAlias()
        ]);
        if (null !== $val = $this->getValuesDataSheetUxon()) {
            $uxon->setProperty('values_data_sheet', $val);
        }
        return $uxon;
    }
}