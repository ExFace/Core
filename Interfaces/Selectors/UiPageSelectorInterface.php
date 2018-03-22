<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiPageSelectorInterface extends AliasSelectorInterface, UidSelectorInterface
{
    /**
     * Returns TRUE if this selector is a CMS specific page id.
     * 
     * @return bool
     */
    public function isCmsId() : bool;
    
    /**
     * Return TRUE if the selector is empty (neither alias nor UID) and FALSE otherwise.
     * 
     * New pages have empty selectors.
     * 
     * @return bool
     */
    public function isEmpty() : bool;
}