<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\CliAuthToken;
use exface\Core\CommonLogic\Security\Authenticators\Traits\CreateUserFromTokenTrait;
use exface\Core\Facades\ConsoleFacade;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class CliAuthenticator extends AbstractAuthenticator
{    
    use CreateUserFromTokenTrait;
    
    private $authenticatedToken = null;
    
    private $createNewUsersAsSuperuser = false;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! ($token instanceof CliAuthToken)) {
            throw new AuthenticationFailedError($this, 'Invalid token type!');
        }        
        if (ConsoleFacade::isPhpScriptRunInCli() === false) {
            throw new AuthenticationFailedError($this, "Authenticator '{$this->getName()}' can only be used to authenticate when php scripts are run from a cli environment!");
        }
        $userName = $this->getUsernameRunningPhpScript();
        if ($token->getUsername() !== $userName) {
            throw new AuthenticationFailedError($this, "Cannot authenticate user '{$token->getUsername()}' via '{$this->getName()}'");
        }
        if ($this->getCreateNewUsers() === true) {
            
            $roles = $this->getNewUserRoles();
            if (empty($roles) && $this->isNewUserSuperuser() === true) {
                $roles[] = 'exface.core.SUPERUSER';
            }
            $this->createUserWithRoles($this->getWorkbench(), $token, null, null, $roles);
        }
        $this->authenticatedToken = $token;
        
        return $token;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $token === $this->authenticatedToken;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool
    {
        return $token instanceof CliAuthToken;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return 'CliAuthenticator';
    }
    
    /**
     * Check if user running php script as write permission for a file
     * 
     * @param string $path
     * @return boolean
     */
    protected function isFileWritable(string $path) : bool
    {
        $writable_file = (file_exists($path) && is_writable($path));
        $writable_directory = (!file_exists($path) && is_writable(dirname($path)));
        
        if ($writable_file || $writable_directory) {
            return true;
        }
        return false;
    }
    
    /**
     * gets the OS username running the php script
     * 
     * @return string|NULL
     */
    protected function getUsernameRunningPhpScript() : ?string
    {
        return getenv('USER') ? getenv('USER') : getenv('USERNAME');
    }
    
    /**
     * Set if new created users should get the exface.core.SUPERUSER role when they have write permission for the System.config.json file.
     * If roles are set explicitly via the ´create_new_users_with_roles´ property this property will be ignored.
     * 
     * @uxon-property create_new_user_as_superuser_if_config_writable
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return CliAuthenticator
     */
    public function setCreateNewUsersAsSuperuserIfConfigWritable(bool $trueOrFalse) : CliAuthenticator
    {
        $this->createNewUsersAsSuperuser = $trueOrFalse;
        return $this;
    }
    
    /**
     * Get if new users should be created with ´exface.Core.Superuser´ role if the config file is writeable
     * 
     * @return bool
     */
    protected function getCreateNewUsersAsSuperuserIfConfigWritable() : bool
    {
        return $this->createNewUsersAsSuperuser;
    }
    
    /**
     * Returns true if requirements to add superuser role are matched, means if new users, that have write permission for config file, should be created
     * as superuser and if write permission for config file exists.
     * 
     * @return bool
     */
    protected function isNewUserSuperuser() : bool    
    {
        $configFilePath = $this->getWorkbench()->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . 'System.config.json';
        if ($this->getCreateNewUsersAsSuperuserIfConfigWritable() === true && $this->isFileWritable($configFilePath)) {
            return true;
        }
        return false;        
    }
}