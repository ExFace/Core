<?php
namespace exface\Core\Interfaces\Selectors;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Selectors are strings that uniquely identify a component of the plattform or the model.
 * 
 * Selector interfaces allow to explicitly define, which types of selectors are usable for
 * a specific component. Their implementation must be able to detect the selector type
 * and to figure out, which app is able to load the component. The actual loadding is
 * performed by the app and does not depend on the implementation of the selector.
 * 
 * For example, the ActionSelectorInterface defines all ways to identify an action: by
 * alias, class name or file path. On the other hand, the MetaObjectSelectorInterface
 * states, that objects can be selected via alias or UID.
 * 
 * Now, to get an instance of an action, we need the corresponding app to create it first,
 * so the ActionFactory will use the selector it gets to get the selector of the app,
 * use it to fetch the app from the workbench and ask the app (which is a DI-container)
 * to hand out the action. Neither the factory, nor the selector need to know, how exactly
 * the app creates it's actions, where the corresponding classes are stored, etc. 
 * 
 * Selectors should be used for all components that can be referenced from UXON or any
 * kind of configuration.
 * 
 * @author Andrej Kabachnik
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