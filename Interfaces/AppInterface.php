<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Contexts\DataContext;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use Psr\Container\ContainerInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Exceptions\AppComponentNotFoundError;

/**
 * An app bundle code, model and all kinds of configuration needed for a meaningfull application.
 *
 * In the model the app acts as a container for model components like objects, actions, etc.
 * 
 * At code level the app acts as a dependency injection container, that resolves component selectors
 * and provides model prototypes and general services like translation, configuration, etc.
 *
 * @author Andrej Kabachnik
 *
 */
interface AppInterface extends WorkbenchDependantInterface, AliasInterface, TaskHandlerInterface, ContainerInterface
{
    
    const CONFIG_SCOPE_SYSTEM = 'SYSTEM';
    
    const CONFIG_SCOPE_INSTALLATION = 'INSTALLATION';
    
    const CONFIG_SCOPE_USER = 'USER';
    
    /**
     * Returns the component or service identified by the given selector.
     * 
     * The selector can either be an instance of the SelectorInterface or a string, but in
     * the latter case a selector class must be passed as well.
     * 
     * In future apps will be extended by the possibility to add any custom services in
     * order to act as true dependency injection containers. This is why the method also
     * accepts strings.
     * 
     * @see \Psr\Container\ContainerInterface::get()
     * 
     * @param SelectorInterface|string $selectorOrString
     * @param string $selectorClass
     * 
     * @throws AppComponentNotFoundError
     * 
     * @return mixed
     */
    public function get($selectorOrString, $selectorClass = null);
    
    /**
     * Returns TRUE if the app contains a component or service specified by the given selector.
     * 
     * The selector can either be an instance of the SelectorInterface or a string, but in
     * the latter case a selector class must be passed as well.
     *
     * @see \Psr\Container\ContainerInterface::has()
     *
     * @param SelectorInterface|string $selectorOrString
     * @param string $selectorClass
     *
     * @return bool
     */
    public function has($selectorOrString, $selectorClass = null);
    
    public function getAction(ActionSelectorInterface $selector, WidgetInterface $sourceWidget) : ActionInterface;

    /**
     * Returns the path to the app's folder relative to the vendor folder
     *
     * @return string
     */
    public function getDirectory();

    /**
     * Returns the absolute path to the app's folder
     *
     * @return string
     */
    public function getDirectoryAbsolutePath();

    /**
     * Return the applications vendor (first part of the namespace)
     *
     * @return string
     */
    public function getVendor();

    /**
     * Returns the configuration object of this app.
     * At this point, the configuration is already fully compiled and contains
     * all options from all definition levels: defaults, installation config, user config, etc.
     *
     * @return ConfigurationInterface
     */
    public function getConfig();

    /**
     * Returns the unique identifier of this app.
     * It is a UUID by default.
     * 
     * @throws LogicException if app has no UID or is not installed
     * 
     * @return string
     */
    public function getUid();

    /**
     * Returns an array with data variables stored for this app in the given context scope
     *
     * @param string $scope            
     * @return DataContext
     */
    public function getContextData($scope);

    /**
     * Returns the value of the given variable stored in the given context scope for this app.
     * If no scope is specified,
     * the default data scope of this app will be used - @see get_context_data_default_scope()
     *
     * @param string $variable_name            
     * @param string $scope            
     * @return mixed
     */
    public function getContextVariable($variable_name, $scope = null);

    /**
     * Sets the value of the given context variable in the specified scope.
     * If no scope specified, the default data
     * scope of this app will be used - @see get_context_data_default_scope()
     *
     * @param string $variable_name            
     * @param mixed $value            
     * @param string $scope            
     * @return DataContext
     */
    public function setContextVariable($variable_name, $value, $scope = null);

    /**
     * Removes the given variable from the context of this app in the given scope.
     * If no scope specified, the default data
     * scope of this app will be used - @see get_context_data_default_scope().
     *
     * @param string $variable_name            
     * @param string $scope            
     * @return DataContext
     */
    public function unsetContextVariable($variable_name, $scope = null);

    /**
     * Returns the alias of the default context scope to be used when saving context data for this app.
     * If not explicitly specified by set_context_data_default_scope() the window scope will be used.
     *
     * @return string
     */
    public function getContextDataDefaultScope();

    /**
     * Sets the default context scope to be used when saving context data for this app.
     *
     * @param string $value            
     * @return AppInterface
     */
    public function setContextDataDefaultScope($scope_alias);

    /**
     *
     * @param InstallerInterface $injected_installer            
     * @return AppInstallerInterface
     */
    public function getInstaller(InstallerInterface $injected_installer = null);
    
    /**
     * Returns the selector, that can be used to instantiate the app
     * 
     * @return AppSelectorInterface
     */
    public function getSelector();
    
    /**
     * Returns the ISO 639-1 code for the default language of the app.
     * 
     * @return string
     */
    public function getLanguageDefault() : string;
    
    /**
     * Return an array with ISO 639-1 codes for all languages, this app has translations for.
     * 
     * @return string[]
     */
    public function getLanguages() : array;
    
    /**
     * Returns the translator used by this app for the current session locale 
     * or the given locale if specified.
     * 
     * Each app is free to use any translation implementation as long 
     * as it implements to the TranslationInterface.
     * 
     * @param string $locale
     * @return TranslationInterface
     */
    public function getTranslator(string $locale = null) : TranslationInterface;
}
?>