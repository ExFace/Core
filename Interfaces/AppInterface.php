<?php
namespace exface\Core\Interfaces;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Contexts\DataContext;
use exface\Core\Exceptions\Actions\ActionNotFoundError;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * The app class provieds access to actions, configs, translations, etc. of
 * an ExFace application.
 *
 * In a sence, it is the junction point for the meta model, the code and all
 * kinds of configuration. There is an instance of the app classe for every
 * app in the meta model. This instance knows, where the app folder is, which
 * hardcoded actions exist, etc.
 *
 * It is also the responsibility of the app class to load configs and translations.
 *
 * @author Andrej Kabachnik
 *
 */
interface AppInterface extends ExfaceClassInterface, AliasInterface
{
    
    const CONFIG_SCOPE_SYSTEM = 'SYSTEM';
    
    const CONFIG_SCOPE_INSTALLATION = 'INSTALLATION';
    
    const CONFIG_SCOPE_USER = 'USER';
    
    /**
     * Returns an action object
     *
     * @param string $action_alias            
     * @throws ActionNotFoundError if the alias cannot be resolved
     * @return ActionInterface
     */
    public function getAction($action_alias, AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null);

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
     * Returns TRUE if the given class is part of the app and FALSE otherwise.
     *
     * @param string|object $object_or_class_name            
     * @throws InvalidArgumentException if the given argument is neither object nor string
     * @return boolean
     */
    public function containsClass($object_or_class_name);
    
    /**
     * Returns the name resolver, that can be used to instantiate the app
     * 
     * @return NameResolverInterface
     */
    public function getNameResolver();
}
?>