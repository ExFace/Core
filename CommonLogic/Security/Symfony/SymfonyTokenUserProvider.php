<?php
namespace exface\Core\CommonLogic\Security\Symfony;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;

/**
 * User provider for Symfony Security based on the UserFactory.
 * 
 * @author Andrej Kabachnik
 *
 */
class SymfonyTokenUserProvider implements UserProviderInterface
{
    private $workbench;

    private $token;
    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(WorkbenchInterface $workbench, AuthenticationTokenInterface $token)
    {
        $this->workbench = $workbench;
        $this->token = $token;
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
    public function loadUserByUsername($username)
    {
        if ($username === $this->token->getUsername()) {
            return SymfonyUserWrapper($this->token->getUser());
        }
        throw new UsernameNotFoundException('Username mismatch! Attempted to load username "' . $username . '" with token for "' . $this->token->getUsername() . '".');
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
        return SymfonyUserWrapper(UserFactory::createFromModel($this->getWorkbench(), $user->getUsername()));
    }
    
    /**
     * Whether this provider supports the given user class.
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return true;
    }
}