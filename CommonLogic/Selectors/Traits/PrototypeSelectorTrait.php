<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

/**
 * Trait with shared logic for the ClassSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
trait PrototypeSelectorTrait
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::prototypeClassExists()
     */
    public function prototypeClassExists()
    {
        return class_exists($this->getPrototypeClass());
    }
}