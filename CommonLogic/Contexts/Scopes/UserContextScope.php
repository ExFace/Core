<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\User;
use exface\Core\Factories\UserFactory;

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
                $this->user = UserFactory::createFromModel($this->getWorkbench(), $this->getWorkbench()->getCMS()->getUserName());
            } else {
                $this->user = UserFactory::createAnonymous($this->getWorkbench());
            }
        }
        return $this->user;
    }
}
?>