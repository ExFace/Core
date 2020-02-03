<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\CompoundAttributeInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class CompoundAttribute extends Attribute implements CompoundAttributeInterface
{
    private $components = null;
    
    public function addComponentAttribute(MetaAttributeInterface $attribute, string $valuePrefix, string $valueSuffix): CompoundAttributeInterface
    {
        $component = new CompoundAttributeComponent($this, $attribute, $valuePrefix, $valueSuffix);
        $this->components[] = $component;
        return $this;
    }

    public function getComponentAttributes(): array
    {
        if ($this->components === null) {
            $this->getModel()->getModelLoader()->loadAttributeComponents($this);
        }
        return $this->components;
    }
}