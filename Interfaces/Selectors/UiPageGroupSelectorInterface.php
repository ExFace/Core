<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for UI page group selectors.
 * 
 * A UI page group can be identified by 
 * - fully qualified alias (with vendor and app prefix)
 * - UID
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiPageGroupSelectorInterface extends AliasSelectorInterface, UidSelectorInterface
{}