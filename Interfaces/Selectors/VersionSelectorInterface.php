<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on meta model aliases.
 * 
 * @author Andrej Kabachnik
 *
 */
interface VersionSelectorInterface extends SelectorInterface
{
    const VERSION_DELIMITER  = ':';
    
    /**
     * Returns TRUE if this selector has a version defined and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasVersion() : bool;
    
    /**
     *
     * @return string
     */
    public function getVersion() : string;
    
    /**
     * 
     * @return string
     */
    public function stripVersion() : string;
}