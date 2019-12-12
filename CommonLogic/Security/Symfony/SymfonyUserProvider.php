<?php
namespace exface\Core\CommonLogic\Security\Symfony;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

/**
 * User provider for Symfony Security based on the UserFactory.
 * 
 * @author Andrej Kabachnik
 *
 */
class SymfonyUserProvider implements UserProviderInterface
{
    private $workbench;

    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }

    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException:: if the user is not found
     */
    public function loadUserByUsername(string $username)
    {
        return UserFactory::createFromModel($this->getWorkbench(), $username);
    }
    
    /**
     * Refreshes the user.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException  if the user is not supported
     * @throws UsernameNotFoundException if the user is not found
     */
    public function refreshUser(UserInterface $user)
    {
        
    }
    
    /**
     * Whether this provider supports the given user class.
     *
     * @return bool
     */
    public function supportsClass(string $class)
    {
        return true;
    }
}