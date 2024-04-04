<?php
namespace exface\Core\Interfaces\Facades;

use exface\Core\CommonLogic\Configuration;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

interface FacadeInterface extends WorkbenchDependantInterface, AliasInterface, iCanBeConvertedToUxon
{
    /**
     * Returns TRUE if this facade matches the given facade selector or is a derivative of that facade.
     *
     * @param string|FacadeSelectorInterface $aliasOrSelector            
     */
    public function is($aliasOrSelector) : bool;
    
    /**
     * 
     * @param string|FacadeSelectorInterface $selectorOrString
     * @return bool
     */
    public function isExactly($selectorOrString) : bool;

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