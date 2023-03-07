<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\CommonLogic\Translation;
use exface\Core\CommonLogic\AppInstallers\AppInstallerContainer;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Exceptions\AppComponentNotFoundError;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;
use exface\Core\Interfaces\Selectors\ContextSelectorInterface;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Interfaces\Selectors\FormulaSelectorInterface;
use exface\Core\Interfaces\Selectors\ModelLoaderSelectorInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\ClassSelectorInterface;
use exface\Core\CommonLogic\Traits\AliasTrait;
use exface\Core\Contexts\DataContext;
use exface\Core\Interfaces\Selectors\WidgetSelectorInterface;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Exceptions\Actions\ActionNotFoundError;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * This is the base implementation of the AppInterface aimed at providing an
 * generic app instance.
 * 
 * Apps defined in the meta model will be represented by this generc app. If an 
 * app requires extra features (i.e. custom installers, another folder structure, etc.), 
 * it should get it's own app class (appfolder\MyAppAliasApp.php).
 * 
 * If extending the generic app, it is recommmended to keep it's folder structure, which
 * is well visible in the Core app. Changing folder names and path convetions, the
 * get(), has() and/or getSelectorForComponent() methods must be overridden.
 * 
 * The generic app provides default configuration and translation implementation, which
 * again, may be changed if neccessary.
 * 
 * @author Andrej Kabachnik
 *
 */
class App implements AppInterface
{
    use AliasTrait;
    
    use ImportUxonObjectTrait;
    
    const CONFIG_FOLDER_IN_APP = 'Config';
    
    const CONFIG_FOLDER_IN_USER_DATA = '.config';
    
    const CONFIG_FILE_SUFFIX = 'config';
    
    const CONFIG_FILE_EXTENSION = '.json';
    
    private $selector = null;
    
    private $uid = null;
    
    private $vendor = null;
    
    private $directory = '';
    
    private $config = null;
    
    private $context_data_default_scope = null;
    
    private $translator = null;
    
    private $selector_cache = [];
    
    private $defaultLanguageCode = null;
    
    private $name;
    
    /**
     *
     * @param AppSelectorInterface $selector
     * @deprecated use AppFactory instead!
     */
    public function __construct(AppSelectorInterface $selector)
    {
        $this->selector = $selector;
        $this->init();
    }
    
    /**
     * This ist the startup-method for apps.
     * Anything put here will be run right after the app is instantiated. By default it does not do anything!
     * This method is handy to initialize some dependencies, variables, etc.
     *
     * @return void
     */
    protected function init()
    {}
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getSelector()->getAppAlias();
    }
    
    /**
     * 
     * @param string $alias
     * @return AppInterface
     */
    protected function setAlias(string $alias) : AppInterface
    {
        if (strcasecmp($alias, $this->getAliasWithNamespace()) !== 0) {
            throw new RuntimeException('Cannot change the alias of an app at runtime! Please change it in the model before loading the app.');
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getClassnameSuffixToStripFromAlias() : string
    {
        return 'App';
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AppInterface::getDirectory()
     */
    public function getDirectory()
    {
        if (! $this->directory) {
            $vendorDir = $this->getWorkbench()->filemanager()->getPathToVendorFolder();
            // Replace dots in the alias by the DIRECTORY_SEPARATOR
            $dirCaseSensitive = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, DIRECTORY_SEPARATOR, $this->getAliasWithNamespace());
            // By default, we use composer packages. Since the must be lowercase as of composer 2.0,
            // we just lowercase the app's alias here.
            $dir = strtolower($dirCaseSensitive);
            // However, earlier apps have non-lowercased folder names, so if the folder does
            // not exist, we check, if the case sensitive path is there. If so, use it. Otherwise
            // the app was never exported, so we can still assume the lowercased path.
            if (file_exists($vendorDir . DIRECTORY_SEPARATOR . $dir) === false) {
                if (file_exists($vendorDir . DIRECTORY_SEPARATOR . $dirCaseSensitive)) {
                    $this->directory = $dirCaseSensitive;
                    return $dirCaseSensitive;
                }
            }
            $this->directory = $dir;
        }
        return $this->directory;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AppInterface::getDirectoryAbsolutePath()
     */
    public function getDirectoryAbsolutePath()
    {
        return $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $this->getDirectory();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->selector->getVendorAlias();
    }
    
    /**
     * Return the applications vendor (first part of the namespace)
     *
     * @return string
     */
    public function getVendor()
    {
        return $this->getSelector()->getVendorAlias();
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getSelector()->getWorkbench();
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::getConfig()
     */
    public function getConfig()
    {
        if (is_null($this->config)) {
            $this->setConfig($this->loadConfigFiles());
        }
        return $this->config;
    }
    
    /**
     * Replaces the current configuration for this app by the given one.
     *
     * @param ConfigurationInterface $configuration
     * @return AppInterface
     */
    protected function setConfig(ConfigurationInterface $configuration)
    {
        $this->config = $configuration;
        return $this;
    }
    
    /**
     * Loads configuration files from the app folder and the installation config folder and merges the respecitve config options
     * into the given configuration object.
     *
     * This method is handy if an app needs to create some custom base config object and load the config files on that. In this case,
     * simply overwrite the getConfig() method to pass a non-empty $base_config.
     *
     * @param ConfigurationInterface $base_config
     * @return \exface\Core\Interfaces\ConfigurationInterface
     */
    protected function loadConfigFiles(ConfigurationInterface $base_config = null)
    {
        $config = ! is_null($base_config) ? $base_config : ConfigurationFactory::createFromApp($this);
        
        // Load the default config of the app. Do not pass a scope to the loader,
        // beacuase the packaged config file should not be edited programmatically.
        $config->loadConfigFile($this->getConfigFolder() . DIRECTORY_SEPARATOR . $this->getConfigFileName());
        
        // Load the installation config of the app
        $config->loadConfigFile($this->getWorkbench()->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . $this->getConfigFileName(), AppInterface::CONFIG_SCOPE_INSTALLATION);
        
        // Load the user config if the workbench is already fully started and thus the user is known
        if ($this->getWorkbench()->isStarted()) {
            $config->loadConfigFile($this->getWorkbench()->getContext()->getScopeUser()->getUserDataFolderAbsolutePath() . DIRECTORY_SEPARATOR . static::CONFIG_FOLDER_IN_USER_DATA . DIRECTORY_SEPARATOR . $this->getConfigFileName(), AppInterface::CONFIG_SCOPE_USER);
        }
        
        return $config;
    }
    
    /**
     * Returns the file name for configurations of this app.
     * By default it is [vendor].[app_alias].[file_suffix].json.
     * The app will look for files with this name in all configuration folders. If your app needs a custom file name, overwrite this method.
     * Using different file suffixes allows the developer to have separate configuration files for app specific purposes.
     *
     * @param string $file_suffix
     * @return string
     */
    public function getConfigFileName($config_name = null, $file_suffix = '.config')
    {
        if (is_null($file_suffix)) {
            $file_suffix = static::CONFIG_FILE_SUFFIX;
        }
        
        if (is_null($config_name)){
            $config_name = $this->getAliasWithNamespace();
        }
        
        return $config_name . $file_suffix . static::CONFIG_FILE_EXTENSION;
    }
    
    /**
     * Returns the absolute path to the config folder of this app.
     * Overwrite this if you want your app configs to be placed somewhere else.
     *
     * @return string
     */
    protected function getConfigFolder()
    {
        return $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $this->getDirectory() . DIRECTORY_SEPARATOR . static::CONFIG_FOLDER_IN_APP;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AppInterface::getUid()
     */
    public function getUid() : ?string
    {
        if ($this->uid === null) {
            try {
                $this->getWorkbench()->model()->getModelLoader()->loadApp($this);
            } catch (AppNotFoundError $e) {
                return null;
            }
        }
        return $this->uid;
    }
    
    /**
     *
     * @param string $value
     * @return AppInterface
     */
    protected function setUid(string $value) : AppInterface
    {
        $this->uid = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getSelector()
     */
    public function getSelector() : AliasSelectorInterface
    {
        return $this->selector;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::getContextDataDefaultScope()
     */
    public function getContextDataDefaultScope()
    {
        if (is_null($this->context_data_default_scope)) {
            $this->context_data_default_scope = ContextManagerInterface::CONTEXT_SCOPE_SESSION;
        }
        return $this->context_data_default_scope;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::setContextDataDefaultScope()
     */
    public function setContextDataDefaultScope($scope_alias)
    {
        $this->context_data_default_scope = $scope_alias;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::getContextData()
     */
    public function getContextData($scope = null)
    {
        if (is_null($scope)) {
            $scope = $this->getContextDataDefaultScope();
        }
        return $this->getWorkbench()->getContext()->getScope($scope)->getContext(DataContext::class);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::getContextVariable()
     */
    public function getContextVariable($variable_name, $scope = null)
    {
        return $this->getContextData($scope)->getVariableForApp($this, $variable_name);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::setContextVariable()
     */
    public function setContextVariable($variable_name, $value, $scope = null)
    {
        return $this->getContextData($scope)->setVariableForApp($this, $variable_name, $value);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::unsetContextVariable()
     */
    public function unsetContextVariable($variable_name, $scope = null)
    {
        return $this->getContextData($scope)->unsetVariableForApp($this, $variable_name);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getTranslator()
     */
    public function getTranslator(string $locale = null) : TranslationInterface
    {
        $fallbackLocales = [
            'en_US'
        ];
        
        if ($locale !== null) {
            return new Translation($this, $locale, $fallbackLocales);
        } 
        
        if ($this->translator === null) {
            $this->translator = new Translation($this, $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale(), $fallbackLocales); 
        }
        
        return $this->translator;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::getInstaller()
     * @return AppInstallerContainer
     */
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $app_installer = new AppInstallerContainer($this->getSelector());
        // Add the injected installer
        if ($injected_installer) {
            $app_installer->addInstaller($injected_installer);
        }
        return $app_installer;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getLanguageDefault()
     */
    public function getLanguageDefault() : string
    {
        if ($this->defaultLanguageCode === null) {
            try {
                $this->getWorkbench()->model()->getModelLoader()->loadApp($this);
            } catch (AppNotFoundError $e) {
                $this->defaultLanguageCode = $this->getWorkbench()->getConfig()->getOption('SERVER.DEFAULT_LOCALE');
            }
        }
        return $this->defaultLanguageCode;
    }
    
    /**
     * 
     * @param string $code
     * @return AppInterface
     */
    protected function setDefaultLanguageCode(string $code) : AppInterface
    {
        $this->defaultLanguageCode = $code;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getLanguages()
     */
    public function getLanguages(bool $forceLocale = true) : array
    {
        return $this->getTranslator()->getLanguagesAvailable($forceLocale);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskHandlerInterface::handle()
     */
    public function handle(TaskInterface $task): ResultInterface
    {        
        return $task->getAction()->handle($task);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getPrototypeClass()
     */
    public function getPrototypeClass(PrototypeSelectorInterface $selector) : string
    {
        $string = $selector->toString();
        switch (true) {
            case $selector->isClassname():
                // If the selector is a class name already, do nothing.
                return $string;
            case $selector->isFilepath():
                // If the selector is a path, we need to get the class from the file.
                $string = Filemanager::pathNormalize($string, FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR);
                $vendorFolder = Filemanager::pathNormalize($this->getWorkbench()->filemanager()->getPathToVendorFolder());
                
                if (StringDataType::startsWith($string, $vendorFolder)) {
                    $relPath = substr($string, strlen($vendorFolder));
                    $absPath = $string;
                } else {
                    $relPath = $string;
                    $absPath = FilePathDataType::join([$vendorFolder, $relPath]);
                }
                
                // We can be sure, the class name is the file name exactly
                $className = FilePathDataType::findFileName($relPath);
                // The namespace can be different, than the file path, so get it 
                // directly from the path. Of course, we could fetch the entire class
                // name from the file, but this is way slower because it requires
                // tokenizing.
                $namespace = PhpFilePathDataType::findNamespaceOfFile($absPath);
                $class = $namespace . '\\' . $className;
                
                return $class;
            case ($selector instanceof AliasSelectorInterface) && $selector->isAlias():
                // If the selector is an alias, we should see, if it matches this app.
                // If not, we delegate resolving to the app, it belongs too because
                // that app could potentially have a different resolver algorithm.
                $appAlias = $selector->getAppAlias();
                if (strcasecmp($appAlias, $this->getAliasWithNamespace()) !== 0) {
                    return $this->getWorkbench()->getApp($appAlias)->getPrototypeClass($selector);
                } else {
                    $appAlias = $this->getAliasWithNamespace();
                }
                $componentAlias = substr($string, (strlen($appAlias)+1));
                $subfolder = $this->getPrototypeClassSubNamespace($selector);
                $classSuffix = $this->getPrototypeClassSuffix($selector);
                $string = $appAlias . ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR . $subfolder . ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR . $componentAlias . $classSuffix;
                return str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
        }
    }
    
    /**
     * 
     * @param PrototypeSelectorInterface $selector
     * @return string
     */
    protected function getPrototypeClassSuffix(PrototypeSelectorInterface $selector) : string
    {
        switch (true) {
            case $selector instanceof DataTypeSelectorInterface:
                return 'DataType';
        }
        return '';
    }
    
    /**
     * 
     * @param PrototypeSelectorInterface $selector
     * @return string
     */
    protected function getPrototypeClassSubNamespace(PrototypeSelectorInterface $selector) : string
    {
        switch (true) {
            case $selector instanceof ActionSelectorInterface:
                return 'Actions';
            case $selector instanceof FacadeSelectorInterface:
                return 'Facades';
            case $selector instanceof BehaviorSelectorInterface:
                return 'Behaviors';
            case $selector instanceof ContextSelectorInterface:
                return 'Contexts';
            case $selector instanceof DataConnectorSelectorInterface:
                return 'DataConnectors';
            case $selector instanceof DataTypeSelectorInterface:
                return 'DataTypes';
            case $selector instanceof FormulaSelectorInterface:
                return 'Formulas';
            case $selector instanceof ModelLoaderSelectorInterface:
                return 'ModelLoaders';
            case $selector instanceof QueryBuilderSelectorInterface:
                return 'QueryBuilders';
            case $selector instanceof WidgetSelectorInterface:
                return 'Widgets';
            case $selector instanceof CommunicationMessageSelectorInterface:
                return 'Communication\\Messages';
        }
        return '';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::get()
     */
    public function get($selectorOrString, $selectorClass = null, array $constructorArguments = null)
    {
        if (! array_key_exists((string) $selectorOrString, $this->selector_cache)) {
            // has() will cache the class
            if (! $this->has($selectorOrString)) {
                $type = $selectorOrString instanceof SelectorInterface ? ucfirst($selectorOrString->getComponentType()) : '';
                throw new AppComponentNotFoundError($type . ' "' . $selectorOrString . '" not found in app ' . $this->getAliasWithNamespace());
            }
        }
        
        if ($selectorOrString instanceof SelectorInterface) {
            $selector = $selectorOrString;
        } elseif ($selectorClass !== null) {
            $selector = SelectorFactory::createFromString($this->getWorkbench(), $selectorOrString, $selectorClass);
        } else {
            throw new UnexpectedValueException('Cannot get component ' . $selectorOrString . ' from app ' . $this->getAliasWithNamespace() . ': invalid selector or missing type!');
        }
        
        $cache = $this->selector_cache[$selector->toString()][get_class($selector)];
        if ($cache !== null) {
            $selector = $cache['selector'];
            $class = $cache['class'] ?? null;
            $instance = $cache['instance'] ?? null;
            if ($instance !== null) {
                return $instance;
            }
            if ($class !== null) {
                if ($constructorArguments === null) {
                    return new $class($selector);
                } else {
                    $reflector = new \ReflectionClass($class);
                    return $reflector->newInstanceArgs($constructorArguments);
                }
            } else {
                $instance = $this->loadFromModel($selector);
                if ($instance === null) {
                    throw new AppComponentNotFoundError(ucfirst($selector->getComponentType()) . ' "' . $selector->toString() . '" not found in app ' . $this->getAliasWithNamespace());
                }
                return $instance;
            }
        }
        
        $type = $selectorOrString instanceof SelectorInterface ? ucfirst($selectorOrString->getComponentType()) : '';
        throw new AppComponentNotFoundError($type . ' "' . $selectorOrString . '" not found in app ' . $this->getAliasWithNamespace());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::has()
     */
    public function has($selectorOrString, $selectorClass = null)
    {
        if (array_key_exists((string) $selectorOrString, $this->selector_cache)) {
            return true;
        }
        
        if ($selectorOrString instanceof SelectorInterface) {
            $selector = $selectorOrString;
        } elseif ($selectorClass !== null) {
            $selector = SelectorFactory::createFromString($this->getWorkbench(), $selectorOrString, $selectorClass);
        } else {
            throw new UnexpectedValueException('Cannot get component ' . $selectorOrString . ' from app ' . $this->getAliasWithNamespace() . ': invalid selector or missing type!');
        }
        
        try {
            $class = $this->getPrototypeClass($selector);
            if (class_exists($class)){
                $this->selector_cache[$selector->toString()][get_class($selector)] = ['selector' => $selector, 'class' => $class];
                return true;
            } else {
                $instance = $this->loadFromModel($selector);
                if ($instance !== null) {
                    $this->selector_cache[$selector->toString()][get_class($selector)] = ['selector' => $selector, 'instance' => $instance];
                    return true;
                } else {
                    return false;
                }
            }
        } catch(\Throwable $e) {
            throw new LogicException('Cannot check if ' . $selector->getComponentType() . ' exists in app ' . $this->getAliasWithNamespace() . ': cannot load prototype class!', null, $e);
        }
        
        return false;
    }
    
    /**
     * 
     * @param SelectorInterface $selector
     * @throws AppComponentNotFoundError
     * @return object|NULL
     */
    protected function loadFromModel(SelectorInterface $selector) : ?object
    {
        switch (true) {
            case $selector instanceof DataTypeSelectorInterface:
                return $this->getWorkbench()->model()->getModelLoader()->loadDataType($selector);
            // TODO add loading other things like actions here too. Currently they are being loaded
            // directly in their factories. It would be nicer to load them here to give app developers
            // the freedom to use their own loading logic for certain selectors
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getAction()
     */
    public function getAction(ActionSelectorInterface $selector, WidgetInterface $sourceWidget = null) : ActionInterface
    {
        $class = $this->getPrototypeClass($selector);
        if (class_exists($class) === false) {
            switch (true) {
                case $selector->isAlias() : $selectorDescr = 'with alias '; break;
                case $selector->isClassname() : $selectorDescr = 'with class name '; break;
                case $selector->isFilepath() : $selectorDescr = 'with file path '; break;
            }
            throw new ActionNotFoundError('Action ' . $selectorDescr . '"' . $selector->toString() . '" not found!');
        }
        return new $class($this, $sourceWidget);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return StringDataType::substringAfter($this->getAliasWithNamespace(), AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject([
            'UID' => $this->getUid(),
            'ALIAS' => $this->getAliasWithNamespace(),
            'NAME' => $this->getName(),
            'DEFAULT_LANGUAGE_CODE' => $this->getLanguageDefault()
        ]);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getName()
     */
    public function getName() : string
    {
        if ($this->name === null) {
            try {
                $this->getWorkbench()->model()->getModelLoader()->loadApp($this);
            } catch (AppNotFoundError $e) {
                $this->name = '';
            }
        }
        return $this->name;
    }
    
    /**
     * 
     * @param string $value
     * @return AppInterface
     */
    protected function setName(string $value) : AppInterface
    {
        $this->name = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::isInstalled()
     */
    public function isInstalled() : bool
    {
        try {
            $this->getWorkbench()->model()->getModelLoader()->loadApp($this);
            return true;
        } catch (AppNotFoundError $e) {
            return false;
        }
    }
}