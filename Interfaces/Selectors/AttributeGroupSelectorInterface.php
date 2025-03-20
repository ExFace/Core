<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for attribute group selectors.
 * 
 * An attribute group can be identified by 
 * - fully qualified alias (with vendor and app prefix)
 * - special built-in aliases like ~VISIBLE or ~ALL
 * - UID
 * 
 * @author Andrej Kabachnik
 *
 */
interface AttributeGroupSelectorInterface extends AliasSelectorWithOptionalNamespaceInterface, UidSelectorInterface
{
    public function isBuiltInGroup() : bool;
}