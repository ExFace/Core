<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;

/**
 * Trait with shared logic for the ClassSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
trait PrototypeSelectorTrait
{
    private $subfolder = '';
    private $classSuffix = '';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::prototypeClassExists()
     */
    public function prototypeClassExists() : bool
    {
        return class_exists($this->getPrototypeClass());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeSubfolder()
     */
    public function getPrototypeSubfolder() : string
    {
        return $this->subfolder;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::setPrototypeSubfolder()
     */
    public function setPrototypeSubfolder(string $path) : PrototypeSelectorInterface
    {
        $this->subfolder = $path;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeClassnameSuffix()
     */
    public function getPrototypeClassnameSuffix() : string
    {
        return $this->classSuffix;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::setPrototypeClassnameSuffix()
     */
    public function setPrototypeClassnameSuffix(string $string) : PrototypeSelectorInterface
    {
        $this->classSuffix = $string;
        return $this;
    }    
}