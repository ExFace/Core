<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\FileNotReadableError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class InstallationContextScope extends AbstractContextScope
{

    const CONTEXTS_FILENAME = '.contexts.json';

    private $context_file_contents = null;
    
    private $changed_vars = [];
    
    private $removed_vars = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getScopeId()
     */
    public function getScopeId()
    {
        return $this->getWorkbench()->getInstallationPath();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::loadContextData()
     */
    public function loadContextData(ContextInterface $context)
    {        
        $ctxtUxon = $this->getContextsUxon();
        if ($ctxtUxon->hasProperty($context->getAliasWithNamespace())) {
            $context->importUxonObject($ctxtUxon->getProperty($context->getAliasWithNamespace()));
        }
        
        return $this;
    }

    /**
     * Contexts in the installation scope get saved to the file .contexts.json in the
     * installation data folder.
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
                $this->getContextsUxon()->setProperty($context->getAliasWithNamespace(), $uxon);
                $this->changed_vars[] = $context->getAliasWithNamespace();
                if (($idx = array_search($context->getAliasWithNamespace(), $this->removed_vars)) !== false) {
                    unset($this->removed_vars[$idx]);
                }
            } else {
                $this->removeContext($context->getAliasWithNamespace());
            }
        }
        
        // Now save the cached version of the file.
        // NOTE: if nothing was cached, than we don't need to change anything.
        if ($this->context_file_contents !== null && ! $this->context_file_contents->isEmpty()) {
            if (! empty($this->changed_vars) || ! empty($this->removed_vars)) {
                // Load a fresh copy of the data (in case it was changed by another thread) and
                // only change variables that were changed or removed in this thread. This is
                // important for concurrent requests as another request might have added some
                // data that was not present when reading this requests data initially.
                $uxon = $this->getContextsUxon(true);
                foreach ($this->changed_vars as $var) {
                    $uxon->setProperty($var, $this->context_file_contents->getProperty($var));
                }
                foreach ($this->removed_vars as $var) {
                    $uxon->unsetProperty($var);
                }
                
                // Do an atomic write to the .contexts file. Using an atomic dump is important to avoid corrupted data
                // on multiple simultanious requests.
                try {
                    $this->getWorkbench()->filemanager()->dumpFile($this->getFilePathAbsolute(), $uxon->toJson());
                } catch (\Throwable $e) {
                    throw new RuntimeException('Cannot save installation context data! ' . $e->getMessage());
                }
            }
            // The installation context is actually never empty as the internal sodium secret
            // and the last_install_time are always there. Removing or emptying the file may
            // be dangerous as the secret key would get lost!
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
        $this->getContextsUxon()->unsetProperty($alias);
        $this->removed_vars[] = $alias;
        if (($idx = array_search($alias, $this->changed_vars)) !== false) {
            unset($this->changed_vars[$idx]);
        }
        return parent::removeContext($alias);
    }

    /**
     * 
     * @return string
     */
    protected function getFilePathAbsolute() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToDataFolder() . DIRECTORY_SEPARATOR . static::CONTEXTS_FILENAME;
    }
    
    /**
     * Returns the internal variable name from a given name and namespace.
     * 
     * @param string $name
     * @param string $namespace
     * @return string
     */
    protected function getVarName(string $name, string $namespace = null) : string
    {
        return '_' . ($namespace !== null ? $namespace . '_' : '') . $name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::setVariable()
     */
    public function setVariable(string $name, $value, string $namespace = null) : ContextScopeInterface
    {
        $var = $this->getVarName($name, $namespace);
        $this->getContextsUxon()->setProperty($var, $value);
        $this->changed_vars[] = $var;
        if (($idx = array_search($var, $this->removed_vars)) !== false) {
            unset($this->removed_vars[$idx]);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::unsetVariable()
     */
    public function unsetVariable(string $name, string $namespace = null) : ContextScopeInterface
    {
        $var = $this->getVarName($name, $namespace);
        $this->getContextsUxon()->unsetProperty($var);
        $this->removed_vars[] = $var;
        if (($idx = array_search($var, $this->changed_vars)) !== false) {
            unset($this->changed_vars[$idx]);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getVariable()
     */
    public function getVariable(string $name, string $namespace = null)
    {
        return $this->getContextsUxon()->getProperty('_' . ($namespace !== null ? $namespace . '_' : '') . $name);
    }
    
    /**
     * 
     * @param bool $noCache
     * @return UxonObject
     */
    protected function getContextsUxon(bool $noCache = false) : UxonObject
    {
        if ($this->context_file_contents === null || $noCache === true) {
            if (file_exists($this->getFilePathAbsolute())) {
                try {
                    $json = file_get_contents($this->getFilePathAbsolute());
                    if ($json === false) {
                        throw new FileNotReadableError('Cannot read file "' . $this->getFilePathAbsolute() . '"!');
                    }
                    $uxon = UxonObject::fromAnything($json);
                } catch (\Throwable $e) {
                    $this->getWorkbench()->getLogger()->logException(new RuntimeException('Cannot load installation context data! ' . $e->getMessage(), null, $e));
                    $uxon = new UxonObject();
                }
            } else {
                $uxon = new UxonObject();
            }
            if ($noCache === true) {
                return $uxon;
            } else {
                $this->context_file_contents = $uxon;
            }
        }
        return $this->context_file_contents;
    }
}