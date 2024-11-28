<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Exceptions\Model\MetaRelationAliasAmbiguousError;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaRelationBrokenError;

class Relation implements MetaRelationInterface
{

    // Properties to be dublicated on copy()
    private $id;

    private $alias;
    
    private $aliasModifier = '';

    private $name;
    
    private $leftObject = null;
    
    private $leftKeyAttribute = null;
    
    private $rightObjectUid = null;
    
    private $rightObject = null;
    
    private $rightKeyAttributeUid = null;
    
    private $rightKeyAttribute = null;

    private $type = null;
    
    private $cardinalty = null;

    private $inheritedFromObjectUid = null;

    private $inheritedOriginalRelation = null;
    
    private $leftObjectToBeDeletedWithRightObject = false;
    
    private $leftObjectToBeCopiedWithRightObject = false;
    
    private $rightObjectToBeDeletedWithLeftObject = false;
    
    private $rightObjectToBeCopiedWithLeftObject = false;

    // Properties NOT to be dublicated on copy()
    private $exface = null;

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::__construct()
     */
    public function __construct(
        Workbench $workbench,
        RelationCardinalityDataType $cardinality,
        string $uid,
        string $alias,
        string $aliasModifier,
        MetaObjectInterface $leftObject,
        MetaAttributeInterface $leftKeyAttribute,
        string $rightObjectUid,
        string $rightObjectKeyAttributeUid = null)
    {
        $this->exface = $workbench;
        $this->id = $uid;
        $this->alias = $alias;
        $this->aliasModifier = $aliasModifier;
        $this->leftObject = $leftObject;
        $this->leftKeyAttribute = $leftKeyAttribute;
        $this->rightObjectUid = $rightObjectUid;
        $this->rightKeyAttributeUid = $rightObjectKeyAttributeUid;
        $this->cardinalty = $cardinality;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getId()
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getAlias()
     */
    public function getAlias() : string
    {
        return $this->alias;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getAliasModifier()
     */
    public function getAliasModifier() : string
    {
        return $this->aliasModifier;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getAliasWithModifier()
     */
    public function getAliasWithModifier() : string
    {
        return $this->getAlias() . ($this->getAliasModifier() && $this->requiresModifier() ? '[' . $this->getAliasModifier() . ']' : '');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getName()
     */
    public function getName() : string
    {
        if ($this->name === null) {
            if ($this->isReverseRelation()) {
                $this->name = $this->getRightObject()->getName();
            } else {
                $this->name = $this->getLeftKeyAttribute()->getName();
            }
        }
        return $this->name;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getLeftObject()
     */
    public function getLeftObject() : MetaObjectInterface
    {
        return $this->leftObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getLeftKeyAttribute()
     */
    public function getLeftKeyAttribute() : MetaAttributeInterface
    {
        return $this->leftKeyAttribute;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightObject()
     */
    public function getRightObject() : MetaObjectInterface
    {
        if ($this->rightObject === null) {
            try {
                $this->rightObject = $this->getModel()->getObjectById($this->rightObjectUid);
            } catch (MetaObjectNotFoundError $e) {
                throw new MetaRelationBrokenError($this->getLeftObject(), 'Relation "' . $this->getAlias() . '" of object "' . $this->getLeftObject()->getAliasWithNamespace() . '" broken: right object not found! ' . $e->getMessage(), '7D97VLN', $e);
            }
        }
        return $this->rightObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightObjectId()
     */
    public function getRightObjectId() : string
    {
        return $this->rightObjectUid;
    }
    
    /**
     * Returns TRUE if the right key of this relation is not specified explictily (thus defaults to the UID)
     * 
     * @return bool
     */
    protected function getRightKeyIsUnspecified() : bool
    {
        return $this->rightKeyAttributeUid === null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightKeyAttribute()
     */
    public function getRightKeyAttribute(bool $appendRelationPath = false) : MetaAttributeInterface
    {
        if ($this->rightKeyAttribute === null) {
            if ($this->rightKeyAttributeUid !== null) {
                $attr = $this->getRightObject()->getAttributes()->getByAttributeId($this->rightKeyAttributeUid);
            } else {
                $attr = $this->getRightObject()->getUidAttribute();
            }
            $this->rightKeyAttribute = $appendRelationPath !== true ? $attr : $this->getRightAttribute($attr->getAlias());
        }
        // FIXME $appendRelationPath should also have effect if $this->rightKeyAttribute is set. However
        // we should check the code, that uses getRightKeyAttribute(true) first!
        return $this->rightKeyAttribute;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightAttribute()
     */
    public function getRightAttribute(string $aliasRelativeToRightObject) : MetaAttributeInterface
    {
        return $this->getLeftObject()->getAttribute(RelationPath::join($this->getAlias(), $aliasRelativeToRightObject));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getType()
     */
    public function getType() : RelationTypeDataType
    {
        if ($this->type === null) {
            switch ($this->getCardinality()->__toString()) {
                case RelationCardinalityDataType::N_TO_ONE:
                case RelationCardinalityDataType::ONE_TO_ONE:
                    $this->type = RelationTypeDataType::REGULAR($this->getWorkbench());
                    break;
                default:
                    $this->type = RelationTypeDataType::REVERSE($this->getWorkbench());
            }
        }
        return $this->type;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getCardinality()
     */
    public function getCardinality() : RelationCardinalityDataType
    {
        return $this->cardinalty;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getObjectInheritedFrom()
     */
    public function getObjectInheritedFrom() : ?MetaObjectInterface
    {
        if ($this->isInherited()) {
            return $this->getModel()->getObjectById($this->inheritedFromObjectUid);
        }
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isInherited()
     */
    public function isInherited() : bool
    {
        return $this->inheritedFromObjectUid !== null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getInheritedOriginalRelation()
     */
    public function getInheritedOriginalRelation() : ?MetaRelationInterface
    {
        return $this->inheritedOriginalRelation;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getReversedRelation()
     */
    public function getReversedRelation() : MetaRelationInterface
    {
        if ($this->isForwardRelation()) {
            $thisModifier = $this->getAliasModifier();

            // If it is a regular relation, it will be a reverse one from the point of view 
            // of the related object. That is identified by the alias of the object it leads 
            // to (in our case, the current object)
            if ($this->getCardinality()->__toString() === RelationCardinalityDataType::ONE_TO_ONE
                && $this->getRightKeyAttribute()->isRelation() 
                // IDEA Will this ever happen?? Isn't $thisModifier always empty for forward
                // relations? Not sure, what exactly this branch was for...
                && $this->getRightKeyAttribute()->getRelation()->getAlias() === $thisModifier
            ) {
                $reverse = $this->getRightKeyAttribute()->getRelation();
            } else {
                // For reverse relations we as the right object of this relation for its relation
                // with the alias of the current object. So, if we reverse the relation `ORDER` of
                // object `ORDER_POS`, we ask the `ORDER` object (right side of the relation) for
                // a relation alias `ORDER_POS` (alias of our current object). If there is only one
                // matching relation, we're done, but if there are multiple, we need a modifier and
                // in this case we use the current relation alias as that modifier.
                $reverseAlias = $this->getLeftObject()->getAlias();
                $reverseModifier = $thisModifier !== '' ? $thisModifier : $this->getAlias();

                // It is a bit strange, that the current modifier is passed here to
                // to the right object when getting the reverse relation. It seems
                // more corret to pass the alias of this relation as modifier, which
                // is done in case the modifier is empty. However, we did not dare
                // to change this code entirely - perhaps, tha current modifier is
                // important for some 1-to-1 relations or similar.
                try {
                    $reverse = $this->getRightObject()->getRelation($reverseAlias, $reverseModifier);
                } catch (MetaRelationNotFoundError $e) {
                    // If this relation was inherited from another object, the reverse relation might also
                    // have another alias - the one of its original object. Concider the example of LIST
                    // and LIST_POS objects, where the latter has a LIST relation. If we have an LIST_POS_EVENT
                    // that extends from LIST (adding calendar event properties), than LIST_POS_EVENT will
                    // have the LIST relation too. However, LIST will only have a LIST_POS relation, but not
                    // LIST_POS_EVENT. The reverse of the LIST relation of LIST_POS_EVENT would be that LIST_POS
                    // relation.
                    if ($this->isInherited()) {
                        $origRel = $this->getInheritedOriginalRelation();
                        $reverseAlias = $origRel->getLeftObject()->getAlias();
                        $reverseModifier = $thisModifier !== '' ? $thisModifier : $origRel->getAlias();
                        $reverse = $this->getRightObject()->getRelation($reverseAlias, $reverseModifier);
                    } else {
                        throw $e;
                    }
                }
            }
        } elseif ($this->isReverseRelation()) {
            // If it is a reverse relation, it will be a regular one from the point of view of the related object. 
            // That is identified by its alias.
            $reverse = $this->getRightKeyAttribute()->getRelation();
        } else {
            throw new RuntimeException('Cannot reverse relation "' . $this->toString() . '" of meta object "' . $this->getLeftObject()->getAliasWithNamespace() . '": invalid relation type!');
        }
        return $reverse;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::copy()
     */
    public function copy(MetaObjectInterface $toObject = null) : self
    {
        return clone $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::withExtendedObject()
     */
    public function withExtendedObject(MetaObjectInterface $newObject) : MetaRelationInterface
    {
        $thisObj = $this->getLeftObject();
        if (! $newObject->is($thisObj)) {
            throw new UnexpectedValueException('Cannot use Relation::withExtendedObject() on object ' . $newObject->__toString() . ' because it does not extend ' . $thisObj->__toString());
        }
        // Copy the relation to make sure all properties set after initialization are copied too, 
        // but change a couple of changes:
        // 1) Make sure the new relation has the inheriting object as left object
        // 2) If it was a self-relation, make sure the right object is the inheriting
        // object too. This way it will remain a self-relation for its new object. 
        // It is important to treat inherited relations the same way as regular ones. In particular, in
        // certain edge cases. For example, if we have a REPORT with multiple REVISIONs and  a REPORT_2, 
        // which extends REPORT, than REVISION__ID:COUNT works for REPORT and for REPORT_2. 
        // But if REVISION has a separate relation to REPORT_2, than REPORT_2 has two reverse relations
        // from REVISION and REVISION__ID:COUNT becomes umbiguous! It now must be REVISION[REPORT]__ID
        // or REVISION[REPORT_2]__ID, this InheritedRelation::needsModifier() cannot reuse the previously
        // calculated modifier.
        $clone = clone $this;
        // Save the inheritance source. If the relation is inherited multiple times because its object 
        // inherits from one, that already inherited the relation, than we need the very first relation 
        // - the one, that was NOT innherited
        $clone->inheritedOriginalRelation = $this->inheritedOriginalRelation ?? $this;
        // Save the UID of the object that we are inheriting from. In contrast to the inheritance origin
        // above, this is the immediate parent object. If we have an inheritance chain, this would be one
        // "hop" back in the chain.
        $clone->inheritedFromObjectUid = $thisObj->getId();
        // The left object of the inherited relation is its inheriting object and the left key is the attribute
        // of that object, that originated from the inherited attribute.
        $clone->leftObject = $newObject;
        $clone->leftKeyAttribute = $newObject->getAttribute($this->leftKeyAttribute->getAlias());
        // Self-relations (pointing from the parent to the parent) need to point from the extending object 
        // to the extending object.
        // For example, if we are extending the FILE object, the relation to the folder should not point
        // to the original file object, but rather to the extending object, which may have a custom base
        // address, etc.
        // In this case, we also unset the cached right-object and right-key to make the relation recalculate
        // them when needed.
        if ($thisObj->getId() === $this->rightKeyAttributeUid) {
            $clone->rightObjectUid = $newObject->getId();
            $clone->rightKeyAttribute = null;
            $clone->rightObject = null;
        }
        return $clone;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getModel()
     */
    public function getModel() : ModelInterface
    {
        return $this->getWorkbench()->model();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isReverseRelation()
     */
    public function isReverseRelation() : bool
    {
        $card = $this->getCardinality()->__toString();
        return $card === RelationCardinalityDataType::ONE_TO_N || $card === RelationCardinalityDataType::N_TO_M;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isForwardRelation()
     */
    public function isForwardRelation() : bool
    {
        return $this->isReverseRelation() === false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::is()
     */
    public function is(MetaRelationInterface $other_relation) : bool
    {
        return $this->getId() === $other_relation->getId() && $this->getCardinality()->isEqual($other_relation->getCardinality());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isExactly()
     */
    public function isExactly(MetaRelationInterface $other_relation) : bool
    {
        if ($this->is($other_relation) && $this->getLeftObject()->isExactly($other_relation->getLeftObject())) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::toString()
     */
    public function toString() : string
    {
        return $this->getLeftObject()->getAliasWithNamespace() . '[' . $this->getLeftKeyAttribute()->getAlias() . '] -> ' . $this->getRightObject()->getAliasWithNamespace() . '[' . $this->getRightKeyAttribute()->getAlias() . ']';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::reverse()
     */
    public function reverse() : MetaRelationInterface
    {
        return $this->getReversedRelation();
    }
    
    /**
     * 
     * @return bool
     */
    protected function requiresModifier() : bool
    {
        if ($this->isForwardRelation() === true) {
            return false;
        }
        
        try {
            if ($this->getLeftObject()->getRelation($this->getAlias())->isExactly($this) === false) {
                return true;
            }
        } catch (MetaRelationAliasAmbiguousError $e) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isLeftObjectToBeDeletedWithRightObject()
     */
    public function isLeftObjectToBeDeletedWithRightObject() : bool
    {
        return $this->leftObjectToBeDeletedWithRightObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setLeftObjectToBeDeletedWithRightObject()
     */
    public function setLeftObjectToBeDeletedWithRightObject(bool $value) : MetaRelationInterface
    {
        $this->leftObjectToBeDeletedWithRightObject = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isLeftObjectToBeCopiedWithRightObject() : bool
    {
        return $this->leftObjectToBeCopiedWithRightObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isLeftObjectToBeCopiedWithRightObject()
     */
    public function setLeftObjectToBeCopiedWithRightObject(bool $value) : MetaRelationInterface
    {
        $this->leftObjectToBeCopiedWithRightObject = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isRightObjectToBeDeletedWithLeftObject()
     */
    public function isRightObjectToBeDeletedWithLeftObject() : bool
    {
        return $this->rightObjectToBeDeletedWithLeftObject;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setRightObjectToBeDeletedWithLeftObject()
     */
    public function setRightObjectToBeDeletedWithLeftObject(bool $value) : MetaRelationInterface
    {
        $this->rightObjectToBeDeletedWithLeftObject = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isRightObjectToBeCopiedWithLeftObject() : bool
    {
        return $this->rightObjectToBeCopiedWithLeftObject;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isRightObjectToBeCopiedWithLeftObject()
     */
    public function setRightObjectToBeCopiedWithLeftObject(bool $value) : MetaRelationInterface
    {
        $this->rightObjectToBeCopiedWithLeftObject = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getDefaultEditorUxon()
     */
    public function getDefaultEditorUxon() : UxonObject
    {
        $relationWidgetType = $this->getWorkbench()->getConfig()->getOption('FACADES.DEFAULT_WIDGET_FOR_RELATIONS');
        
        $uxon = new UxonObject([
            "widget_type" => $relationWidgetType
        ]);
        
        if ($relationWidgetType === 'InputComboTable' && $this->getRightKeyAttribute()->isUidForObject() === false) {
            $uxon->setProperty("value_attribute_alias", $this->getRightKeyAttribute()->getAliasWithRelationPath());
        }
        
        return $uxon;
    }
}
?>