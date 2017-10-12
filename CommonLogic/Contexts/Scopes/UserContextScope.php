<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\User;
use exface\Core\Factories\UserFactory;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Exceptions\UserNotUniqueError;

class UserContextScope extends AbstractContextScope
{

    const CONTEXTS_FILENAME_IN_USER_DATA = '.contexts.json';

    private $user = null;

    private $user_context_file_contents = null;

    public function getScopeId()
    {
        return $this->getUsername();
    }

    public function getUsername()
    {
        return $this->getWorkbench()->getCMS()->getUserName();
    }

    /**
     * Returns the absolute path to the base installation folder (e.g.
     * c:\xampp\htdocs\exface\exface\UserData\username)
     *
     * @return string
     */
    public function getUserDataFolderAbsolutePath()
    {
        $path = $this->getWorkbench()->filemanager()->getPathToUserDataFolder() . DIRECTORY_SEPARATOR . $this->getUserDataFolderName();
        if (! file_exists($path)) {
            mkdir($path);
        }
        return $path;
    }

    public function getUserDataFolderName()
    {
        return $this->getUsername() ? $this->getUsername() : '.anonymous';
    }

    /**
     * TODO
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::loadContextData()
     */
    public function loadContextData(ContextInterface $context)
    {
        if (is_null($this->user_context_file_contents)) {
            if (file_exists($this->getFilename())) {
                try {
                    $this->user_context_file_contents = UxonObject::fromAnything(file_get_contents($this->getFilename()));
                } catch (\Throwable $e) {
                    $this->user_context_file_contents = new UxonObject();
                }
            } else {
                $this->user_context_file_contents = new UxonObject();
            }
        }
        
        if ($this->user_context_file_contents->hasProperty($context->getAliasWithNamespace())) {
            $context->importUxonObject($this->user_context_file_contents->getProperty($context->getAliasWithNamespace()));
        }
        
        return $this;
    }

    /**
     * Contexts in the user scope get saved to the file .contexts.json in the
     * user data folder of the current user.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::saveContexts()
     */
    public function saveContexts()
    {
        // Update the cached file contents with data from all loaded contexts
        // If a context is not loaded, but present in the file, it had noch
        // chance to get changed, so we just keep it in the file. If the
        // context is loaded, but empty - then we know, it should disapear from
        // the file.
        foreach ($this->getContextsLoaded() as $context) {
            $uxon = $context->exportUxonObject();
            if (! is_null($uxon) && ! $uxon->isEmpty()) {
                $this->user_context_file_contents->setProperty($context->getAliasWithNamespace(), $uxon);
            } else {
                $this->removeContext($context->getAliasWithNamespace());
            }
        }
        
        // Now save the cached version of the file.
        // NOTE: if nothing was cached, than we don't need to change anything.
        if (! is_null($this->user_context_file_contents)) {
            if (! $this->user_context_file_contents->isEmpty()) {
                file_put_contents($this->getFilename(), $this->user_context_file_contents->toJson());
            } elseif (file_exists($this->getFilename())) {
                unlink($this->getFilename());
            }
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::removeContext()
     */
    public function removeContext($alias)
    {
        $this->user_context_file_contents->unsetProperty($alias);
        return parent::removeContext($alias);
    }

    protected function getFilename()
    {
        return $this->getUserDataFolderAbsolutePath() . DIRECTORY_SEPARATOR . static::CONTEXTS_FILENAME_IN_USER_DATA;
    }

    /**
     * Returns the Exface user defined by the passed username.
     * 
     * @param string $username
     * @throws UserNotFoundError
     * @throws UserNotUniqueError
     * @return User
     */
    public function getUserByName($username)
    {
        if (! $username) {
            throw new UserNotFoundError('Empty username passed.');
        }
        
        $userModel = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $userSheet = DataSheetFactory::createFromObject($userModel);
        foreach ($userModel->getAttributes() as $attr) {
            $userSheet->getColumns()->addFromAttribute($attr);
        }
        $userSheet->getFilters()->addConditionsFromString($userModel, 'USERNAME', $username, EXF_COMPARATOR_EQUALS);
        $userSheet->dataRead();
        
        if ($userSheet->countRows() == 0) {
            throw new UserNotFoundError('No Exface user with username "' . $username . '" defined.');
        } elseif ($userSheet->countRows() == 1) {
            return UserFactory::createFromDataSheet($userSheet);
        } else {
            throw new UserNotUniqueError('More than one Exface users with username "' . $username . '" defined.');
        }
    }

    /**
     * Returns the Exface user defined by the passed UID.
     * 
     * @param string $uid
     * @throws UserNotFoundError
     * @throws UserNotUniqueError
     * @return User
     */
    public function getUserById($uid)
    {
        if (! $uid) {
            throw new UserNotFoundError('Empty UID passed.');
        }
        
        $userModel = $this->getWorkbench()->model()->getObject('exface.Core.USER');
        $userSheet = DataSheetFactory::createFromObject($userModel);
        foreach ($userModel->getAttributes() as $attr) {
            $userSheet->getColumns()->addFromAttribute($attr);
        }
        $userSheet->getFilters()->addConditionsFromString($userModel, $userModel->getUidAttributeAlias(), $uid, EXF_COMPARATOR_EQUALS);
        $userSheet->dataRead();
        
        if ($userSheet->countRows() == 0) {
            throw new UserNotFoundError('No Exface user with UID "' . $uid . '" defined.');
        } elseif ($userSheet->countRows() == 1) {
            return UserFactory::createFromDataSheet($userSheet);
        } else {
            throw new UserNotUniqueError('More than one Exface users with UID "' . $uid . '" defined.');
        }
    }

    /**
     * Returns the Exface user which is currently logged in in the CMS.
     * 
     * If no user is logged in in the CMS, an anonymous user is returned.
     * 
     * @return User
     */
    public function getUserCurrent()
    {
        if (! $this->user) {
            if ($this->getWorkbench()->getCMS()->isUserLoggedIn()) {
                $this->user = $this->getUserByName($this->getWorkbench()->getCMS()->getUserName());
            } else {
                $this->user = UserFactory::createAnonymous($this->getWorkbench());
            }
        }
        return $this->user;
    }

    /**
     * Creates the passed Exface user.
     * 
     * @param User $user
     * @return UserContextScope
     */
    public function createUser(User $user)
    {
        $user->exportDataSheet()->dataCreate();
        return $this;
    }

    /**
     * Updates the passed Exface user.
     * 
     * @param User $user
     * @return UserContextScope
     */
    public function updateUser(User $user)
    {
        $user->exportDataSheet()->dataUpdate();
        return $this;
    }

    /**
     * Deletes the passed Exface user.
     * 
     * @param User $user
     * @return UserContextScope
     */
    public function deleteUser(User $user)
    {
        $user->exportDataSheet()->dataDelete();
        return $this;
    }
}
?>