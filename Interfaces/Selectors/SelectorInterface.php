<?php
namespace exface\Core\Interfaces\Selectors;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Selectors are strings that uniquely identify a component of the plattform or the model.
 * 
 * Depending on the component, different selectors can be used: action selectors
 * can be aliases, class names or file paths, while meta object selectors can be
 * aliases or UIDs.
 * 
 * Selectors are used to fetch the respective PHP instances from the corresponding 
 * container. In most cases, they are passed to the factory of the component and the 
 * factory findes the container and fetches the instance: e.g. the ActionFactory will
 * find the app and execute AppInterface::get($selector) to get the desired instance.
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
    public function __construct(WorkbenchInterface $workbench, string $selectorString);
    
    /**
     * Returns the original selector string
     * 
     * @return string
     */
    public function toString() : string;
    
    /**
     * Returns a user-friendly name of the component type selected by this selector: e.g. "action" for an action selector.
     * @return string
     */
    public function getComponentType() : string;
}