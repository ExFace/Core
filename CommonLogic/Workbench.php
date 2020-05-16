<?php
namespace exface\Core\CommonLogic;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'autoload.php';

use exface\Core\CommonLogic\Log\Log;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Factories\AppFactory;
use exface\Core\Factories\ModelLoaderFactory;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\Interfaces\WorkbenchCacheInterface;
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
use exface\Core\CommonLogic\Selectors\ModelLoaderSelector;
use exface\Core\Exceptions\AppComponentNotFoundError;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Events\Workbench\OnStartEvent;
use exface\Core\Events\Workbench\OnStopEvent;
use exface\Core\Interfaces\Security\SecurityManagerInterface;
use exface\Core\CommonLogic\Security\SecurityManager;
use exface\Core\DataTypes\StringDataType;

class Workbench implements WorkbenchInterface
{
    private $started = false;

    private $data;

    private $mm;

    private $db;

    private $debugger;

    private $logger;
    
    private $cache = null;

    private $context;

    private $running_apps = array();

    private $utils = null;

    private $event_manager = null;

    private $vendor_dir_path = null;

    private $installation_path = null;

    private $request_params = null;
    
    private $security = null;

    public function __construct()
    {        
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
        $config = $this->getConfig();
        if ($config->getOption('DEBUG.PRETTIFY_ERRORS')) {
            $dbg->setPrettifyErrors(true);
        }

        $this->eventManager()->dispatch(new OnStartEvent($this));
        
        // init data module
        $this->data = new DataManager($this);
        
        // load the metamodel manager
        $this->mm = new \exface\Core\CommonLogic\Model\Model($this);
        
        // Init the ModelLoader
        $model_loader_selector = new ModelLoaderSelector($this, $config->getOption('METAMODEL.LOADER_CLASS'));
        try {
            $model_loader = ModelLoaderFactory::create($model_loader_selector);
        } catch (AppComponentNotFoundError $e) {
            throw new InvalidArgumentException('No valid model loader found in current configuration - please add a valid "MODEL_LOADER" : "file_path_or_qualified_alias_or_qualified_class_name" to your config in "' . $this->filemanager()->getPathToConfigFolder() . '"', null, $e);
        }
        
        $model_connection = DataConnectionFactory::createFromPrototype($this, $config->getOption('METAMODEL.CONNECTOR'), $config->getOption('METAMODEL.CONNECTOR_CONFIG'));
        $model_loader->setDataConnection($model_connection);
        $this->model()->setModelLoader($model_loader);
        
        // Load the context
        $this->context = new ContextManager($this);
        
        $this->security = new SecurityManager($this);
        
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::isStarted()
     */
    public function isStarted() : bool
    {
        return $this->started;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::isInstalled()
     */
    public function isInstalled() : bool
    {
        return $this->getConfig()->getOption('METAMODEL.INSTALLED_ON') ? true : false;
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
        if ($this->context === null){
            throw new RuntimeException('Workbench not started: missing context manager! Did you forget Workbench->start()?');
        }
        return $this->context;
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
            $this->eventManager()->dispatch(new OnStopEvent($this));
            $this->started = false;
        }
    }

    /**
     * Returns the central event manager (dispatcher)
     *
     * @return EventManagerInterface
     */
    public function eventManager()
    {
        if ($this->event_manager === null) {
            $this->event_manager = new EventManager($this);
        }
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
    
    public function getInstallationFolderName() : string
    {        
        return StringDataType::substringAfter($this->getInstallationPath(), DIRECTORY_SEPARATOR, false, false, true);
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getSecurity()
     */
    public function getSecurity() : SecurityManagerInterface
    {
        return $this->security;
    }
    
    public function handle(TaskInterface $task) : ResultInterface
    {
        if (! $task->hasAction()) {
            throw new AppNotFoundError('Cannot handle task without an action selector!');
        }
        return $this->getApp($task->getActionSelector()->getAppAlias())->handle($task);
    }
    
    public function getAppFolder(AppSelectorInterface $selector) : string 
    {
        return str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, DIRECTORY_SEPARATOR, $selector->getAppAlias());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getCache()
     */
    public function getCache(): WorkbenchCacheInterface
    {
        if ($this->cache === null) {
            $this->cache = new WorkbenchCache($this, WorkbenchCache::createDefaultPool($this));
        }
        return $this->cache;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getUrl()
     */
    public function getUrl() : string
    {
        $url = $this->getConfig()->getOption('SERVER.BASE_URLS')->toArray()[0];
        if ($url !== null) {
            return $url;
        }
        
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getSecret()
     */
    public function getSecret() : string
    {
        $key = $this->getConfig()->getOption("ENCRYPTION.SALT");
        if ($key === null || $key === '') {
            $key = sodium_crypto_kdf_keygen();
            $key = sodium_bin2base64($key, 1);
            $this->getConfig()->setOption("ENCRYPTION.SALT", $key, AppInterface::CONFIG_SCOPE_INSTALLATION);
        }
        return $key;
    }
}