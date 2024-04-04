<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Model\AttributeGroup;
use exface\Core\Interfaces\Model\MetaAttributeGroupInterface;
use exface\Core\Interfaces\Model\MetaAttributeListInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

abstract class AttributeGroupFactory extends AbstractStaticFactory
{

    /**
     *
     * @param MetaObjectInterface $object            
     * @param string $alias            
     * @return AttributeGroup
     */
    public static function createForObject(MetaObjectInterface $object, $alias = null)
    {
        $exface = $object->getWorkbench();
        $group = new AttributeGroup($exface, $object);
        $group->setAlias($alias);
        
        if (substr($alias, 0, 1) === '~') {
            if (strcasecmp($alias, MetaAttributeGroupInterface::ALL) === 0) {
                // The ~ALL group should list visible hidden attributes at the very end
                $hidden_attrs = [];
                foreach ($object->getAttributes() as $attr) {
                    if ($attr->isHidden() === false) {
                        $group->add($attr);
                    } else {
                        $hidden_attrs[] = $attr;
                    }
                }
                if (empty($hidden_attrs) === false) {
                    foreach ($hidden_attrs as $attr) {
                        $group->add($attr);
                    }
                }
            } else {
                $spells = explode('~', substr($alias, 1));
                foreach (static::getAttributesByMagic($object->getAttributes(), $spells) as $attr) {
                    $group->add($attr);
                }
            }
        } else {
            // TODO Load alises from group models (as soon as attribute groups become available in the model)
        }
        return $group;
    }
    
    /**
     * 
     * @param MetaAttributeListInterface $attributeList
     * @param array $spells
     * @return MetaAttributeListInterface
     */
    protected static function getAttributesByMagic(MetaAttributeListInterface $attributeList, array $spells) : MetaAttributeListInterface
    {
        if (empty($spells)) {
            return $attributeList;
        }
        
        $spell = array_shift($spells);
        if (substr($spell, 0, 1) === '!') {
            $invert = true;
            $alias = '~' . substr($spell, 1);
        } else {
            $invert = false;
            $alias = '~' . $spell;
        }
        
        switch ($alias) {
            case MetaAttributeGroupInterface::VISIBLE:
                $attributeList = $attributeList->filter(function(MetaAttributeInterface $attr) use ($invert) {
                    return $invert XOR ! $attr->isHidden();
                });
                break;
            case MetaAttributeGroupInterface::EDITABLE:
                $attributeList = $attributeList->filter(function(MetaAttributeInterface $attr) use ($invert) {
                   return $invert XOR $attr->isEditable(); 
                });
                break;
            case MetaAttributeGroupInterface::REQUIRED:
                $attributeList = $attributeList->filter(function(MetaAttributeInterface $attr) use ($invert) {
                    return $invert XOR $attr->isRequired();
                });
                break;
            case MetaAttributeGroupInterface::DEFAULT_DISPLAY:
                $attributeList = $attributeList->filter(function(MetaAttributeInterface $attr) use ($invert) {
                    return $invert XOR $attr->getDefaultDisplayOrder() > 0;
                });
                // If no attributes are marked with a default display position, include the label
                // if there is one
                if ($attributeList->isEmpty() === true && $attributeList->getMetaObject()->hasLabelAttribute() === true) {
                    $attributeList->add($attributeList->getMetaObject()->getLabelAttribute());
                }
                $attributeList->sortByDefaultDisplayOrder();
                break;
            case MetaAttributeGroupInterface::WRITABLE:
                $attributeList = $attributeList->filter(function(MetaAttributeInterface $attr) use ($invert) {
                    return $invert XOR $attr->isWritable();
                });
                break;
            case MetaAttributeGroupInterface::READABLE:
                $attributeList = $attributeList->filter(function(MetaAttributeInterface $attr) use ($invert) {
                    return $invert XOR $attr->isReadable();
                });
                break;
            case MetaAttributeGroupInterface::COPYABLE:
                $attributeList = $attributeList->filter(function(MetaAttributeInterface $attr) use ($invert) {
                    return $invert XOR $attr->isCopyable();
                });
                break;
        }
        
        return static::getAttributesByMagic($attributeList, $spells);
    }
}
?>