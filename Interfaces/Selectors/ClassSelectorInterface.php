<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on qualified class names.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ClassSelectorInterface extends SelectorInterface
{    
    const CLASS_NAMESPACE_SEPARATOR = '\\';
    
    /**
     * Returns TRUE if this selector is based on a class name and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isClassname();
}