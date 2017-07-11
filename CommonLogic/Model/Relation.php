<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Workbench;

class Relation implements ExfaceClassInterface
{

    const RELATION_TYPE_FORWARD = 'n1';

    const RELATION_TYPE_REVERSE = '1n';

    const RELATION_TYPE_ONE_TO_ONE = '11';

    // Properties to be dublicated on copy()
    private $id;

    private $alias;

    private $name;

    private $main_object_id;

    private $related_object_id;

    private $related_object_key_attribute_id;

    private $related_object_key_alias;

    private $join_type = 'LEFT';

    private $foreign_key_alias;

    private $type = self::RELATION_TYPE_FORWARD;

    private $inherited_from_object_id = null;

    // Properties NOT to be dublicated on copy()
    private $exface = null;

    /**
     *
     * @param unknown $id            
     * @param unknown $alias            
     * @param unknown $name            
     * @param unknown $main_object            
     * @param unknown $foreign_key_alias            
     * @param unknown $related_object_id            
     * @param string $type
     *            one of the Relation::RELATION_TYPE_xxx constants
     */
    function __construct(Workbench $workbench, $relation_id, $alias, $name, $main_object_id, $foreign_key_alias, $related_object_id, $related_object_key_attribute_id = null, $type = 'n1')
    {
        $this->exface = $workbench;
        $this->id = $relation_id;
        $this->alias = $alias;
        $this->name = $name;
        $this->main_object_id = $main_object_id;
        $this->foreign_key_alias = $foreign_key_alias;
        $this->related_object_id = $related_object_id;
        $this->related_object_key_attribute_id = $related_object_key_attribute_id;
        $this->type = $type;
    }

    function getRelatedObject()
    {
        return $this->getModel()->getObject($this->related_object_id, $this->getAlias());
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($value)
    {
        $this->alias = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getRelatedObjectId()
    {
        return $this->related_object_id;
    }

    public function setRelatedObjectId($value)
    {
        $this->related_object_id = $value;
    }
    
    /**
     * Returns the attribute, that is the foreign key in the main object.
     * Same as calling getMainObjectKeyAttribute()
     *
     * @return Attribute
     */
    public function getForeignKeyAttribute(){
        return $this->getMainObjectKeyAttribute();
    }

    /**
     * Returns the alias of the foreign key in the main object.
     * E.g. for the relation ORDER->USER it would return USER_UID, which is a attribute of the object ORDER.
     *
     * @return string
     */
    public function getForeignKeyAlias()
    {
        return $this->foreign_key_alias;
    }

    public function setForeignKeyAlias($value)
    {
        $this->foreign_key_alias = $value;
    }

    public function getJoinType()
    {
        return $this->join_type;
    }

    public function setJoinType($value)
    {
        $this->join_type = $value;
    }

    public function getMainObject()
    {
        return $this->getModel()->getObject($this->main_object_id);
    }

    public function setMainObject(\exface\Core\CommonLogic\Model\Object $obj)
    {
        $this->main_object_id = $obj->getId();
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($value)
    {
        $this->type = $value;
    }

    /**
     * Returns the alias of the attribute, that identifies the related object in this relation.
     * In most cases it is the UID
     * of the related object, but can also be another attribute.
     * E.g. for the relation ORDER->USER it would return UID, which is the alias of the id attribute of the object USER.
     *
     * @return string
     */
    public function getRelatedObjectKeyAlias()
    {
        // If there is no special related_object_key_alias set, use the UID
        if (! $this->related_object_key_alias) {
            if ($this->related_object_key_attribute_id) {
                $this->related_object_key_alias = $this->getRelatedObject()->getAttributes()->getByAttributeId($this->related_object_key_attribute_id)->getAlias();
            } else {
                $this->related_object_key_alias = $this->getRelatedObject()->getUidAlias();
            }
        }
        return $this->related_object_key_alias;
    }

    public function setRelatedObjectKeyAlias($value)
    {
        $this->related_object_key_alias = $value;
    }

    /**
     * Returns the foreign key attribute or NULL if there is no key attribute
     *
     * FIXME Fix Reverse relations key bug. For some reason, the foreign key is set incorrectly: e.g. for exface.Core.WIDGET__PHP_ANNOTATION the
     * foreign key is FILE, but there is no FILE attribute in the WIDGET object (the UID is PATHNAME_RELATIVE).
     *
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function getMainObjectKeyAttribute()
    {
        try {
            return $this->getMainObject()->getAttribute($this->getForeignKeyAlias());
        } catch (\exface\Core\Exceptions\Model\MetaAttributeNotFoundError $e) {
            return null;
        }
    }

    public function getRelatedObjectKeyAttribute()
    {
        return $this->getRelatedAttribute($this->getRelatedObjectKeyAlias());
        // Backup of an old version, that returned an attribute withou a relation path
        // return $this->getRelatedObject()->getAttribute($this->getRelatedObjectKeyAlias());
    }

    /**
     * Returns the UID of the object, this attribute was inherited from or NULL if it is a direct attribute of it's object
     *
     * @return string
     */
    public function getInheritedFromObjectId()
    {
        return $this->inherited_from_object_id;
    }

    /**
     *
     * @param string $value            
     */
    public function setInheritedFromObjectId($value)
    {
        $this->inherited_from_object_id = $value;
    }

    /**
     * Returns TRUE if this Relation was inherited from a parent object
     *
     * @return boolean
     */
    public function isInherited()
    {
        return is_null($this->getInheritedFromObjectId()) ? true : false;
    }

    /**
     * Returns a related attribute as if it was queried via $object->getAttribute("this_relation_alias->attribute_alias").
     * An attribute returned by this function has a relation path relative to the main object of this relation!
     *
     * @param string $attribute_alias            
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function getRelatedAttribute($attribute_alias)
    {
        return $this->getMainObject()->getAttribute(RelationPath::relationPathAdd($this->getAlias(), $attribute_alias));
    }

    /**
     * Returns the relation in the opposite direction: ORDER->POSITION will become POSITION->ORDER
     *
     * @return \exface\Core\CommonLogic\Model\relation | boolean
     */
    public function getReversedRelation()
    {
        if ($this->getType() == self::RELATION_TYPE_FORWARD) {
            // If it is a regular relation, it will be a reverse one from the point of view of the related object. That is identified by the
            // alias of the object it leads to (in our case, the current object)
            $reverse = $this->getRelatedObject()->getRelation($this->getMainObject()->getAlias(), $this->getAlias());
        } elseif ($this->getType() == self::RELATION_TYPE_REVERSE || $this->getType() == self::RELATION_TYPE_ONE_TO_ONE) {
            // If it is a reverse relation, it will be a regular one from the point of view of the related object. That is identified by its alias.
            // TODO Will it also work for one-to-one relations?
            $reverse = $this->getRelatedObject()->getRelation($this->getForeignKeyAlias());
        } else {
            $reverse = false;
        }
        return $reverse;
    }

    /**
     * Clones the attribute keeping the model and object
     *
     * @return Relation
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Model\Model
     */
    public function getModel()
    {
        return $this->getWorkbench()->model();
    }

    /**
     * Returns TRUE if this is a reverse relation and FALSE otherwise
     *
     * @return boolean
     */
    public function isReverseRelation()
    {
        return $this->getType() == self::RELATION_TYPE_REVERSE ? true : false;
    }

    /**
     * Returns TRUE if this is a regular (forward) relation and FALSE otherwise
     *
     * @return boolean
     */
    public function isForwardRelation()
    {
        return $this->getType() == self::RELATION_TYPE_FORWARD ? true : false;
    }

    /**
     * Returns TRUE if this is a one-to-one relation and FALSE otherwise
     *
     * @return boolean
     */
    public function isOneToOneRelation()
    {
        return $this->getType() == self::RELATION_TYPE_ONE_TO_ONE ? true : false;
    }

    /**
     * Returns TRUE if this relation equals the given relation or is derived (inherited) from it and FALSE otherwise.
     *
     * This method will return TRUE for rel1::is(rel2) if rel1 belongs to object1 and was inherited by object2 to form
     * rel2. These relations are the same (have the same definition and the same UID), but belong to different objects.
     * The method is_exaclty() would return FALSE in this situation.
     *
     * @param Relation $other_relation            
     * @return boolean
     */
    public function is(Relation $other_relation)
    {
        return $this->getId() === $other_relation->getId() && $this->getType() === $other_relation->getType() ? true : false;
    }

    /**
     * Returns TRUE if this relation is exactly the same as the given one and belongs to the same object.
     * Otherwise returns FALSE.
     *
     * @param Relation $other_relation            
     * @return boolean
     */
    public function isExactly(Relation $other_relation)
    {
        if ($this->is($other_relation) && $this->getMainObject()->isExactly($other_relation->getMainObject())) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Returns a string representation of this relation: e.g. "ORDER_POSITION[ORDER_ID] -> ORDER[ID]".
     * 
     * This is handy to use in debug printouts and user messages.
     * 
     * @return string
     */
    public function toString(){
        return $this->getMainObject()->getAliasWithNamespace() . '[' . $this->getForeignKeyAlias() . '] -> ' . $this->getRelatedObject()->getAliasWithNamespace() . '[' . $this->getRelatedObjectKeyAlias() . ']';
    }
}
?>