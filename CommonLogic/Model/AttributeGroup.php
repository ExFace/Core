<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\AttributeGroupFactory;
use exface\Core\Interfaces\Model\MetaAttributeGroupInterface;

/**
 * An attribute group contains groups any number of attributes of a single object (including inherited attributes!).
 * An attribute group can be populated either be manually or using predifined selectors. Technically an attribute list with
 * an alias and some preconfigured groups (and respective aliases) to quickly select certain types of attributes of an
 * object.
 *
 * A manually create attribute group can even contain attributes of related objects. The only limitation is, that all
 * attributes must be selectable from the parent object of the group: thus, they must be related somehow.
 *
 * IDEA use a Condition as a selector to populate the group
 *
 * @author Andrej Kabachnik
 *        
 */
class AttributeGroup extends AttributeList implements MetaAttributeGroupInterface
{

    private $alias = NULL;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeGroupInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeGroupInterface::setAlias()
     */
    public function setAlias($value)
    {
        $this->alias = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeGroupInterface::getAttributes()
     */
    public function getAttributes()
    {
        return parent::getAll();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeGroupInterface::getInvertedAttributeGroup()
     */
    public function getInvertedAttributeGroup()
    {
        $object = $this->getMetaObject();
        $group = AttributeGroupFactory::createForObject($object);
        foreach ($this->getMetaObject()->getAttributes() as $attr) {
            if (! in_array($attr, $this->getAttributes())) {
                $group->addAttribute($attr);
            }
        }
        return $group;
    }
}