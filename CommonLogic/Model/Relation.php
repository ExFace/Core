<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Exceptions\RuntimeException;

class Relation implements MetaRelationInterface
{

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

    private $type = MetaRelationInterface::RELATION_TYPE_FORWARD;

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
     *            one of the MetaRelationInterface::RELATION_TYPE_xxx constants
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRelatedObject()
     */
    public function getRelatedObject()
    {
        return $this->getModel()->getObject($this->related_object_id, $this->getAlias());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getId()
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setId()
     */
    public function setId($value)
    {
        $this->id = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setAlias()
     */
    public function setAlias($value)
    {
        $this->alias = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setName()
     */
    public function setName($value)
    {
        $this->name = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRelatedObjectId()
     */
    public function getRelatedObjectId()
    {
        return $this->related_object_id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setRelatedObjectId()
     */
    public function setRelatedObjectId($value)
    {
        $this->related_object_id = $value;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getForeignKeyAttribute()
     */
    public function getForeignKeyAttribute(){
        return $this->getMainObjectKeyAttribute();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getForeignKeyAlias()
     */
    public function getForeignKeyAlias()
    {
        return $this->foreign_key_alias;
    }

    /**
     * 
     */
    public function setForeignKeyAlias($value)
    {
        $this->foreign_key_alias = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getJoinType()
     */
    public function getJoinType()
    {
        return $this->join_type;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setJoinType()
     */
    public function setJoinType($value)
    {
        $this->join_type = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getMainObject()
     */
    public function getMainObject()
    {
        return $this->getModel()->getObject($this->main_object_id);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setMainObject()
     */
    public function setMainObject(\exface\Core\Interfaces\Model\MetaObjectInterface $obj)
    {
        $this->main_object_id = $obj->getId();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getType()
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setType()
     */
    public function setType($value)
    {
        $this->type = $value;
    }

   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRelatedObjectKeyAlias()
    */
    public function getRelatedObjectKeyAlias()
    {
        // If there is no special related_object_key_alias set, use the UID
        if (! $this->related_object_key_alias) {
            if ($this->related_object_key_attribute_id) {
                $this->related_object_key_alias = $this->getRelatedObject()->getAttributes()->getByAttributeId($this->related_object_key_attribute_id)->getAlias();
            } else {
                $this->related_object_key_alias = $this->getRelatedObject()->getUidAttributeAlias();
            }
        }
        return $this->related_object_key_alias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setRelatedObjectKeyAlias()
     */
    public function setRelatedObjectKeyAlias($value)
    {
        $this->related_object_key_alias = $value;
    }
    
    /**
     * FIXME Fix Reverse relations key bug. For some reason, the foreign key is set incorrectly: e.g. for exface.Core.WIDGET__PHP_ANNOTATION the
     * foreign key is FILE, but there is no FILE attribute in the WIDGET object (the UID is PATHNAME_RELATIVE).
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getMainObjectKeyAttribute()
     */
    public function getMainObjectKeyAttribute()
    {
        try {
            return $this->getMainObject()->getAttribute($this->getForeignKeyAlias());
        } catch (\exface\Core\Exceptions\Model\MetaAttributeNotFoundError $e) {
            return null;
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRelatedObjectKeyAttribute()
     */
    public function getRelatedObjectKeyAttribute()
    {
        return $this->getRelatedAttribute($this->getRelatedObjectKeyAlias());
        // Backup of an old version, that returned an attribute withou a relation path
        // return $this->getRelatedObject()->getAttribute($this->getRelatedObjectKeyAlias());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getInheritedFromObjectId()
     */
    public function getInheritedFromObjectId()
    {
        return $this->inherited_from_object_id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setInheritedFromObjectId()
     */
    public function setInheritedFromObjectId($value)
    {
        $this->inherited_from_object_id = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isInherited()
     */
    public function isInherited()
    {
        return is_null($this->getInheritedFromObjectId()) ? true : false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRelatedAttribute()
     */
    public function getRelatedAttribute($attribute_alias)
    {
        return $this->getMainObject()->getAttribute(RelationPath::relationPathAdd($this->getAlias(), $attribute_alias));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getReversedRelation()
     */
    public function getReversedRelation()
    {
        if ($this->getType() == MetaRelationInterface::RELATION_TYPE_FORWARD) {
            // If it is a regular relation, it will be a reverse one from the point of view of the related object. That is identified by the
            // alias of the object it leads to (in our case, the current object)
            $reverse = $this->getRelatedObject()->getRelation($this->getMainObject()->getAlias(), $this->getAlias());
        } elseif ($this->getType() == MetaRelationInterface::RELATION_TYPE_REVERSE || $this->getType() == MetaRelationInterface::RELATION_TYPE_ONE_TO_ONE) {
            // If it is a reverse relation, it will be a regular one from the point of view of the related object. That is identified by its alias.
            // TODO Will it also work for one-to-one relations?
            $reverse = $this->getRelatedObject()->getRelation($this->getForeignKeyAlias());
        } else {
            throw new RuntimeException('Cannot reverse relation "' . $this->toString() . '" of meta object "' . $this->getMainObject()->getAliasWithNamespace() . '": invalid relation type!');
        }
        return $reverse;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::copy()
     */
    public function copy()
    {
        return clone $this;
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
    public function getModel()
    {
        return $this->getWorkbench()->model();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isReverseRelation()
     */
    public function isReverseRelation()
    {
        return $this->getType() == MetaRelationInterface::RELATION_TYPE_REVERSE ? true : false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isForwardRelation()
     */
    public function isForwardRelation()
    {
        return $this->getType() == MetaRelationInterface::RELATION_TYPE_FORWARD ? true : false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isOneToOneRelation()
     */
    public function isOneToOneRelation()
    {
        return $this->getType() == MetaRelationInterface::RELATION_TYPE_ONE_TO_ONE ? true : false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::is()
     */
    public function is(MetaRelationInterface $other_relation)
    {
        return $this->getId() === $other_relation->getId() && $this->getType() === $other_relation->getType() ? true : false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isExactly()
     */
    public function isExactly(MetaRelationInterface $other_relation)
    {
        if ($this->is($other_relation) && $this->getMainObject()->isExactly($other_relation->getMainObject())) {
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
    public function toString(){
        return $this->getMainObject()->getAliasWithNamespace() . '[' . $this->getForeignKeyAlias() . '] -> ' . $this->getRelatedObject()->getAliasWithNamespace() . '[' . $this->getRelatedObjectKeyAlias() . ']';
    }
}
?>