<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for user selectors.
 * 
 * A user can be identified by 
 * - username
 * - UID of the user in the metamodel
 * 
 * @author Andrej Kabachnik
 *
 */
interface UserSelectorInterface extends UidSelectorInterface
{    
    /**
     * 
     * @return bool
     */
    public function isUsername() : bool;
}