<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Actions\ActionNotFoundError;
use exface\Core\CommonLogic\Translation;
use exface\Core\CommonLogic\AppInstallers\AppInstallerContainer;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

/**
 * This is the base implementation of the AppInterface aimed at providing an
 * app instance for apps defined in the meta model.
 * 
 * If an app requires extra features (i.e. custom installers), it should get
 * it's own app class (appfolder\appaliasApp.php), which extends this class and
 * overrides methods or introduces new ones.
 * 
 * @author Andrej Kabachnik
 *
 */
class App implements AppInterface
{
    
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
     * @see \exface\Core\Interfaces\AppInterface::getAction()
     */
    public function getAction($action_alias, WidgetInterface $called_by_widget = null, UxonObject $uxon_description = null)
    {
        if (! $action_alias) {
            throw new ActionNotFoundError('Cannot find action with alias "' . $action_alias . '" in app "' . $this->getAliasWithNamespace() . '"!');
        }
        $action = ActionFactory::createFromString($this->getWorkbench(), $this->getAliasWithNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $action_alias, $called_by_widget);
        if ($uxon_description instanceof UxonObject) {
            $action->importUxonObject($uxon_description);
        }
        return $action;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getSelector()->getAliasWithNamespace();
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->getSelector()->getAlias();
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
        return substr($this->getAliasWithNamespace(), 0, mb_strripos($this->getAliasWithNamespace(), AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER));
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
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
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
            $config->loadConfigFile($this->getWorkbench()->context()->getScopeUser()->getUserDataFolderAbsolutePath() . DIRECTORY_SEPARATOR . static::CONFIG_FOLDER_IN_USER_DATA . DIRECTORY_SEPARATOR . $this->getConfigFileName(), AppInterface::CONFIG_SCOPE_USER);
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
        return $this->getWorkbench()->context()->getScope($scope)->getContext('Data');
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
    
    public function getTranslator()
    {
        if (is_null($this->translator)) {
            $translator = new Translation($this);
            $translator->setLocale($this->getWorkbench()->context()->getScopeSession()->getSessionLocale());
            $translator->setFallbackLocales(array(
                'en_US'
            ));
            $this->translator = $this->loadTranslationFiles($translator);
        }
        return $this->translator;
    }
    
    protected function loadTranslationFiles(TranslationInterface $translator)
    {
        $locales = array_unique(array_merge(array(
            $translator->getLocale()
        ), $translator->getFallbackLocales()));
        
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
    
    protected function getTranslationsFolder()
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
    
    public function getDefaultLanguageCode()
    {
        return $this->getAppModelDataSheet()->getCellValue('DEFAULT_LANGUAGE_CODE', 0);
    }
    
    protected function getAppModelDataSheet()
    {
        $app_object = $this->getWorkbench()->model()->getObject('exface.Core.App');
        $ds = DataSheetFactory::createFromObject($app_object);
        foreach ($app_object->getAttributes()->getAll() as $attr) {
            $ds->getColumns()->addFromAttribute($attr);
        }
        $ds->addFilterFromString('ALIAS', $this->getAliasWithNamespace(), EXF_COMPARATOR_EQUALS);
        $ds->dataRead();
        return $ds;
    }
}
?>