<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Exceptions\SecurityException;
use exface\Core\Interfaces\UserInterface;
use exface\Core\CommonLogic\Filemanager;

class UserContextScope extends InstallationContextScope
{
    private $user = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getScopeId()
     */
    public function getScopeId()
    {
        return $this->getUsername();
    }

    /**
     * 
     */
    public function getUsername() : ?string
    {
        return $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername();
    }

    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface\data\users\<username>)
     *
     * @return string
     */
    public function getUserDataFolderAbsolutePath() : string
    {
        $path = $this->getWorkbench()->filemanager()->getPathToUserDataFolder() . DIRECTORY_SEPARATOR . $this->getUserDataFolderName();
        if (! file_exists($path)) {
            Filemanager::pathConstruct($path);
        }
        return $path;
    }

    /**
     * 
     * @return string
     */
    public function getUserDataFolderName() : string
    {
        return $this->getUsername() ? $this->getUsername() : '.anonymous';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\InstallationContextScope::getFilePathAbsolute()
     */
    protected function getFilePathAbsolute() : string
    {
        return $this->getUserDataFolderAbsolutePath() . DIRECTORY_SEPARATOR . static::CONTEXTS_FILENAME;
    }

    /**
     * @deprecated use $workbench->getSecurity()->getAuthenticatedUser() instead
     * @return UserInterface
     */
    public function getUserCurrent() : UserInterface
    {
        $user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser();
        // Check if the user has changed since the last access to the user scope.
        // This is not allowed as this would result in multiple users sharing the same scope!
        if ($this->user !== null && $this->user->isAnonymous() === false && $this->user->getUsername() !== $user->getUsername()) {
            throw new SecurityException('Authenticated user changed after the user context scope was initialized!');
        }
        // Save the current user for further checks
        if ($this->user === null) {
            $this->user = $user;
        }
        return $user;
    }
}