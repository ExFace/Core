<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\ClassSelectorInterface;

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
     * {@inheritDoc}
     * 
     * A class path must contain at least one "\" and no "." (to make sure it is not a file path)
     * 
     * @see \exface\Core\Interfaces\Selectors\ClassSelectorInterface::isClassname()
     */
    public function isClassname()
    {
        if ($this->isClassname === null) {
            $string = $this->toString();
            $this->isClassname = (strpos($string, '\\') !== false && strpos($string, '.') === false);
        }
        return $this->isClassname;
    }
}