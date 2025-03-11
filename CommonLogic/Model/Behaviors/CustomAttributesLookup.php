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
     * 
     * @return UxonObject
     */
    public function exportUxonObject() : UxonObject
    {
        $uxon = new UxonObject([
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