<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\ClassSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Factories\SelectorFactory;

/**
 * Trait with shared logic for the PrototypeSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
trait PrototypeSelectorTrait
{
    use ClassSelectorTrait;
    use FileSelectorTrait;
    
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
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeAppSelector()
     */
    public function getPrototypeAppSelector() : AppSelectorInterface
    {
        if ($this->isClassname()) {
            $string = $this->toString();
            $parts = explode(ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
        } elseif ($this->isFilepath()) {
            $path = Filemanager::pathNormalize($this->getFolderRelativeToVendorFolder());
            $parts = explode('/', $path);
        } else {
            throw new RuntimeException('Invalid prototype selector "' . $this->toString() . '": expecting a file path or a qualified class name!');
        }
        $appAlias = implode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, array_splice($parts, 0, 2));
        
        return SelectorFactory::createAppSelector($this->getWorkbench(), $appAlias);
    }
}