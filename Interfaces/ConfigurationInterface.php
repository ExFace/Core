<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\OutOfBoundsException;

/**
 * Interface for configuration data objects allowing access to config options in different scopes.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ConfigurationInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{

    /**
     * Returns a single configuration value specified by the given key
     *
     * @param string $key            
     * @return mixed
     */
    public function getOption(string $key);
    
    /**
     * Returns all options from a given group namespace (i.e. with the samep part before the `.`).
     * 
     * The namespace can be optionally stripped off the option name by setting `$removeNamespace` to TRUE.
     * 
     * Examples:
     * 
     * - getOptionGroup('MONITOR.ERRORS') -> ['MONITOR.ERRORS.ENABLED' => true, 'MONITOR.ERRORS.DAY_TO_KEEP' ... ]
     * - getOptionGroup('MONITOR.ERRORS', true) -> ['ENABLED' => true, 'DAY_TO_KEEP' ... ]
     * - getOptionGroup('MONITOR') -> ['MONITOR.ACTIONS.ENABLED' => true, 'MONITOR.ERRORS.ENABLED' ... ]
     * - getOptionGroup('MONITOR', true) -> ['ACTIONS.ENABLED' => true, 'ERRORS.ENABLED' ... ]
     * 
     * @param string $namespace
     * @return array
     */
    public function getOptionGroup(string $namespace, bool $removeNamespace = false) : array;
    
    /**
     * 
     * @param string $regEx
     * @return array
     */
    public function findOptions(string $regEx) : array;

    /**
     * Sets a single configuration value specified by the given key. If a scope
     * is defined, the value will be stored in this scope. Otherwise the value
     * will only affect the current request. 
     *
     * @param string $key     
     * @param string $configScope       
     * @param mixed $value_or_object_or_string            
     */
    public function setOption(string $key, $value_or_object_or_string, string $configScope = null) : ConfigurationInterface;
    
    /**
     * Removes an option from the given scope eventually revealing a lower-scope value for it.
     * 
     * If you have set an option in multiple scopes (e.g. a global scope and a user scope),
     * removing it from a higher-level scope will change it's value to the one on the lower
     * level.
     * 
     * Note, that you cannot remove none-scope options! Only options, that can be changed
     * (= have a scope) can be removed!
     * 
     * @param string $key
     * @param string $configScope
     * 
     * @return ConfigurationInterface
     */
    public function unsetOption(string $key, string $configScope) : ConfigurationInterface;

    /**
     * Loads the configuration stored in a file, overriding already existing
     * values. If a configuration scope is given, the file will be accessible
     * for writing via this scope.
     * 
     * @throws OutOfBoundsException 
     *                  if the given context scope was not loaded into this 
     *                  configuration before writing to it.
     * @param string $absolute_path  
     * @param string $configScope          
     * @return ConfigurationInterface
     */
    public function loadConfigFile(string $absolute_path, string $configScope = null) : ConfigurationInterface;

    /**
     *
     * @param UxonObject $uxon            
     * @return ConfigurationInterface
     */
    public function loadConfigUxon(UxonObject $uxon) : ConfigurationInterface;
    
    /**
     * Reloads the configuration from the respective config files.
     * 
     * This will revert all changes made outside of a specific scope (= temporary changes).
     * 
     * @return ConfigurationInterface
     */
    public function reloadFiles() : ConfigurationInterface;
    
    /**
     * Returns TRUE if a config option matching the given key exists and FALSE otherwise.
     * 
     * If the optional parameter $scope is set, the value is checked only inside that
     * scope.
     * 
     * @param string $key
     * @param string $scope
     * @return boolean
     */
    public function hasOption(string $key, string $scope = null) : bool;
}

?>