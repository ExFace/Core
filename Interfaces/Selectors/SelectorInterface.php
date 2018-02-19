<?php
namespace exface\Core\Interfaces\Selectors;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\ExfaceClassInterface;

/**
 * Selectors are strings that uniquely identify a UXON model entity or it's prototype.
 * 
 * Depending on the model entity, different selectors can be used: action selectors
 * can be aliases, class names or file paths, while meta object selectors can be
 * aliases or UIDs.
 * 
 * @author aka
 *
 */
interface SelectorInterface extends ExfaceClassInterface
{
    /**
     * A selector class can be created from the selector string and the target workbench.
     * 
     * @param string $selectorString
     */
    public function __construct(Workbench $workbench, $selectorString);
    
    /**
     * Returns the original selector string
     * 
     * @return string
     */
    public function toString();
}