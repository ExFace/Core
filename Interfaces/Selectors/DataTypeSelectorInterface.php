<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for data type selectors.
 * 
 * A data type can be identified by 
 * - fully qualified alias (with vendor and app prefix)
 * - UID of a data type defined in the meta model
 * - file path or qualified class name of the app's PHP class (if there is one)
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataTypeSelectorInterface extends AliasSelectorInterface, UidSelectorInterface, PrototypeSelectorInterface
{}