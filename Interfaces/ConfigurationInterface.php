<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\OutOfBoundsException;

interface ConfigurationInterface extends ExfaceClassInterface, iCanBeConvertedToUxon
{

    /**
     * Returns a single configuration value specified by the given key
     *
     * @param string $key            
     * @return multitype
     */
    public function getOption($key);

    /**
     * Sets a single configuration value specified by the given key. If a scope
     * is defined, the value will be stored in this scope. Otherwise the value
     * will only affect the current request. 
     *
     * @param string $key     
     * @param string $configScope       
     * @param mixed $value_or_object_or_string            
     */
    public function setOption($key, $value_or_object_or_string, $configScope = null);

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
    public function loadConfigFile($absolute_path, $configScope = null);

    /**
     *
     * @param UxonObject $uxon            
     * @return ConfigurationInterface
     */
    public function loadConfigUxon(UxonObject $uxon);
    
    /**
     * Returns TRUE if a config option matching the given key exists and FALSE otherwise.
     * 
     * @param string $key
     * @return boolean
     */
    public function hasOption($key);
}

?>