<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for user role selectors.
 * 
 * A user role can be identified by 
 * - fully qualified alias (with vendor and app prefix)
 * - UID
 * 
 * @author Andrej Kabachnik
 *
 */
interface UserRoleSelectorInterface extends AliasSelectorWithOptionalNamespaceInterface, UidSelectorInterface
{}