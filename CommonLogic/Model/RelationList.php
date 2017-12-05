<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Factories\AttributeListFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class RelationList extends EntityList
{

    /**
     * An attribute list stores attributes with their aliases for keys unless the keys are explicitly specified.
     * Using the alias with relation path ensures, that adding related attributes will never lead to dublicates here!
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::add()
     * @param MetaRelationInterface $attribute            
     */
    public function add($relaion, $key = null)
    {
        if (is_null($key)) {
            $key = $relaion->getAliasWithRelationPath();
        }
        return parent::add($relaion, $key);
    }

    /**
     *
     * @return model
     */
    public function getModel()
    {
        return $this->getMetaObject()->getModel();
    }

    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject()
    {
        return $this->getParent();
    }

    public function setMetaObject(MetaObjectInterface $meta_object)
    {
        return $this->setParent($meta_object);
    }

    /**
     *
     * @return MetaRelationInterface[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    /**
     * Returns the attribute matching the given alias or FALSE if no such attribute is found
     *
     * @param string $alias            
     * @return MetaAttributeInterface|boolean
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
     * @return MetaAttributeInterface|boolean
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
     * Returns a new attribute list containig only attributes marked as required
     *
     * @return AttributeList
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
     * Returns a list with all attributes, marked for the default display of the object sorted by default_display_order
     * The list can then be easily used to create widgets to display the object without the user having to
     * specify which particular attributes to display.
     *
     * @return AttributeList
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
                    $rel_attr = $object->getAttribute(RelationPath::relationPathAdd($rel_path, $rel_obj->getLabelAttributeAlias()));
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
        if ($defs->isEmpty() && $label_attribute = $object->getLabelAttribute()) {
            $defs->add($label_attribute);
        }
        
        $defs->sortByKey();
        return $defs;
    }
}