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
{
    /**
     * Returns TRUE if this selector matches the built-in role exface.Core.AUTHENTICATED
     * 
     * @return bool
     */
    public function isGlobalRoleAuthenticated() : bool;

    /**
     * Returns TRUE if this selector matches the built-in role exface.Core.ANONYMOUS
     * 
     * @return bool
     */
public function isGlobalRoleAnonymous() : bool;
}