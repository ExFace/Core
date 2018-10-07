<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

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
interface MetaRelationInterface extends WorkbenchDependantInterface
{
    /**
     * 
     * @param Workbench $workbench
     * @param RelationTypeDataType $type
     * @param string $uid
     * @param string $alias
     * @param string $aliasModifier
     * @param string $name
     * @param MetaObjectInterface $leftObject
     * @param MetaAttributeInterface $leftKeyAttribute
     * @param string $rightObjectUid
     * @param string $rightObjectKeyAttributeUid
     */
    public function __construct(
        Workbench $workbench, 
        RelationTypeDataType $type, 
        string $uid, 
        string $alias, 
        string $aliasModifier = '', 
        string $name = null, 
        MetaObjectInterface $leftObject, 
        MetaAttributeInterface $leftKeyAttribute, 
        string $rightObjectUid, 
        string $rightObjectKeyAttributeUid = null);
    
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
    
    public function getType() : RelationTypeDataType;
    
    /**
     * Returns the UID of the object, this attribute was inherited from or NULL if it is a direct attribute of it's object
     *
     * @return string|null
     */
    public function getInheritedFromObjectId() : ?string;
    
    /**
     *
     * @param string $value
     * @return MetaRelationInterface
     */
    public function setInheritedFromObjectId($value) : MetaRelationInterface;
    
    /**
     * Returns TRUE if this Relation was inherited from a parent object
     *
     * @return bool
     */
    public function isInherited() : bool;
    
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
     * Clones the attribute keeping the model and object
     *
     * @return MetaRelationInterface
     */
    public function copy() : MetaRelationInterface;
    
    /**
     *
     * @return ModelInterface
     */
    public function getModel() : ModelInterface;
    
    /**
     * Returns TRUE if this is a reverse relation and FALSE otherwise
     *
     * @return bool
     */
    public function isReverseRelation() : bool;
    
    /**
     * Returns TRUE if this is a regular (forward) relation and FALSE otherwise
     *
     * @return bool
     */
    public function isForwardRelation() : bool;
    
    /**
     * Returns TRUE if this is a one-to-one relation and FALSE otherwise
     *
     * @return bool
     */
    public function isOneToOneRelation() : bool;
    
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
   
}