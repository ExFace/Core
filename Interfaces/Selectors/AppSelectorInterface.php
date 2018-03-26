<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for app selectors.
 * 
 * An app can be identified by 
 * - fully qualified alias (with vendor prefix)
 * - file path or qualified class name of the app's PHP class (if there is one)
 * - UID of the app's model (only if the app was already installed!)
 * 
 * @author Andrej Kabachnik
 *
 */
interface AppSelectorInterface extends AliasSelectorInterface, UidSelectorInterface, PrototypeSelectorInterface
{
    
}