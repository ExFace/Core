<?php

namespace exface\Core\Interfaces;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\UxonObject;

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
     * Sets a single configuration value specified by the given key
     * 
     * @param string $key            
     * @param mixed $value_or_object_or_string            
     */
    public function setOption($key, $value_or_object_or_string);

    /**
     *
     * @param string $absolute_path            
     * @return ConfigurationInterface
     */
    public function loadConfigFile($absolute_path);

    /**
     *
     * @param UxonObject $uxon            
     * @return ConfigurationInterface
     */
    public function loadConfigUxon(UxonObject $uxon);
}

?>