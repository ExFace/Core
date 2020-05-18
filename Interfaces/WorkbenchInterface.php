<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Security\SecurityManagerInterface;

interface WorkbenchInterface extends TaskHandlerInterface
{
    public function start();
    
    /**
     *
     * @return \exface\Core\CommonLogic\Workbench
     */
    public static function startNewInstance();
    
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
     * @return AppInterface
     */
    public function getApp($selectorOrString);
    
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
    
    public function stop();
    
    /**
     * Returns the central event manager (dispatcher)
     *
     * @return EventManagerInterface
     */
    public function eventManager();
    
    /**
     * Returns the absolute path of the ExFace installation folder
     *
     * @return string
     */
    public function getInstallationPath();
    
    /**
     *
     * @return Filemanager
     */
    public function filemanager();
    
    /**
     *
     * @return \exface\Core\Interfaces\DebuggerInterface
     */
    public function getDebugger();
    
    public function setDebugger(DebuggerInterface $value);
    
    /**
     *
     * @return \exface\Core\Interfaces\Log\LoggerInterface
     */
    public function getLogger();
    
    /**
     * Makes the given app get automatically instantiated every time the workbench
     * is started.
     *
     * The app will be added to the AUTORUN_APPS config option of the installation
     * scope.
     *
     * NOTE: Autorun apps can be temporarily disabled in the config by changing
     * their respective value to FALSE.
     *
     * @param AppInterface $app
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function addAutorunApp(AppInterface $app);
    
    /**
     * Removes the give app from the AUTORUN_APPS config option in the installation scope.
     *
     * NOTE: this will completely the remove the app from the list. To disable
     * the autorun temporarily, it's flag-value in the config can be set to FALSE.
     *
     * @param AppInterface $app
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function removeAutorunApp(AppInterface $app);
    
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
     * Returns secret that is saved as option in system config. If secret in config is empty a new one is generated and saved.
     * Secret should be saved base64 encoded!
     *
     * @return string
     */
    public function getSecret() : string;
}
