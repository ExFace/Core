<?php
namespace exface\Core\Interfaces\Selectors;

use exface\Core\Interfaces\AliasInterface;

/**
 * Interface for action selectors.
 * 
 * In general, any action can be identified by it's fully qualified alias. Additionally,
 * modeled actions (e.g. object actions) can be identified by UID while action prototypes
 * (and actions based on them without modifications) can be identified via class name or
 * class path relative to the vendor folder of the plattform.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ActionSelectorInterface extends AliasInterface, PrototypeSelectorInterface
{
    
}