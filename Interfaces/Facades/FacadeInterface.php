<?php
namespace exface\Core\Interfaces\Facades;

use exface\Core\CommonLogic\Configuration;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Interfaces\ConfigurationInterface;

interface FacadeInterface extends WorkbenchDependantInterface, AliasInterface
{
    /**
     * Returns TRUE if this facade matches the given facade alias and false otherwise (case insensitive!)
     *
     * @param string $facade_alias            
     */
    public function is($facade_alias) : bool;

    /**
     * Returns the app, that contains the facade
     *
     * @return AppInterface
     */
    public function getApp() : AppInterface;

    /**
     * Returns the configuration object for this facade.
     * By default, it is the configuration object of the app of the facade
     *
     * @return Configuration
     */
    public function getConfig() : ConfigurationInterface;
    
    /**
     * 
     * @return FacadeSelectorInterface
     */
    public function getSelector() : FacadeSelectorInterface;
}
?>