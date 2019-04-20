<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Factories\DataSheetFactory;
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
use exface\Core\Interfaces\CmsConnectorInterface;
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
use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\DataSheets\DataSheetReadError;
use exface\Core\Contexts\DataContext;
use exface\Core\Interfaces\Selectors\WidgetSelectorInterface;

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
    
    const CONFIG_FOLDER_IN_APP = 'Config';
    
    const CONFIG_FOLDER_IN_USER_DATA = '.config';
    
    const CONFIG_FILE_SUFFIX = 'config';
    
    const CONFIG_FILE_EXTENSION = '.json';
    
    const TRANSLATIONS_FOLDER_IN_APP = 'Translations';
    
    private $selector = null;
    
    private $uid = null;
    
    private $vendor = null;
    
    private $directory = '';
    
    private $config = null;
    
    private $context_data_default_scope = null;
    
    private $translator = null;
    
    private $selector_cache = [];
    
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
    
    protected function getClassnameSuffixToStripFromAlias() : string
    {
        return 'App';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::getDirectory()
     */
    public function getDirectory()
    {
        if (! $this->directory) {
            $this->directory = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, DIRECTORY_SEPARATOR, $this->getAliasWithNamespace());
        }
        return $this->directory;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AppInterface::getDirectoryAbsolutePath()
     */
    public function getDirectoryAbsolutePath()
    {
        return $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $this->getDirectory();
    }
    
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
     *
     * @see \exface\Core\Interfaces\AppInterface::getUid()
     */
    public function getUid()
    {
        if (is_null($this->uid)) {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.APP');
            $ds->addFilterFromString('ALIAS', $this->getAliasWithNamespace(), EXF_COMPARATOR_EQUALS);
            $ds->dataRead();
            if ($ds->countRows() == 0) {
                throw new LogicException('No app matching alias "' . $this->getAliasWithNamespace() . '" is installed!');
            }
            if ($ds->countRows() > 1) {
                throw new LogicException('Multiple apps matching the alias "' . $this->getAliasWithNamespace() . '" found!');
            }
            $this->uid = $ds->getUidColumn()->getCellValue(0);
        }
        return $this->uid;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getSelector()
     */
    public function getSelector()
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
            $this->context_data_default_scope = ContextManagerInterface::CONTEXT_SCOPE_WINDOW;
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
        if ($locale !== null) {
            return $this->createTranslation($locale);
        }
        
        if ($this->translator === null) {
            $this->translator = $this->createTranslation($this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale()); 
        }
        
        return $this->translator;
    }
    
    /**
     * 
     * @param string $locale
     * @return TranslationInterface
     */
    protected function createTranslation(string $locale) : TranslationInterface
    {
        $fallbackLocales = [
            'en_US'
        ];
        
        $locales = array_unique(
            array_merge(
                [$locale],
                $fallbackLocales
            )
        );
        
        $translator = new Translation($locale, $fallbackLocales);
        
        foreach ($locales as $locale) {
            $locale_suffixes = array();
            $locale_suffixes[] = $locale;
            $locale_suffixes[] = explode('_', $locale)[0];
            $locale_suffixes = array_unique($locale_suffixes);
            
            foreach ($locale_suffixes as $suffix) {
                $filename = $this->getAliasWithNamespace() . '.' . $suffix . '.json';
                // Load the default translation of the app
                $translator->addDictionaryFromFile($this->getTranslationsFolder() . DIRECTORY_SEPARATOR . $filename, $locale);
                
                // Load the installation specific translation of the app
                $translator->addDictionaryFromFile($this->getWorkbench()->filemanager()->getPathToTranslationsFolder() . DIRECTORY_SEPARATOR . $filename, $locale);
            }
        }
        
        
        return $translator;
    }
    
    /**
     * @return string
     */
    protected function getTranslationsFolder() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $this->getDirectory() . DIRECTORY_SEPARATOR . static::TRANSLATIONS_FOLDER_IN_APP;
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
        $app_installer = new AppInstallerContainer($this);
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
        try { 
            return $this->getAppModelDataSheet()->getCellValue('DEFAULT_LANGUAGE_CODE', 0);
        } catch (DataSheetReadError $e) {
            // Catch read errors in case, the app does not yet exist in the model (this may happen
            // on rare occasions, when apps are just being installed)
            $this->getWorkbench()->getLogger()->logException($e);
            return $this->getWorkbench()->getConfig()->getOption('LOCALE.DEFAULT');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getLanguages()
     */
    public function getLanguages() : array
    {
        $langs = [];
        foreach (glob($this->getTranslationsFolder() . "*.json") as $path) {
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $langs[] = StringDataType::substringAfter($filename, '.', false, false, true);
        }
        return $langs;
    }
    
    protected function getAppModelDataSheet()
    {
        $app_object = $this->getWorkbench()->model()->getObject('exface.Core.App');
        $ds = DataSheetFactory::createFromObject($app_object);
        $cols = $ds->getColumns();
        $cols->addFromExpression('UID');
        $cols->addFromExpression('DEFAULT_LANGUAGE_CODE');
        $cols->addFromExpression('NAME');
        $ds->addFilterFromString('ALIAS', $this->getAliasWithNamespace(), EXF_COMPARATOR_EQUALS);
        $ds->dataRead();
        return $ds;
    }
    
    public function handle(TaskInterface $task): ResultInterface
    {
        if ($task->isTriggeredByWidget()) {
            $widget = $task->getWidgetTriggeredBy();
            if (! $task->hasMetaObject()) {
                $task->setMetaObject($widget->getMetaObject());
            }
            
            // If the task tigger is a button or similar, the action might be defined in that
            // trigger widget. However, we can only use this definition, if the task does not
            // have an action explicitly defined or that action is exactly the same as the
            // one of the trigger widget.
            if ($widget instanceof iTriggerAction) {
                if (! $task->hasAction()) {
                    $action = $widget->getAction();
                } elseif ($widget->hasAction()) {
                    // At this point, we know, that both, task and widget, have actions - so we
                    // need to compare them.
                    if ($task->getActionSelector()->isAlias() && strcasecmp($task->getActionSelector()->toString(), $widget->getAction()->getAliasWithNamespace()) === 0) {
                        //In most cases, the task action will be defined via
                        // alias, so we can simply compare the alias without instantiating the action.
                        $action = $widget->getAction();
                    } else {
                        // Otherwise we need to instantiate it first to get the alias.
                        $task_action = ActionFactory::create($task->getActionSelector(), ($widget ? $widget : null));
                        $widget_action = $widget->getAction();
                        if ($task_action->isExactly($widget_action)) {
                            // If the task tells us to perform the action of the widget, use the description in the
                            // widget, because it is more detailed.
                            $action = $widget->getAction();
                        } else {
                            // If the task is about another action (e.g. ReadPrefill on a button, that does ShowDialog),
                            // Take the task action and inherit action settings related to the input data from the widget.
                            $action = $task_action;
                            if ($widget_action->hasInputDataPreset() === true) {
                                $action->setInputDataPreset($widget->getAction()->getInputDataPreset());
                            }
                            if ($widget_action->hasInputMappers() === true) {
                                foreach ($widget_action->getInputMappers() as $mapper) {
                                    $action->addInputMapper($mapper);
                                }
                            }
                        }
                    }
                    
                }
            }
        }
        
        if (! isset($action)) {
            $action = ActionFactory::create($task->getActionSelector(), ($widget ? $widget : null));
        }
        
        return $action->handle($task);
    }
    
    protected function getPrototypeClass(PrototypeSelectorInterface $selector) : string
    {
        $string = $selector->toString();
        switch (true) {
            case $selector->isClassname():
                return $string;
            case $selector->isFilepath():
                $string = Filemanager::pathNormalize($string, FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR);
                $vendorFolder = Filemanager::pathNormalize($this->getWorkbench()->filemanager()->getPathToVendorFolder());
                if (StringDataType::startsWith($string, $vendorFolder)) {
                    $string = substr($string, strlen($vendorFolder));
                }
                $ext = '.' . FileSelectorInterface::PHP_FILE_EXTENSION;
                $string = substr($string, 0, (-1*strlen($ext)));
                $string = str_replace(FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
                return $string;
            case ($selector instanceof AliasSelectorInterface) && $selector->isAlias():
                $appAlias = $selector->getAppAlias();
                $componentAlias = substr($string, (strlen($appAlias)+1));
                $subfolder = $this->getPrototypeClasssSubfolder($selector);
                $classSuffix = $this->getPrototypeClassSuffix($selector);
                $string = $appAlias . ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR . $subfolder . ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR . $componentAlias . $classSuffix;
                return str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
        }
    }
    
    protected function getPrototypeClassSuffix(PrototypeSelectorInterface $selector) : string
    {
        switch (true) {
            case $selector instanceof DataTypeSelectorInterface:
                return 'DataType';
        }
        return '';
    }
    
    protected function getPrototypeClasssSubfolder(PrototypeSelectorInterface $selector) : string
    {
        switch (true) {
            case $selector instanceof ActionSelectorInterface:
                return 'Actions';
            case $selector instanceof FacadeSelectorInterface:
                return 'Facades';
            case $selector instanceof BehaviorSelectorInterface:
                return 'Behaviors';
            case $selector instanceof CmsConnectorInterface:
                return 'CmsConnectors';
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
            $class = $cache['class'];
            if ($class !== null) {
                if ($constructorArguments === null) {
                    return new $class($selector);
                } else {
                    $reflector = new \ReflectionClass($class);
                    return $reflector->newInstanceArgs($constructorArguments);
                }
            } else {
                return $this->loadFromModel($selector);
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
                try {
                    $this->loadFromModel($selector);
                    $this->selector_cache[$selector->toString()][get_class($selector)] = ['selector' => $selector];
                    return true;
                } catch (\Throwable $e) {
                    return false;
                }
            }
        } catch(\Throwable $e) {
            throw new LogicException('Cannot check if ' . $selector->getComponentType() . ' exists in app ' . $this->getAliasWithNamespace() . ': cannot load prototype class!', null, $e);
        }
        
        return false;
    }
    
    protected function loadFromModel(SelectorInterface $selector) 
    {
        if ($selector instanceof DataTypeSelectorInterface) {
            return $this->getWorkbench()->model()->getModelLoader()->loadDataType($selector);
        }
        throw new AppComponentNotFoundError(ucfirst($selector->getComponentType()) . ' "' . $selector->toString() . '" not found in app ' . $this->getAliasWithNamespace());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AppInterface::getAction()
     */
    public function getAction(ActionSelectorInterface $selector, WidgetInterface $sourceWidget = null) : ActionInterface
    {
        $class = $this->getPrototypeClass($selector);
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
}
?>