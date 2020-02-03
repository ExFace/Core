<?php
namespace exface\Core\Interfaces\Model;

/**
 * 
 *
 * @author Andrej Kabachnik
 *
 */
interface CompoundAttributeInterface extends MetaAttributeInterface
{
    /**
     * 
     * @return MetaAttributeInterface[]
     */
    public function getComponentAttributes() : array;
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @return CompoundAttributeInterface
     */
    public function addComponentAttribute(MetaAttributeInterface $attribute, string $valuePrefix, string $valueSuffix) : CompoundAttributeInterface;
}