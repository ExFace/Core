<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\DataTypes\MetaAttributeOriginDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Custom attributes are meant to be added to objects at runtime.
 * Apart from some minor convenience features, they work the same as regular attributes.
 * 
 * @see \exface\Core\Behaviors\CustomAttributeDefinitionBehavior
 */
class CustomAttribute extends Attribute
{
    private mixed $source = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @param string $name
     * @param string $alias
     * @param object $source
     */
    public function __construct(MetaObjectInterface $object, string $name, string $alias, object $source = null)
    {
        $this->source = $source;
        parent::__construct($object, $name, $alias);
    }

    /**
     * Returns the source of this custom attribute. 
     * 
     * The source represents the caller that initiated the creation of this object.
     * Bear in mind that it might be null.
     * 
     * @return mixed
     */
    public function getSource() : mixed
    {
        return $this->source;
    }

    /**
     * 
     * @return string
     */
    public function getSourceHint() : string
    {
        $src = $this->getSource();
        switch (true) {
            case $src instanceof BehaviorInterface:
                $hint = PhpClassDataType::findClassNameWithoutNamespace($src) . ' "' . $src->getName() . '"';
                break;
            case $src === null:
                $hint = 'unknown source';
                break;
            default:
                $hint = PhpClassDataType::findClassNameWithoutNamespace($src);
                break;
        }
        return $hint;
    }    

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getOrigin()
     */
    public function getOrigin() : int
    {
        return MetaAttributeOriginDataType::CUSTOM_ATTRIBUTE;
    }
}