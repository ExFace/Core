<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on UIDs.
 * 
 * @author Andrej Kabachnik
 *
 */
interface UidSelectorInterface extends SelectorInterface
{    
    /**
     * Returns TRUE if this selector is based on a UID and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isUid();
}