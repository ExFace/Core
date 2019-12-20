<?php
namespace exface\Core\CommonLogic\Security\Symfony;

use Symfony\Component\Security\Core\User\UserInterface;
use exface\Core\Exceptions\UserNotFoundError;

class SymfonyUserWrapper implements UserInterface
{
    private $userModel;

    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(\exface\Core\Interfaces\UserInterface $userModel)
    {
        $this->userModel = $userModel;
    }

    /**
     * Returns the roles granted to the user.
     *
     *     public function getRoles()
     *     {
     *         return ['ROLE_USER'];
     *     }
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return string[] The user roles
     */
    public function getRoles()
    {
        return [];
    }
    
    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string|null The encoded password if any
     */
    public function getPassword()
    {
        try {
            $password = $this->userModel->getPassword();
        } catch (UserNotFoundError $e) {
            return null;
        }
        return $password;
    }
    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        try {
            $salt = $this->userModel->getUid();
        } catch (UserNotFoundError $e) {
            return null;
        }
        return $salt;
    }
    
    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        $this->userModel->getUsername();
    }
    
    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
        // TODO
    }
}