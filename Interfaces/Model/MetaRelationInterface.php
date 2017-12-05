<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\RuntimeException;

interface MetaRelationInterface extends ExfaceClassInterface
{
    const RELATION_TYPE_FORWARD = 'n1';
    
    const RELATION_TYPE_REVERSE = '1n';
    
    const RELATION_TYPE_ONE_TO_ONE = '11';
    
    /**
     *
     * @param unknown $id
     * @param unknown $alias
     * @param unknown $name
     * @param unknown $main_object
     * @param unknown $foreign_key_alias
     * @param unknown $related_object_id
     * @param string $type
     *            one of the MetaRelationInterface::RELATION_TYPE_xxx constants
     */
    public function __construct(Workbench $workbench, $relation_id, $alias, $name, $main_object_id, $foreign_key_alias, $related_object_id, $related_object_key_attribute_id = null, $type = 'n1');
    
    public function getRelatedObject();
    
    public function getId();
    
    public function setId($value);
    
    public function getAlias();
    
    public function setAlias($value);
    
    public function getName();
    
    public function setName($value);
    
    public function getRelatedObjectId();
    
    public function setRelatedObjectId($value);
    
    /**
     * Returns the attribute, that is the foreign key in the main object.
     * Same as calling getMainObjectKeyAttribute()
     *
     * @return MetaAttributeInterface
     */
    public function getForeignKeyAttribute();
    
    /**
     * Returns the alias of the foreign key in the main object.
     * E.g. for the relation ORDER->USER it would return USER_UID, which is a attribute of the object ORDER.
     *
     * @return string
     */
    public function getForeignKeyAlias();
    
    public function setForeignKeyAlias($value);
    
    public function getJoinType();
    
    public function setJoinType($value);
    
    public function getMainObject();
    
    public function setMainObject(\exface\Core\Interfaces\Model\MetaObjectInterface $obj);
    
    public function getType();
    
    public function setType($value);
    
    /**
     * Returns the alias of the attribute, that identifies the related object in this relation.
     * In most cases it is the UID
     * of the related object, but can also be another attribute.
     * E.g. for the relation ORDER->USER it would return UID, which is the alias of the id attribute of the object USER.
     *
     * @return string
     */
    public function getRelatedObjectKeyAlias();
    
    public function setRelatedObjectKeyAlias($value);
    
    /**
     * Returns the foreign key attribute or NULL if there is no key attribute
     *
     * FIXME Fix Reverse relations key bug. For some reason, the foreign key is set incorrectly: e.g. for exface.Core.WIDGET__PHP_ANNOTATION the
     * foreign key is FILE, but there is no FILE attribute in the WIDGET object (the UID is PATHNAME_RELATIVE).
     *
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getMainObjectKeyAttribute();
    
    public function getRelatedObjectKeyAttribute();
    
    /**
     * Returns the UID of the object, this attribute was inherited from or NULL if it is a direct attribute of it's object
     *
     * @return string
     */
    public function getInheritedFromObjectId();
    
    /**
     *
     * @param string $value
     */
    public function setInheritedFromObjectId($value);
    
    /**
     * Returns TRUE if this Relation was inherited from a parent object
     *
     * @return boolean
     */
    public function isInherited();
    
    /**
     * Returns a related attribute as if it was queried via $object->getAttribute("this_relation_alias->attribute_alias").
     * An attribute returned by this function has a relation path relative to the main object of this relation!
     *
     * @param string $attribute_alias
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getRelatedAttribute($attribute_alias);
    
    /**
     * Returns the relation in the opposite direction: ORDER->POSITION will become POSITION->ORDER
     * 
     * @throws RuntimeException
     *
     * @return MetaRelationInterface
     */
    public function getReversedRelation();
    
    /**
     * Clones the attribute keeping the model and object
     *
     * @return MetaRelationInterface
     */
    public function copy();
    
    /**
     *
     * @return \exface\Core\CommonLogic\Model\Model
     */
    public function getModel();
    
    /**
     * Returns TRUE if this is a reverse relation and FALSE otherwise
     *
     * @return boolean
     */
    public function isReverseRelation();
    
    /**
     * Returns TRUE if this is a regular (forward) relation and FALSE otherwise
     *
     * @return boolean
     */
    public function isForwardRelation();
    
    /**
     * Returns TRUE if this is a one-to-one relation and FALSE otherwise
     *
     * @return boolean
     */
    public function isOneToOneRelation();
    
    /**
     * Returns TRUE if this relation equals the given relation or is derived (inherited) from it and FALSE otherwise.
     *
     * This method will return TRUE for rel1::is(rel2) if rel1 belongs to object1 and was inherited by object2 to form
     * rel2. These relations are the same (have the same definition and the same UID), but belong to different objects.
     * The method is_exaclty() would return FALSE in this situation.
     *
     * @param MetaRelationInterface $other_relation
     * @return boolean
     */
    public function is(MetaRelationInterface $other_relation);
    
    /**
     * Returns TRUE if this relation is exactly the same as the given one and belongs to the same object.
     * Otherwise returns FALSE.
     *
     * @param MetaRelationInterface $other_relation
     * @return boolean
     */
    public function isExactly(MetaRelationInterface $other_relation);
    
    /**
     * Returns a string representation of this relation: e.g. "ORDER_POSITION[ORDER_ID] -> ORDER[ID]".
     *
     * This is handy to use in debug printouts and user messages.
     *
     * @return string
     */
    public function toString();
}