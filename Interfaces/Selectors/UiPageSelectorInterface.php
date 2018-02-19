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
     * @return boolean
     */
    public function isCmsId();
}