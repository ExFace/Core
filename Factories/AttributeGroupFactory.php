<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Model\AttributeGroup;
use exface\Core\Interfaces\Model\MetaAttributeGroupInterface;

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
        switch ($alias) {
            case MetaAttributeGroupInterface::ALL:
                // The ~ALL should list visible hidden attributes at the very end
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
                
                break;
            case MetaAttributeGroupInterface::VISIBLE:
                foreach ($object->getAttributes() as $attr) {
                    if (! $attr->isHidden()) {
                        $group->add($attr);
                    }
                }
                break;
            case MetaAttributeGroupInterface::EDITABLE:
                foreach ($object->getAttributes() as $attr) {
                    if ($attr->isEditable()) {
                        $group->add($attr);
                    }
                }
                break;
            case MetaAttributeGroupInterface::REQUIRED:
                foreach ($object->getRequiredAttributes() as $attr) {
                    $group->add($attr);
                }
                break;
            case MetaAttributeGroupInterface::DEFAULT_DISPLAY:
                foreach ($object->getAttributes()->getDefaultDisplayList() as $attr) {
                    $group->add($attr);
                }
                break;
            case MetaAttributeGroupInterface::WRITABLE:
                foreach ($object->getAttributes() as $attr) {
                    if ($attr->isWritable()) {
                        $group->add($attr);
                    }
                }
                break;
            case MetaAttributeGroupInterface::READABLE:
                foreach ($object->getAttributes() as $attr) {
                    if ($attr->isReadable()) {
                        $group->add($attr);
                    }
                }
                break;
            default:
                // TODO load group from DB
                break;
        }
        return $group;
    }
}
?>