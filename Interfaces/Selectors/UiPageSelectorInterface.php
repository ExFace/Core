<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for UI page selectors.
 * 
 * A UI page is identified by
 * - a UID
 * - a unique alias with an optional namespace (app alias)
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiPageSelectorInterface extends AliasSelectorWithOptionalNamespaceInterface, UidSelectorInterface
{    
    /**
     * Return TRUE if the selector is empty (neither alias nor UID) and FALSE otherwise.
     * 
     * New pages have empty selectors.
     * 
     * @return bool
     */
    public function isEmpty() : bool;
}