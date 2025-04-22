<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for UXON snippet selectors.
 * 
 * A snippet can be identified by 
 * - fully qualified alias (with vendor and app prefix)
 * - UID
 * 
 * @author Andrej Kabachnik
 *
 */
interface UxonSnippetSelectorInterface extends AliasSelectorInterface, UidSelectorInterface
{}