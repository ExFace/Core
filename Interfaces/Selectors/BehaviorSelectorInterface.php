<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for metaobject behavior selectors.
 * 
 * A behavior can be identified by 
 * - UID of an object behavior instance
 * - fully qualified alias (with vendor and app prefix)
 * - file path or qualified class name of the app's PHP class (if there is one)
 * 
 * @author Andrej Kabachnik
 */
interface BehaviorSelectorInterface extends AliasSelectorInterface, PrototypeSelectorInterface, UidSelectorInterface
{}