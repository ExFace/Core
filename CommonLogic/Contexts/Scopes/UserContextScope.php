<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;

class UserContextScope extends AbstractContextScope
{
    const CONTEXTS_FILENAME_IN_USER_DATA = '.contexts.json';

    private $user_data = null;

    private $user_locale = null;
    
    private $user_context_file_contents = null;
    
    public function getScopeId(){
        return $this->getUserName();
    }

    public function getUserName()
    {
        return $this->getWorkbench()->getCMS()->getUserName();
    }

    public function getUserId()
    {
        return $this->getUserData()->getUidColumn()->getCellValue(0);
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
        return $this->getUserName() ? $this->getUserName() : '.anonymous';
    }

    /**
     * TODO
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::loadContextData()
     */
    public function loadContextData(ContextInterface $context)
    {
        if (is_null($this->user_context_file_contents)){
            if (file_exists($this->getFilename())){
                try {
                    $this->user_context_file_contents = UxonObject::fromAnything(file_get_contents($this->getFilename()));
                } catch (\Throwable $e){
                    $this->user_context_file_contents = new UxonObject();
                }
            } else {
                $this->user_context_file_contents = new UxonObject();
            }
        }
        
        if ($this->user_context_file_contents->hasProperty($context->getAliasWithNamespace())){
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
        if (!is_null($this->user_context_file_contents)){
            if (!$this->user_context_file_contents->isEmpty()){
                file_put_contents($this->getFilename(), $this->user_context_file_contents->toJson());
            } elseif (file_exists($this->getFilename())){
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
    public function removeContext($alias){
        $this->user_context_file_contents->unsetProperty($alias);
        return parent::removeContext($alias);
    }
    
    protected function getFilename(){
        return $this->getUserDataFolderAbsolutePath() . DIRECTORY_SEPARATOR . static::CONTEXTS_FILENAME_IN_USER_DATA;
    }

    /**
     * Returns a data sheet with all data from the user object
     *
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    protected function getUserData()
    {
        if (is_null($this->user_data)) {
            $user_object = $this->getWorkbench()->model()->getObject('exface.Core.USER');
            $ds = DataSheetFactory::createFromObject($user_object);
            $ds->getColumns()->addFromExpression($user_object->getUidAlias());
            $ds->getColumns()->addFromExpression('USERNAME');
            $ds->getColumns()->addFromExpression('FIRST_NAME');
            $ds->getColumns()->addFromExpression('LAST_NAME');
            $ds->addFilterFromString('USERNAME', $this->getUserName());
            $ds->dataRead();
            $this->user_data = $ds;
        }
        return $this->user_data;
    }

    /**
     * Returns the locale, set for the current user
     *
     * @return string
     */
    public function getUserLocale()
    {
        if (is_null($this->user_locale) && $cms_locale = $this->getWorkbench()->getCMS()->getUserLocale()) {
            $this->setUserLocale($cms_locale);
        }
        return $this->user_locale;
    }

    /**
     * Sets the locale for the current user
     *
     * @param string $string            
     * @return \exface\Core\CommonLogic\Contexts\Scopes\UserContextScope
     */
    public function setUserLocale($string)
    {
        $this->user_locale = $string;
        return $this;
    }
    
    /**
     * Returns TRUE if the user currently logged in is an administrator and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isUserAdmin()
    {
        return $this->getWorkbench()->getCMS()->isUserAdmin();
    }
    
    /**
     * Returns TRUE if no named user is logged in and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isUserAnonymous()
    {
        return $this->getWorkbench()->getCMS()->isUserLoggedIn() ? false : true;
    }
}
?>