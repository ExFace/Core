<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\EntityListInterface;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

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
    public function getModel() : ModelInterface;
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface;
    
    /**
     * 
     * @param MetaObjectInterface $meta_object
     * @return MetaAttributeInterface
     */
    public function setMetaObject(MetaObjectInterface $meta_object) : MetaAttributeListInterface;
    
    /**
     * Returns the attribute matching the given UID
     *
     * @param string $uid
     * @throws MetaAttributeNotFoundError
     * @return MetaAttributeInterface
     */
    public function getByAttributeId(string $uid) : MetaAttributeInterface;
    
    /**
     * Returns a new attribute list containig only attributes marked as required
     *
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getRequired();
    
    /**
     * Returns system attributes.
     *
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getSystem() : MetaAttributeListInterface;
    
    /**
     * Returns a list with all attributes, marked for the default display of the object sorted by default_display_order
     * The list can then be easily used to create widgets to display the object without the user having to
     * specify which particular attributes to display.
     *
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getDefaultDisplayList() : MetaAttributeListInterface;
    
    /**
     * 
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getWritable() : MetaAttributeListInterface;
    
    /**
     * 
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getReadable() : MetaAttributeListInterface;
    
    /**
     * 
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getEditable() : MetaAttributeListInterface;
    
    /**
     *
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function getCopyable() : MetaAttributeListInterface;
    
    /**
     * 
     * @return MetaAttributeListInterface|MetaAttributeInterface[]
     */
    public function sortByDefaultDisplayOrder() : MetaAttributeListInterface;
}