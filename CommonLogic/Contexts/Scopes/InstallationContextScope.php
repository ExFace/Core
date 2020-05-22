<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class InstallationContextScope extends AbstractContextScope
{

    const CONTEXTS_FILENAME = '.contexts.json';

    private $context_file_contents = null;

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
            } else {
                $this->removeContext($context->getAliasWithNamespace());
            }
        }
        
        // Now save the cached version of the file.
        // NOTE: if nothing was cached, than we don't need to change anything.
        if ($this->context_file_contents !== null) {
            if (! $this->context_file_contents->isEmpty()) {
                file_put_contents($this->getFilePathAbsolute(), $this->context_file_contents->toJson());
            } elseif (file_exists($this->getFilePathAbsolute())) {
                unlink($this->getFilePathAbsolute());
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
        $this->getContextsUxon()->unsetProperty($alias);
        return parent::removeContext($alias);
    }

    /**
     * @return string
     */
    protected function getFilePathAbsolute() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToDataFolder() . DIRECTORY_SEPARATOR . static::CONTEXTS_FILENAME;
    }
    
    /**
     * 
     * @param string $name
     * @param mixed $value
     * @return ContextScopeInterface
     */
    public function setVariable(string $name, $value) : ContextScopeInterface
    {
        $this->getContextsUxon()->setProperty('_' . $name, $value);
        return $this;
    }
    
    /**
     * 
     * @param string $name
     * @return ContextScopeInterface
     */
    public function unsetVariable(string $name) : ContextScopeInterface
    {
        $this->getContextsUxon()->unsetProperty('_' . $name);
        return $this;
    }
    
    /**
     * 
     * @param string $name
     * @return mixed
     */
    public function getVariable(string $name)
    {
        return $this->getContextsUxon()->getProperty('_' . $name);
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getContextsUxon() : UxonObject
    {
        if ($this->context_file_contents === null) {
            if (file_exists($this->getFilePathAbsolute())) {
                try {
                    $this->context_file_contents = UxonObject::fromAnything(file_get_contents($this->getFilePathAbsolute()));
                } catch (\Throwable $e) {
                    $this->context_file_contents = new UxonObject();
                }
            } else {
                $this->context_file_contents = new UxonObject();
            }
        }
        return $this->context_file_contents;
    }
}