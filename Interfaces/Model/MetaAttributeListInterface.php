<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\EntityListInterface;

interface MetaAttributeListInterface extends EntityListInterface
{
    /**
     * An attribute list stores attributes with their aliases for keys unless the keys are explicitly specified.
     * Using the alias with relation path ensures, that adding related attributes will never lead to dublicates here!
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::add()
     * @param MetaAttributeInterface $attribute
     */
    public function add($attribute, $key = null);
    
    /**
     *
     * @return ModelInterface
     */
    public function getModel();
    
    public function getMetaObject();
    
    public function setMetaObject(MetaObjectInterface $meta_object);
    
    /**
     * Returns the attribute matching the given alias or FALSE if no such attribute is found
     *
     * @param string $alias
     * @return MetaAttributeInterface|boolean
     */
    public function getByAttributeAlias($alias);
    
    /**
     * Returns the attribute matching the given UID or FALSE if no such attribute is found
     *
     * @param string $uid
     * @return MetaAttributeInterface|boolean
     */
    public function getByAttributeId($uid);
    
    /**
     * Returns a new attribute list containig only attributes marked as required
     *
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    function getRequired();
    
    /**
     * Returns system attributes.
     *
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getSystem();
    
    /**
     * Returns a list with all attributes, marked for the default display of the object sorted by default_display_order
     * The list can then be easily used to create widgets to display the object without the user having to
     * specify which particular attributes to display.
     *
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getDefaultDisplayList();
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getWritable() : MetaAttributeListInterface;
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getReadable() : MetaAttributeListInterface;
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getEditable() : MetaAttributeListInterface;
}

