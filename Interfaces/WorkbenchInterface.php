<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Mutations\MutatorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Security\SecurityManagerInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Communication\CommunicatorInterface;

interface WorkbenchInterface extends TaskHandlerInterface
{
    public function start();
    
    /**
     * 
     * @param array $config
     * @return WorkbenchInterface
     */
    public static function startNewInstance(array $config = null) : WorkbenchInterface;
    
    /**
     * Returns TRUE if start() was successfully called on this workbench instance and FALSE otherwise.
     *
     * @return bool
     */
    public function isStarted() : bool;
    
    /**
     * 
     * @return bool
     */
    public function isInstalled() : bool;
    
    /**
     *
     * @return ConfigurationInterface
     */
    public function getConfig();
    
    public function model();
    
    /**
     *
     * @throws RuntimeException if the context manager was not started yet
     *
     * @return ContextManagerInterface
     */
    public function getContext();
    
    /**
     *
     * @return DataManagerInterface
     */
    public function data();
    
    /**
     * Launches an ExFace app and returns it.
     * Apps are cached and kept running for script (request) window
     *
     * @param AppSelectorInterface|string $appSelectorString
     * @throws InvalidArgumentException
     * @return AppInterface
     */
    public function getApp($selectorOrString) : AppInterface;
    
    /**
     *
     * @param AppSelectorInterface $selector
     * @return string
     */
    public function getAppFolder(AppSelectorInterface $selector) : string;
    
    /**
     * Returns the core app
     *
     * @return AppInterface
     */
    public function getCoreApp();
    
    /**
     * 
     * @triggers \exface\Core\Events\Workbench\OnBeforeStopEvent
     * @triggers \exface\Core\Events\Workbench\OnStopEvent
     * 
     * @return void;
     */
    public function stop();
    
    /**
     * Returns the central event manager (dispatcher)
     *
     * @return EventManagerInterface
     */
    public function eventManager() : EventManagerInterface;
    
    /**
     * Returns the absolute path of the ExFace installation folder
     *
     * @return string
     */
    public function getInstallationPath() : string;
    
    /**
     * Returns the unique name of this installation on the server.
     * 
     * @return string
     */
    public function getInstallationName() : string;
    
    /**
     *
     * @return Filemanager
     */
    public function filemanager();
    
    /**
     *
     * @return \exface\Core\Interfaces\DebuggerInterface
     */
    public function getDebugger() : DebuggerInterface;
    
    public function setDebugger(DebuggerInterface $value);
    
    /**
     *
     * @return \exface\Core\Interfaces\Log\LoggerInterface
     */
    public function getLogger() : LoggerInterface;
    
    /**
     * Returns the workbench cache.
     * 
     * @return WorkbenchCacheInterface
     */
    public function getCache() : WorkbenchCacheInterface;
    
    /**
     * Returns the central security manager responsible for authentication and authorization.
     * 
     * @return SecurityManagerInterface
     */
    public function getSecurity() : SecurityManagerInterface;
    
    /**
     * Returns the absolute URL of the current workbench (ending with an `/`).
     * 
     * @return string
     */
    public function getUrl() : string;
    
    /**
     * 
     * @return CommunicatorInterface
     */
    public function getCommunicator() : CommunicatorInterface;

    /**
     * @return MutatorInterface
     */
    public function getMutator() : MutatorInterface;
}
