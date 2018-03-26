<?php
namespace exface\Core\CommonLogic;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'autoload.php';

use exface\Core\CommonLogic\Log\Log;
use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Factories\DataConnectorFactory;
use exface\Core\Factories\CmsConnectorFactory;
use exface\Core\Factories\AppFactory;
use exface\Core\Factories\ModelLoaderFactory;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\CoreApp;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\CommonLogic\Selectors\CmsConnectorSelector;
use exface\Core\CommonLogic\Selectors\ModelLoaderSelector;
use exface\Core\Exceptions\AppComponentNotFoundError;

class Workbench implements WorkbenchInterface
{
    private $started = false;

    private $data;

    private $cms;

    private $mm;

    private $ui;

    private $db;

    private $debugger;

    private $logger;

    private $context;

    private $running_apps = array();

    private $utils = null;

    private $event_manager = null;

    private $vendor_dir_path = null;

    private $installation_path = null;

    private $request_params = null;

    public function __construct()
    {
        if (substr(phpversion(), 0, 1) == 5) {
            require_once 'Php5Compatibility.php';
        }
        
        // If the config overrides the installation path, use the config value, otherwise go one level up from the vendor folder.
        if ($this->getConfig()->hasOption('FOLDERS.INSTALLATION_PATH_ABSOLUTE') && $installation_path = $this->getConfig()->getOption("FOLDERS.INSTALLATION_PATH_ABSOLUTE")) {
            $this->setInstallationPath($installation_path);
        } 
        
        // If the current config uses the live autoloader, load it right next
        // to the one from composer.
        if ($this->getConfig()->getOption('DEBUG.LIVE_CLASS_AUTOLOADER')){
            require_once 'splClassLoader.php';
            $classLoader = new \SplClassLoader(null, array(
                $this->getVendorDirPath()
            ));
            $classLoader->register();
        }
        
        // Load the internal constants file
        require_once ('Constants.php');
    }

    public function __destruct()
    {
        $this->stop();
    }

    public function start()
    {
        // Init logger
        $this->getLogger();

        // Start the error handler
        $dbg = new Debugger($this->logger);
        $this->setDebugger($dbg);
        if ($this->getConfig()->getOption('DEBUG.PRETTIFY_ERRORS')) {
            $dbg->setPrettifyErrors(true);
        }

        // start the event dispatcher
        $this->event_manager = new EventManager($this);
        $this->event_manager->dispatch(EventFactory::createBasicEvent($this, 'Start'));
        
        // load the CMS connector
        $this->cms = CmsConnectorFactory::create(new CmsConnectorSelector($this, $this->getConfig()->getOption('CMS_CONNECTOR')));
        // init data module
        $this->data = new DataManager($this);
        
        // load the metamodel manager
        $this->mm = new \exface\Core\CommonLogic\Model\Model($this);
        
        // Init the ModelLoader
        $model_loader_selector = new ModelLoaderSelector($this, $this->getConfig()->getOption('MODEL_LOADER'));
        try {
            $model_loader = ModelLoaderFactory::create($model_loader_selector);
        } catch (AppComponentNotFoundError $e) {
            throw new InvalidArgumentException('No valid model loader found in current configuration - please add a valid "MODEL_LOADER" : "file_path_or_qualified_alias_or_qualified_class_name" to your config in "' . $this->filemanager()->getPathToConfigFolder() . '"', null, $e);
        }
        $model_connection = DataConnectorFactory::createFromAlias($this, $this->getConfig()->getOption('MODEL_DATA_CONNECTOR'));
        $model_loader->setDataConnection($model_connection);
        $this->model()->setModelLoader($model_loader);
        
        // Load the context
        $this->context = new ContextManager($this);
        
        // Now the workbench is fully loaded and operational
        $this->started = true;
        
        // Finally load the autoruns
        $this->autorun();
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Workbench
     */
    public static function startNewInstance()
    {
        $instance = new self();
        $instance->start();
        return $instance;
    }

    /**
     * Returns TRUE if start() was successfully called on this workbench instance and FALSE otherwise.
     *
     * @return boolean
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     *
     * @return ConfigurationInterface
     */
    public function getConfig()
    {
        return $this->getCoreApp()->getConfig();
    }

    public function model()
    {
        return $this->mm;
    }

    /**
     *
     * @throws RuntimeException if the context manager was not started yet
     * 
     * @return ContextManager
     */
    public function getContext()
    {
        if (is_null($this->context)){
            throw new RuntimeException('Workbench not started: missing context manager! Did you forget Workbench->start()?');
        }
        return $this->context;
    }

    /**
     *
     * @return CmsConnectorInterface
     */
    public function getCMS()
    {
        return $this->cms;
    }

    /**
     *
     * @return DataManagerInterface
     */
    public function data()
    {
        return $this->data;
    }
    
    /**
     * 
     * @return \exface\Core\CommonLogic\UiManager
     */
    public function ui()
    {   
        if (is_null($this->ui)){
            $this->ui = new \exface\Core\CommonLogic\UiManager($this);
        }
            
        return $this->ui;
    }

    /**
     * Launches an ExFace app and returns it.
     * Apps are cached and kept running for script (request) window
     * 
     * @param string $appSelectorString
     * @return AppInterface
     */
    public function getApp($selectorOrString)
    {
        if ($selectorOrString instanceof AppSelectorInterface) {
            $selector = $selectorOrString;
        } elseif (is_string($selectorOrString)) {
            $selector = new AppSelector($this, $selectorOrString);
        } else {
            throw new InvalidArgumentException('Invalid app selector used: ' . $selectorOrString . '!');
        }
        
        if ($app = $this->findAppRunning($selector)) {
            return $app;
        } else {
            $app = AppFactory::create($selector);
            $this->running_apps[] = $app;
            return $app;
        }
    }

    /**
     * Returns an app, defined by its UID or alias, from the running_apps.
     * 
     * @param AppSelectorInterface $selector
     * @return AppInterface|false
     */
    protected function findAppRunning(AppSelectorInterface $selector)
    {
        if ($selector->isUid() && $this->model()) {
            // Die App-UID darf nur abgefragt werden, wenn tatsaechlich eine UID ueber-
            // geben wird, sonst kommt es zu Problemen beim Update. Um die UID der App zu
            // erhalten muss ausserdem das Model bereits existieren, sonst kommt es zu
            // einem Fehler in app->getUid().
            foreach ($this->running_apps as $app) {
                if (strcasecmp($app->getUid(), $selector->toString()) === 0) {
                    return $app;
                }
            }
        } else {
            foreach ($this->running_apps as $app) {
                if (strcasecmp($app->getAliasWithNamespace(), $selector->getAppAlias()) === 0) {
                    return $app;
                }
            }
        }
        return false;
    }

    /**
     * Returns the core app
     *
     * @return CoreApp
     */
    public function getCoreApp()
    {
        return $this->getApp('exface.Core');
    }

    public function stop()
    {
        if ($this->isStarted()) {
            $this->getContext()->saveContexts();
            $this->data()->disconnectAll();
            $this->eventManager()->dispatch(EventFactory::createBasicEvent($this, 'Stop'));
        }
    }

    /**
     * Returns the central event manager (dispatcher)
     *
     * @return EventManagerInterface
     */
    public function eventManager()
    {
        return $this->event_manager;
    }

    /**
     * Returns the absolute path of the ExFace installation folder
     *
     * @return string
     */
    public function getInstallationPath()
    {
        if (is_null($this->installation_path)) {
            $this->installation_path = Filemanager::pathNormalize($this->getVendorDirPath() . DIRECTORY_SEPARATOR . '..', DIRECTORY_SEPARATOR);
        }
        return $this->installation_path;
    }
    
    /**
     * Changes the path to the installation folder and the vendor folder for this instance.
     * 
     * @param string $absolute_path
     * @return Workbench
     */
    private function setInstallationPath($absolute_path)
    {
        if ($this->isStarted()){
            throw new RuntimeException('Cannot override installation path after the workbench has started!');
        }
        
        if (! is_dir($absolute_path)){
            throw new UnexpectedValueException('Cannot override default installation path with "' . $absolute_path . '": folder does not exist!');
        }
        
        $this->installation_path = $absolute_path;
        $this->vendor_dir_path = $absolute_path . DIRECTORY_SEPARATOR . Filemanager::FOLDER_NAME_VENDOR;
        return $this;
    }
    
    private function getVendorDirPath()
    {
        if (is_null($this->vendor_dir_path)){
            $this->vendor_dir_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..';
        }
        return $this->vendor_dir_path;
    }

    /**
     *
     * @return Filemanager
     */
    public function filemanager()
    {
        return new Filemanager($this);
    }

    /**
     *
     * @return \exface\Core\Interfaces\DebuggerInterface
     */
    public function getDebugger()
    {
        return $this->debugger;
    }

    public function setDebugger(DebuggerInterface $value)
    {
        $this->debugger = $value;
        return $this;
    }

    /**
     *
     * @return \exface\Core\Interfaces\Log\LoggerInterface
     */
    public function getLogger()
    {
        if (is_null($this->logger)) {
            $this->logger = Log::getErrorLogger($this);
        }
        return $this->logger;
    }
    
    /**
     * Empties all caches of this workbench: internal cache, CMS cache, etc.
     * 
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function clearCache()
    {
        // Clear CMS cache
        $this->getCMS()->clearCmsCache();
        
        // TODO clear other caches
        
        // Clear main cache folder. Mute errors since this is method is often called in background
        // IDEA introduce a special exception instead of muting to give dependants
        // more control.
        try {
            $filemanager = $this->filemanager();
            $filemanager->emptyDir($this->filemanager()->getPathToCacheFolder());
        } catch (\Throwable $e){
            $this->getLogger()->logException($e);
        }
        return $this;
    }
    
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
    public function addAutorunApp(AppInterface $app)
    {
        $autoruns = $this->getConfig()->getOption('AUTORUN_APPS');
        $autoruns->setProperty($app->getAliasWithNamespace(), true);
        $this->getConfig()->setOption('AUTORUN_APPS', $autoruns, AppInterface::CONFIG_SCOPE_INSTALLATION);
        return $this;
    }
    
    /**
     * Removes the give app from the AUTORUN_APPS config option in the installation scope.
     * 
     * NOTE: this will completely the remove the app from the list. To disable
     * the autorun temporarily, it's flag-value in the config can be set to FALSE.
     * 
     * @param AppInterface $app
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function removeAutorunApp(AppInterface $app)
    {
        $autoruns = $this->getConfig()->getOption('AUTORUN_APPS');
        $autoruns->unsetProperty($app->getAliasWithNamespace());
        $this->getConfig()->setOption('AUTORUN_APPS', $autoruns, AppInterface::CONFIG_SCOPE_INSTALLATION);
        return $this;
    }
    
    /**
     * Instantiates all apps in the AUTORUN_APPS config option.
     * 
     * @return \exface\Core\CommonLogic\Workbench
     */
    protected function autorun()
    {
        try {
            $autoruns = $this->getConfig()->getOption('AUTORUN_APPS');
        } catch (ConfigOptionNotFoundError $e){
            $this->getLogger()->logException($e);
        }
        
        foreach ($autoruns as $app_alias => $flag){
            if ($flag){
                $this->getApp($app_alias);
            }
        }
        
        return $this;
    }
    
    public function handle(TaskInterface $task) : ResultInterface
    {
        if (! $task->hasAction()) {
            throw new AppNotFoundError('Cannot handle task without an action selector!');
        }
        // TODO #api-v4 remove page current in general - just use the task pages!
        if ($task->isTriggeredOnPage()) {
            $this->ui()->setPageCurrent($task->getPageTriggeredOn());
        }
        return $this->getApp($task->getActionSelector()->getAppAlias())->handle($task);
    }

}
?>