<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\ClassSelectorInterface;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;

/**
 * Trait with shared logic for the ClassSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
trait ClassSelectorTrait
{
    private $isClassname = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\ClassSelectorInterface::isClassname()
     */
    public function isClassname()
    {
        if (is_null($this->isClassname)) {
            // It's a classname if it starts with a \ and is not a filename (the latter only applies if
            // the selector can be a file name, of course)
            if (substr($this->toString(), 0, 1) === ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR) {
                $this->isClassname = $this instanceof FileSelectorInterface ? (! $this->isFilepath()) : true;
            } else {
                $this->isClassname = false;
            }
        }
        return $this->isClassname;
    }
}