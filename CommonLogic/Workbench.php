<?php
namespace exface\Core\CommonLogic;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'autoload.php';

use exface\Core\CommonLogic\Log\Log;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Factories\AppFactory;
use exface\Core\Factories\ModelLoaderFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\DebuggerInterface;
use exface\Core\Interfaces\WorkbenchCacheInterface;
use exface\Core\CoreApp;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
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
use exface\Core\Events\Workbench\OnBeforeStopEvent;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\Model\App;

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
    
    private $running_apps_selectors = [];

    private $utils = null;

    private $event_manager = null;

    private $vendor_dir_path = null;

    private $installation_path = null;
    
    private $installation_name = null;

    private $request_params = null;
    
    private $security = null;

    public function __construct(array $config = null)
    {   
        $this->vendor_dir_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..';
        $this->installation_path = Filemanager::pathNormalize($this->vendor_dir_path . DIRECTORY_SEPARATOR . '..', DIRECTORY_SEPARATOR);
        
        $cfg = $this->getConfig();
        
        if ($config !== null) {
            foreach ($config as $option => $value) {
                $cfg->setOption($option, $value);
            }
        }        
        
        // Similarly, use the installation folder name as the unique name of the installation
        // if not specified in the config (and save this for later)
        if (! ($instName = $cfg->getOption('SERVER.INSTALLTION_NAME'))) {
            $instName = FilePathDataType::findFileName($this->getInstallationPath(), false);
            $cfg->setOption('SERVER.INSTALLTION_NAME', $instName, App::CONFIG_SCOPE_SYSTEM);
        }
        $this->installation_name = $instName;
        
        
        // If the current config uses the live autoloader, load it right next
        // to the one from composer.
        if ($cfg->getOption('DEBUG.LIVE_CLASS_AUTOLOADER')){
            require_once 'splClassLoader.php';
            $classLoader = new \SplClassLoader(null, [$this->vendor_dir_path]);
            $classLoader->register();
        }
        
        // Load the internal constants file
        require_once ('Constants.php');
    }

    /**
     * Make sure the workbench is stopped properly before the instance is destroyed.
     * @return void
     */    
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::start()
     */
    public function start()
    {
        $config = $this->getConfig();
        
        // Init logger
        $logger = $this->getLogger();

        // Start the error handler
        $dbg = new Debugger($logger, $config);
        $this->setDebugger($dbg);
        
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
        
        $model_connection = DataConnectionFactory::createModelLoaderConnection($config);
        $model_loader->setDataConnection($model_connection);
        $this->model()->setModelLoader($model_loader);
        
        // Load the context
        $this->context = new ContextManager($this, $config);
        
        $this->security = new SecurityManager($this);
        
        if ($config->getOption('MONITOR.ENABLED')) {
            Monitor::register($this);
        }
        
        // Now the workbench is fully loaded and operational
        $this->started = true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::startNewInstance()
     */
    public static function startNewInstance(array $config = null) : WorkbenchInterface
    {
        $instance = new self($config);
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
        return $this->getContext()->getScopeInstallation()->getVariable('last_metamodel_install') ? true : false;
    }

    /**
     *
     * @return ConfigurationInterface
     */
    public function getConfig()
    {
        return $this->getCoreApp()->getConfig();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::model()
     */
    public function model()
    {
        if ($this->mm === null) {
            throw new RuntimeException('Meta model not initialized: workbench not started properly?');
        }
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
    public function getApp($selectorOrString) : AppInterface
    {
        if ($selectorOrString instanceof AppSelectorInterface) {
            if ($app = ($this->running_apps_selectors[$selectorOrString->toString()] ?? null)) {
                return $app;
            }
            $selector = $selectorOrString;
        } elseif (is_string($selectorOrString)) {
            if ($app = ($this->running_apps_selectors[$selectorOrString] ?? null)) {
                return $app;
            }
            $selector = new AppSelector($this, $selectorOrString);
        } else {
            throw new InvalidArgumentException('Invalid app selector used: ' . $selectorOrString . '!');
        }
        
        if (! $app = $this->findAppRunning($selector)) {
            $app = AppFactory::create($selector);
            $this->running_apps[] = $app;
        }
        
        $this->running_apps_selectors[$selector->toString()] = $app;
        
        return $app;
    }

    /**
     * Returns an app, defined by its UID or alias, from the running_apps.
     * 
     * @param AppSelectorInterface $selector
     * @return AppInterface|false
     */
    protected function findAppRunning(AppSelectorInterface $selector) : ?AppInterface
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
        } elseif ($selector->isAlias()) {
            foreach ($this->running_apps as $app) {
                if (strcasecmp($app->getAliasWithNamespace(), $selector->toString()) === 0) {
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
        
        return null;
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::stop()
     */
    public function stop()
    {
        if ($this->isStarted()) {
            $this->eventManager()->dispatch(new OnBeforeStopEvent($this));
            $this->getContext()->saveContexts();
            $this->data()->disconnectAll();
            $this->eventManager()->dispatch(new OnStopEvent($this));
            $this->started = false;
            /* TODO it would be nicer to deinitialize these things when stopping,
             * but the logger won't be able to dump logs properly without the
             * context manager...
            unset($this->logger);
            unset($this->event_manager);
            unset($this->context);
            unset($this->cache);*/
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::eventManager()
     */
    public function eventManager()
    {
        if ($this->event_manager === null) {
            $this->event_manager = new EventManager($this);
        }
        return $this->event_manager;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getInstallationPath()
     */
    public function getInstallationPath() : string
    {
        return $this->installation_path;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getInstallationName()
     */
    public function getInstallationName() : string
    {
        return $this->installation_name;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::filemanager()
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getLogger()
     */
    public function getLogger()
    {
        if (is_null($this->logger)) {
            $this->logger = Log::getErrorLogger($this);
        }
        return $this->logger;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskHandlerInterface::handle()
     */
    public function handle(TaskInterface $task) : ResultInterface
    {
        if (! $task->hasAction()) {
            throw new AppNotFoundError('Cannot handle task without an action selector!');
        }
        return $this->getApp($task->getActionSelector()->getAppAlias())->handle($task);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchInterface::getAppFolder()
     */
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
}