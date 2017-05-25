<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Factories\AttributeListFactory;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method Attribute[] get_all()
 * @method AttributeList|Attribute[] getIterator()
 *        
 */
class AttributeList extends EntityList
{

    /**
     * An attribute list stores attributes with their aliases for keys unless the keys are explicitly specified.
     * Using the alias with relation path ensures, that adding related attributes will never lead to dublicates here!
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::add()
     * @param Attribute $attribute            
     */
    public function add($attribute, $key = null)
    {
        if (is_null($key)) {
            $key = $attribute->getAliasWithRelationPath();
        }
        return parent::add($attribute, $key);
    }

    /**
     *
     * @return model
     */
    public function getModel()
    {
        return $this->getMetaObject()->getModel();
    }

    public function getMetaObject()
    {
        return $this->getParent();
    }

    public function setMetaObject(Object $meta_object)
    {
        return $this->setParent($meta_object);
    }

    /**
     * Returns the attribute matching the given alias or FALSE if no such attribute is found
     *
     * @param string $alias            
     * @return Attribute|boolean
     */
    public function getByAttributeAlias($alias)
    {
        // Most attributes stored here will have no relation path, so for now this fallback to iterating over all members is OK.
        if ($attr = $this->get($alias)) {
            return $attr;
        } else {
            foreach ($this->getAll() as $attr) {
                if (strcasecmp($attr->getAliasWithRelationPath(), $alias) == 0) {
                    return $attr;
                }
            }
        }
        return false;
    }

    /**
     * Returns the attribute matching the given UID or FALSE if no such attribute is found
     *
     * @param string $uid            
     * @return Attribute|boolean
     */
    public function getByAttributeId($uid)
    {
        foreach ($this->getAll() as $attr) {
            if (strcasecmp($attr->getId(), $uid) == 0) {
                return $attr;
            }
        }
        return false;
    }

    /**
     * Returns a new attribute list with all attributes of the given data type
     *
     * @param string $data_type_alias            
     * @return AttributeList|Attribute[]
     */
    public function getByDataTypeAlias($data_type_alias)
    {
        $object = $this->getMetaObject();
        $result = AttributeListFactory::createForObject($object);
        foreach ($this->getAll() as $key => $attr) {
            if (strcasecmp($attr->getDataType()->getName(), $data_type_alias) == 0) {
                $result->add($attr, $key);
            }
        }
        return $result;
    }

    /**
     * Returns a new attribute list containig only attributes marked as required
     *
     * @return AttributeList|Attribute[]
     */
    function getRequired()
    {
        $object = $this->getMetaObject();
        $output = AttributeListFactory::createForObject($object);
        foreach ($this->getAll() as $key => $attr) {
            if ($attr->isRequired()) {
                $output->add($attr, $key);
            }
        }
        return $output;
    }

    /**
     * Returns system attributes.
     *
     * @return AttributeList|Attribute[]
     */
    public function getSystem()
    {
        $object = $this->getMetaObject();
        $output = AttributeListFactory::createForObject($object);
        foreach ($this->getAll() as $key => $attr) {
            if ($attr->isSystem()) {
                $output->add($attr, $key);
            }
        }
        return $output;
    }

    /**
     * Returns a list with all attributes, marked for the default display of the object sorted by default_display_order
     * The list can then be easily used to create widgets to display the object without the user having to
     * specify which particular attributes to display.
     *
     * @return AttributeList|Attribute[]
     */
    function getDefaultDisplayList()
    {
        $object = $this->getMetaObject();
        $defs = AttributeListFactory::createForObject($object);
        foreach ($this->getAll() as $attr) {
            if ($attr->getDefaultDisplayOrder()) {
                if ($attr->isRelation()) {
                    $rel_path = $attr->getAlias();
                    $rel_obj = $object->getRelatedObject($rel_path);
                    $rel_attr = $object->getAttribute(RelationPath::relationPathAdd($rel_path, $rel_obj->getLabelAlias()));
                    // Leave the name of the relation as attribute name and ensure, that it is visible
                    $rel_attr->setName($attr->getName());
                    $rel_attr->setHidden(false);
                    $defs->add($rel_attr, $attr->getDefaultDisplayOrder());
                } else {
                    $defs->add($attr, $attr->getDefaultDisplayOrder());
                }
            }
        }
        
        // Use the label attribute if there are no defaults defined
        if ($defs->count() == 0 && $label_attribute = $object->getLabelAttribute()) {
            $defs->add($label_attribute);
        }
        
        $defs->sortByKey();
        return $defs;
    }
}