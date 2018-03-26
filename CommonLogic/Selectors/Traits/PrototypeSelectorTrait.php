<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\ClassSelectorInterface;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

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
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeAppSelector()
     */
    public function getPrototypeAppSelector() : AppSelectorInterface
    {
        return SelectorFactory::createAppSelector($this->getWorkbench(), $this->getPrototypeAppAlias());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getAppAlias()
     */
    public function getPrototypeAppAlias()
    {
        $string = $this->toString();
        if ($this->isClassname()) {
            $parts = explode(ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
        } elseif ($this->isFilepath()) {
            $parts = explode(FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, $string);
        } else {
            throw new RuntimeException('Invalid prototype selector "' . $this->toString() . '": expecting a file path or a qualified class name!');
        }
        return implode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, array_slice($parts, 0, 2));
    }
}