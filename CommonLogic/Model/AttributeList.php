<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Factories\AttributeListFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeListInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method MetaAttributeInterface[] getAll()
 * @method MetaAttributeListInterface|MetaAttributeInterface[] getIterator()
 *        
 */
class AttributeList extends EntityList implements MetaAttributeListInterface
{

    /**
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::add()
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
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getModel()
     */
    public function getModel()
    {
        return $this->getMetaObject()->getModel();
    }

    /**
     *
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getMetaObject()
     */
    public function getMetaObject()
    {
        return $this->getParent();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see MetaAttributeListInterface::setMetaObject()
     */
    public function setMetaObject(MetaObjectInterface $meta_object)
    {
        return $this->setParent($meta_object);
    }

    /**
     *
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getByAttributeAlias()
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
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getByAttributeId()
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
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getByDataTypeAlias()
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
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getRequired()
     */
    public function getRequired()
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
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getSystem()
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
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getDefaultDisplayList()
     */
    public function getDefaultDisplayList()
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