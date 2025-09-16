<?php
namespace exface\Core\Interfaces\Model;

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
interface MetaAttributeGroupInterface extends MetaAttributeListInterface
{
    const ALL = '~ALL';
    const VISIBLE = '~VISIBLE';
    const REQUIRED = '~REQUIRED';
    const EDITABLE = '~EDITABLE';
    const DEFAULT_DISPLAY = '~DEFAULT_DISPLAY';
    const WRITABLE = '~WRITABLE';
    const READABLE = '~READABLE';
    const COPYABLE = '~COPYABLE';
    const CUSTOM = '~CUSTOM';
    const SYSTEM = '~SYSTEM';
    
    public function getAlias() : ?string;
    
    public function setAlias(string $value) : MetaAttributeGroupInterface;
    
    /**
     * This is an alias for AttributeList->getAll()
     *
     * @return MetaAttributeListInterface||MetaAttributeInterface[]
     */
    public function getAttributes();
    
    /**
     * Returns a new attribute group, that contains all attributes of the object, that were not present in the original group
     * E.g.
     * group(~VISIBLE)->getInvertedAttributeGroup() will hold all hidden attributes.
     *
     * @return MetaAttributeGroupInterface
     */
    public function getInvertedAttributeGroup();

    /**
     * Returns TRUE if this group belongs to a related object (= has a relation path)
     * @return bool
     */
    public function isRelated() : bool;

    /**
     * 
     * @return MetaRelationPathInterface
     */
    public function getRelationPath() : ?MetaRelationPathInterface;

    /**
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $newObject
     * @return MetaAttributeGroupInterface
     */
    public function withExptendedObject(MetaObjectInterface $newObject) : MetaAttributeGroupInterface;
}