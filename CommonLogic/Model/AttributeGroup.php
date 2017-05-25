<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\AttributeGroupFactory;

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
class AttributeGroup extends AttributeList
{

    private $alias = NULL;

    const ALL = '~ALL';

    const VISIBLE = '~VISIBLE';

    const REQUIRED = '~REQUIRED';

    const EDITABLE = '~EDITABLE';

    const DEFAULT_DISPLAY = '~DEFAULT_DISPLAY';

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($value)
    {
        $this->alias = $value;
        return $this;
    }

    /**
     * This is an alias for AttributeList->getAll()
     *
     * @return Attribute[]
     */
    public function getAttributes()
    {
        return parent::getAll();
    }

    /**
     * Returns a new attribute group, that contains all attributes of the object, that were not present in the original group
     * E.g.
     * group(~VISIBLE)->getInvertedAttributeGroup() will hold all hidden attributes.
     *
     * @return \exface\Core\CommonLogic\Model\AttributeGroup
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