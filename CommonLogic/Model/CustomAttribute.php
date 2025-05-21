<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\MetaAttributeOriginDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Custom attributes are meant to be added to objects at runtime.
 * Apart from some minor convenience features, they work the same as regular attributes.
 * 
 * @see \exface\Core\Behaviors\CustomAttributeDefinitionBehavior
 */
class CustomAttribute extends Attribute
{
    private mixed $source = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @param string $name
     * @param string $alias
     * @param object $source
     */
    public function __construct(MetaObjectInterface $object, string $name, string $alias, object $source = null)
    {
        $this->source = $source;
        parent::__construct($object, $name, $alias);
    }

    /**
     * Returns the source of this custom attribute. 
     * 
     * The source represents the caller that initiated the creation of this object.
     * Bear in mind that it might be null.
     * 
     * @return mixed
     */
    public function getSource() : mixed
    {
        return $this->source;
    }

    /**
     * 
     * @return string
     */
    public function getSourceHint() : string
    {
        $src = $this->getSource();
        switch (true) {
            case $src instanceof BehaviorInterface:
                $hint = PhpClassDataType::findClassNameWithoutNamespace($src) . ' "' . $src->getName() . '"';
                break;
            case $src === null:
                $hint = 'unknown source';
                break;
            default:
                $hint = PhpClassDataType::findClassNameWithoutNamespace($src);
                break;
        }
        return $hint;
    }    

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getOrigin()
     */
    public function getOrigin() : int
    {
        return MetaAttributeOriginDataType::CUSTOM_ATTRIBUTE;
    }

    /**
     * Make this attribute a relation
     *
     * Similarly to the object editor UI, you can set additional relation properties here:
     *
     * ```
     * {
     *     "relation": {
     *          "related_object_alias": "my.App.OBJ_ALIAS",
     *          "related_object_key_attribute_alias": "NON_UID_ATTRIBUTE",
     *          "relation_cardinality": "N1",
     *          "copy_with_related_object": false,
     *          "delete_with_related_object": true
     *     }
     * }
     * ```
     *
     * @uxon-property relation
     * @uxon-type \exface\Core\CommonLogic\Model\Relation
     * @uxon-template {"related_object_alias": ""}
     *
     * @param UxonObject $uxon
     * @return MetaAttributeInterface
     */
    protected function setRelation(UxonObject $uxon) : MetaAttributeInterface
    {
        $workbench = $this->getWorkbench();

        // The logic here is similar to SqlModelLoader::loadObject(). Once we have an attribute and we know, it is
        // a relation, we need to generate relation instances for this object and the related object - the forward
        // and reverse relations.

        // First create a relation for this attribute and add it to this attributes object
        $rightSelector = new MetaObjectSelector($this->getWorkbench(), $uxon->getProperty('related_object_alias'));
        $rightObj = MetaObjectFactory::createFromSelector($rightSelector);
        $rightObjUid = $rightSelector->isUid() ? $rightSelector->toString() : $rightObj->getId();
        $cardinality = $uxon->hasProperty('relation_cardinality') ? RelationCardinalityDataType::fromValue($workbench, $uxon->getProperty('relation_cardinality')) : RelationCardinalityDataType::N_TO_ONE($workbench);
        $rel = new Relation(
            $workbench,
            $cardinality,
            $this->getId(), // relation id
            $this->getAlias(), // relation alias
            '', // alias modifier allways empty for direct regular relations
            $this->getObject(), //  left object
            $this, // left key attribute
            $rightObjUid, // right object UID
            $uxon->getProperty('related_object_key_attribute_alias') // related object key attribute (UID will be used if not set)
        );
        // Set other relation properteis
        if ($uxon->getProperty('delete_with_related_object') === true) {
            $rel->setLeftObjectToBeDeletedWithRightObject(true);
        }
        if ($uxon->getProperty('copy_with_related_object') === true) {
            $rel->setLeftObjectToBeCopiedWithRightObject(true);
        }
        $this->setRelationFlag(true);
        // Add the relation to this attributes object (left object)
        $this->getObject()->addRelation($rel);

        // Now create the corresponding reverse relation and add it to the related (right) object
        $cardinality = RelationCardinalityDataType::fromValue($this->getWorkbench(), RelationCardinalityDataType::findCardinalityOfReverseRelation($cardinality));
        $rel = new Relation(
            $workbench,
            $cardinality,
            $this->getId(), // relation id
            $rightObj->getAlias(), // relation alias
            $this->getAlias(), // relation modifier: the alias of the right key attribute
            $rightObj, // left object
            $uxon->getProperty('related_object_key_attribute_alias') ? $rightObj->getAttribute($uxon->getProperty('related_object_key_attribute_alias')) : $rightObj->getUidAttribute(), // left key in the main object
            $this->getObject()->getId(), // right object UID
            $this->getId() // right object key attribute id
        );
        // Set other relation properteis
        if ($uxon->getProperty('delete_with_related_object') === true) {
            $rel->setLeftObjectToBeDeletedWithRightObject(true);
        }
        if ($uxon->getProperty('copy_with_related_object') === true) {
            $rel->setLeftObjectToBeCopiedWithRightObject(true);
        }
        // Add the relation to the other (right) object
        $rightObj->addRelation($rel);

        return $this;
    }
}