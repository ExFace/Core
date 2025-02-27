<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeCopied;

/**
 * A relation in the metamodel symbolizes a key-based relationship between to objects.
 * 
 * In UXON alias expressions, relations are symbolized by a double underscore "__". By concatenating aliases
 * via double underscore, it is possible to "travel" along relation paths: e.g. ORDER__COMPANY__COUNTRY__NAME.
 * 
 * A relation is allways to be read from left to right: e.g. ORDER__PAYEE would be the alias of the relation 
 * PAYEE of the object ORDER, where the relation's left object is ORDER and it's right object is whatever 
 * the relation PAYEE points to - in this case, the COMPANY. There is allways also the reverse relation
 * COMPANY__ORDER, or more precisely COMPANY__ORDER[PAYEE], which stands for the connection between a COMPANY 
 * to all ORDERS, where this COMPANY is the target of the PAYEE relation. The left object of that relation
 * is COMPANY and the right one - ORDER. Although both relations describe the same key set of the underlying
 * relational data model, they are two distinct relations of different type in the metamodel: a "regular" and a 
 * "reverse" one. 
 * 
 * Under the hood, a relation actually connects two attributes and not merely two objects. In the above example,
 * the ORDER object will probably have other relations to COMPANY, like CONTRACTOR, AGENT, etc. So the ORDER
 * object will have multiple attributes, that hold foreign keys of COMPANY entities. Additionally, relations are 
 * not always based on an explict foreig key. In particular relations between object from differen data sources, 
 * may be based on some string identifiers like invoice numbers, id-codes, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface MetaRelationInterface extends WorkbenchDependantInterface, iCanBeCopied
{    
    /**
     * Returns the unique id of the attribute, where the relation is defined.
     * 
     * @return string
     */
    public function getId() : string;
    
    /**
     * Returns the alias of the relation (without any modifiers).
     * 
     * @return string
     */
    public function getAlias() : string;
    
    /**
     * Returns the modifier of this relation or an empty string if no modifier exists.
     * 
     * @return string
     */
    public function getAliasModifier() : string;
    
    /**
     * Returns the full alias of the relation including the modifier.
     * @return string
     */
    public function getAliasWithModifier() : string;
    
    /**
     * Returns the relation name in the current session language.
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * 
     * @return string
     */
    public function getRightObjectId() : string;
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getRightObject() : MetaObjectInterface;
    
    /**
     * Returns the key attribute of the right object.
     * 
     * For regular relations, it is typically the primary key (UID) of the right object,
     * while for reverse relations it is the foreign key.
     * 
     * @param bool $appendRelationPath
     * @return MetaAttributeInterface
     */
    public function getRightKeyAttribute(bool $appendRelationPath = false) : MetaAttributeInterface;
       
    /**
     * Returns the attribute of the left object, that holds the relation key.
     * 
     * E.g. for the relation ORDER__PAYEE it would return the foreign key for COMPANY stored in ORDER data.
     *
     * @return MetaAttributeInterface
     */
    public function getLeftKeyAttribute() : MetaAttributeInterface;
    
    public function getLeftObject() : MetaObjectInterface;
    
    /**
     * 
     * @return RelationTypeDataType
     */
    public function getType() : RelationTypeDataType;
    
    /**
     * 
     * @return RelationCardinalityDataType
     */
    public function getCardinality() : RelationCardinalityDataType;
    
    /**
     * Returns the UID of the object, this relation was inherited from or NULL if is a native relation
     * 
     * If the relation was inherited multiple times, this method will go back exactly one step. For example, if we have a base object
     * of a data source, that is extended by OBJECT1, which in turn, is extended by OBJECT2, calling `getObjectInheritedFrom()` on an
     * relation of OBJECT2 will return OBJECT1, while doing so for OBJECT1 will return the base object.
     * 
     * If you need the original object (the one where the relation was actually defined), use
     * `->getInheritedOriginalRelation()->getLeftObject()` instead.
     *
     * @return MetaObjectInterface|null
     */
    public function getObjectInheritedFrom() : ?MetaObjectInterface;
    
    /**
     * Returns TRUE if this Relation was inherited from a parent object
     *
     * @return bool
     */
    public function isInherited() : bool;

    /**
     * Returns the source relation, where this one is originated from if it was inherited.
     * 
     * If the was inherited multiple times (because its object inherited it from one, that inherited it from another), this will 
     * return the very first definition - i.e. the one, that was not inherited by its object.
     * 
     * @return MetaRelationInterface|null
     */
    public function getInheritedOriginalRelation() : ?MetaRelationInterface;
    
    /**
     * Returns a related attribute as if it was queried via $object->getAttribute("this_relation_alias__attribute_alias").
     * 
     * The attribute returned by this function has a relation path relative to the left object of this relation!
     *
     * @param string $aliasRelativeToRightObject
     * 
     * @throws MetaAttributeNotFoundError
     * 
     * @return MetaAttributeInterface
     */
    public function getRightAttribute(string $aliasRelativeToRightObject) : MetaAttributeInterface;
    
    /**
     * Returns the relation in the opposite direction: ORDER->POSITION will become POSITION->ORDER
     * 
     * @throws RuntimeException
     *
     * @return MetaRelationInterface
     */
    public function getReversedRelation() : MetaRelationInterface;
    
    /**
     * Same as getReversedRelation()
     * 
     * @return MetaRelationInterface
     */
    public function reverse() : MetaRelationInterface;
    
    /**
     *
     * @return ModelInterface
     */
    public function getModel() : ModelInterface;
    
    /**
     * Returns TRUE if this is a reverse relation and FALSE otherwise.
     * 
     * Reverse relations have a cardinality of N (or M :) a the right end. This
     * can be a 1-to-n or an n-to-m relation. This means, that any number of right 
     * instances correspond to a left instances. 
     * 
     * Note, that an n-to-m relatoin is a reversed on for both of it's ends!
     *
     * @return bool
     */
    public function isReverseRelation() : bool;
    
    /**
     * Returns TRUE if this is a regular (forward) relation and FALSE otherwise.
     * 
     * Forward relations have a cardinality of 1 at the right end (1-to-1 or n-to-1).
     * In the world of relational databases, this would be a foreign key, which
     * means, that any number of left instances point to a single right instance.
     * 
     * Note, that a 1-to-1 relation is a regular one for both of it's ends!
     *
     * @return bool
     */
    public function isForwardRelation() : bool;
    
    /**
     * Returns TRUE if this relation equals the given relation or is derived (inherited) from it and FALSE otherwise.
     *
     * This method will return TRUE for rel1::is(rel2) if rel1 belongs to object1 and was inherited by object2 to form
     * rel2. These relations are the same (have the same definition and the same UID), but belong to different objects.
     * The method is_exaclty() would return FALSE in this situation.
     *
     * @param MetaRelationInterface $other_relation
     * @return bool
     */
    public function is(MetaRelationInterface $other_relation) : bool;
    
    /**
     * Returns TRUE if this relation is exactly the same as the given one and belongs to the same object.
     * Otherwise returns FALSE.
     *
     * @param MetaRelationInterface $other_relation
     * @return bool
     */
    public function isExactly(MetaRelationInterface $other_relation) : bool;
    
    /**
     * Returns a string representation of this relation: e.g. "ORDER_POSITION[ORDER_ID] -> ORDER[ID]".
     *
     * This is handy to use in debug printouts and user messages.
     *
     * @return string
     */
    public function toString() : string;
    
    /**
     * Returns TRUE if the left object is subject for a cascading delete on the right object and FALSE otherwise.
     * 
     * @return bool
     */
    public function isLeftObjectToBeDeletedWithRightObject() : bool;
    
    /**
     * Set to TRUE to delete the left object automatically when the right object is deleted.
     * 
     * @param bool $value
     * @return MetaRelationInterface
     */
    public function setLeftObjectToBeDeletedWithRightObject(bool $value) : MetaRelationInterface;
    
    /**
     * Returns TRUE if the left object is subject for a deep copy on the right object and FALSE otherwise.
     * 
     * @return bool
     */
    public function isLeftObjectToBeCopiedWithRightObject();
    
    /**
     * Set to TRUE to copy the left object automatically when the right object is copied.
     * 
     * @param bool $value
     * @return MetaRelationInterface
     */
    public function setLeftObjectToBeCopiedWithRightObject(bool $value) : MetaRelationInterface;
    
    /**
     * Returns TRUE if the right object is subject for a cascading delete on the left object and FALSE otherwise.
     *
     * @return bool
     */
    public function isRightObjectToBeDeletedWithLeftObject() : bool;
    
    /**
     * Set to TRUE to delete the right object automatically when the left object is deleted.
     *
     * @param bool $value
     * @return MetaRelationInterface
     */
    public function setRightObjectToBeDeletedWithLeftObject(bool $value) : MetaRelationInterface;
    
    /**
     * Returns TRUE if the right object is subject for a deep copy on the left object and FALSE otherwise.
     *
     * @return bool
     */
    public function isRightObjectToBeCopiedWithLeftObject();
    
    /**
     * Set to TRUE to copy the right object automatically when the left object is copied.
     *
     * @param bool $value
     * @return MetaRelationInterface
     */
    public function setRightObjectToBeCopiedWithLeftObject(bool $value) : MetaRelationInterface;
    
    /**
     * Returns a copy of the UXON description object for a default editor for the relation.
     * 
     * Similar to MetaAttributeInterface::getDefaultEditorUxon()
     * 
     * @return UxonObject
     */
    public function getDefaultEditorUxon() : UxonObject;

    /**
     * Returns a copy of this relation, built for a different object - one, that inherits from the original object
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $newObject
     * @return \exface\Core\Interfaces\Model\MetaRelationInterface
     */
    public function withExtendedObject(MetaObjectInterface $newObject) : MetaRelationInterface;   
}