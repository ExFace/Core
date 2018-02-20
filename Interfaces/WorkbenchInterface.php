<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\CommonLogic\Filemanager;

interface WorkbenchInterface
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
     * @return boolean
     */
    public function isStarted();
    
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
    public function context();
    
    /**
     *
     * @return CmsConnectorInterface
     */
    public function getCMS();
    
    /**
     *
     * @return DataManagerInterface
     */
    public function data();
    
    /**
     *
     * @return \exface\Core\CommonLogic\UiManager
     */
    public function ui();
    
    /**
     * Launches an ExFace app and returns it.
     * Apps are cached and kept running for script (request) window
     *
     * @param string $appSelectorString
     * @return AppInterface
     */
    public function getApp($selectorOrString);
    
    /**
     * Returns the core app
     *
     * @return AppInterface
     */
    public function getCoreApp();
    
    public function stop();
    
    public function handle(TaskInterface $taks);
    
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
     * Empties all caches of this workbench: internal cache, CMS cache, etc.
     *
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function clearCache();
    
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
}
