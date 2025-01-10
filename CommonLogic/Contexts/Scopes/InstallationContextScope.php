<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Saves context information in `data/.context.json`
 * 
 * Then installation context scope is very important because it contains information,
 * that is required for the workbench to run - e.g. the decryption key for
 * EncryptedDataType, the information about the installation time, etc. If the
 * file is not there or is empty, the workbench is concidered not installed correctly!
 * 
 * This is why additional safety measures need to be implemented when readin and
 * writing the context file. We've had bad issues with file system glitches leading 
 * to inability to read or write it and ultimately to going down entirely.
 * 
 * Currently we keep two versions of the file: `.context.json` and `.context.recovery.json`.
 * Both should have the same contents and are kept up-to-date simultaniously. If the main
 * file cannot be written or read, the recovery file is used. This may lead to rare data loss,
 * but should allow the workbench to survive file system locks on the context file (in the
 * hope, that the OS will not lock both files at the same time).
 * 
 * @author Andrej Kabachnik
 *
 */
class InstallationContextScope extends AbstractContextScope
{

    const CONTEXTS_FILENAME = '.contexts.json';

    const CONTEXTS_FILENAME_RECOVERY = '.contexts.recovery.json';

    private $context_file_contents = null;
    
    private $changed_vars = [];
    
    private $removed_vars = [];

    private $busyReading = false;

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
                $uxonPrevious = $uxon->copy();
                foreach ($this->changed_vars as $var) {
                    $uxon->setProperty($var, $this->context_file_contents->getProperty($var));
                }
                foreach ($this->removed_vars as $var) {
                    $uxon->unsetProperty($var);
                }
                
                // If all the above did produce any changes in the saved data, don't overwrite the
                // file - it won't change anything, there will be one I/O-operation less and we
                // will have less potential problems with concurrent requests to write the file.
                $json = $uxon->toJson();
                if ($json === $uxonPrevious->toJson()) {
                    return $this;
                }
                
                // Do an atomic write to the .contexts file. Using an atomic dump is important to avoid corrupted data
                // on multiple simultanious requests.
                $pathRegular = $this->getFilePathAbsolute(self::CONTEXTS_FILENAME);
                $pathRecovery = $this->getFilePathAbsolute(self::CONTEXTS_FILENAME_RECOVERY);
                try {
                    $this->getWorkbench()->filemanager()->dumpFile($pathRegular, $json);
                } catch (\Throwable $e) {
                    try {
                        $this->getWorkbench()->filemanager()->dumpFile($pathRecovery, $json);
                        $recoveryHint = 'Created recovery file. ';
                    } catch (\Throwable $e2) {
                        $recoveryHint = 'Failed to create recovery file - see logs. ';
                        $this->getWorkbench()->getLogger()->logException(new RuntimeException('Cannot save recovery file for installation context scope. ' . $e2->getMessage(), null, $e2));
                    }
                    $eCombined = new RuntimeException('Cannot save context data in scope ' . $this->getName() . '! ' . $recoveryHint . $e->getMessage(), null, $e);
                    if ($e2 !== null) {
                        throw $eCombined->setLogLevel(LoggerInterface::EMERGENCY);
                    } else {
                        $this->getWorkbench()->getLogger()->logException($eCombined);
                    }
                    return $this;
                }
                try {
                    $this->getWorkbench()->filemanager()->dumpFile($pathRecovery, $json);
                } catch (\Throwable $e) {
                    // Ignore errors here
                }
            }
            // The installation context is actually never empty as the internal sodium secret
            // and the last_install_time are always there. Removing or emptying the file may
            // be dangerous as the secret key would get lost!
            // FIXME this is not the case for the user scope though - need to separate the two
            // context scopes and probably let both extend an AbstractJsonFileContextScope or similar.
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
    protected function getFilePathAbsolute(string $filename = null) : string
    {
        return $this->getWorkbench()->filemanager()->getPathToDataFolder() . DIRECTORY_SEPARATOR . $filename ?? static::CONTEXTS_FILENAME;
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
        return $this->getContextsUxon()->getProperty($this->getVarName($name, $namespace));
    }
    
    /**
     * 
     * @param bool $noCache
     * @return UxonObject
     */
    protected function getContextsUxon(bool $noCache = false) : UxonObject
    {
        if ($this->context_file_contents === null || $noCache === true) {
            // Prevent loops that might happen when reading config or installation context results
            // in an error, that will cause the DebugWidgetProcessor to ask the workbench if it is
            // installed and ready to produce debug widgets. So while we are reading the context
            // we assume being not installed properly and once the context is read, we use its
            // contents to determine if we are installed or not.
            if ($this->busyReading === true) {
                return new UxonObject();
            }

            // Try to read the main context file and the recovery file sequentially. If the main
            // file fails, but the recovery is there - log an error, but continue working. If the 
            // recovery fails too, throw an emergency error and stop!
            $pathRegular = $this->getFilePathAbsolute(self::CONTEXTS_FILENAME);
            $pathRecovery = $this->getFilePathAbsolute(self::CONTEXTS_FILENAME_RECOVERY);
            $regularFileExists = file_exists($pathRegular);
            $recoveryFileExists = file_exists($pathRecovery);

            if ($regularFileExists || $recoveryFileExists) {
                $this->busyReading = true;
                try {
                    $uxon = $this->readContextsFile($pathRegular);
                } catch (\Throwable $e) {
                    try {
                        $uxon = $this->readContextsFile($pathRecovery);
                        $recoveryHint = 'Used recovery file successfully. ';
                    } catch (\Throwable $e2) {
                        $this->getWorkbench()->getLogger()->logException(new RuntimeException('Cannot load recovery data in scope "' . $this->getName() . '"', null, $e2));
                        $recoveryHint = 'Failed to read recovery file too! ';
                    }
                    $eCombined = new RuntimeException('Cannot load context data in scope "' . $this->getName() . '". ' . $recoveryHint, null, $e);
                }
                if (! $uxon) {
                    $eCombined = $eCombined ?? new RuntimeException('Cannot load context data in scope "' . $this->getName() . '": empty data read!');
                    throw $eCombined->setLogLevel(LoggerInterface::EMERGENCY);
                } elseif ($eCombined !== null) {
                    $this->getWorkbench()->getLogger()->logException($eCombined);
                }
                $this->busyReading = false;
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

    protected function readContextsFile(string $path) : ?UxonObject
    {
        if (file_exists($path)) {
            $json = file_get_contents($path);
            if ($json === false) {
                throw new FileNotReadableError('Cannot read file "' . FilePathDataType::findFileName($path) . '"!');
            }
            return UxonObject::fromAnything($json);
        } else {
            throw new FileNotFoundError('Context file "' . FilePathDataType::findFileName($path) . '" not found!');
        }
    }
}