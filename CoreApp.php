<?php
namespace exface\Core;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\Model\App;

class CoreApp extends App
{
    const CONFIG_FILENAME_SYSTEM = 'System';
    
    private $config_loaded = false;
    
    private $system_config = null;

    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);
        // Add the custom core installer, that will take care of model schema updates, etc.
        // Make sure, it runs before any other installers do.
        $installer->addInstaller(new CoreInstaller($this->getNameResolver()), true);
        return $installer;
    }
    
    /**
     * The configuration of the core consists of two parts: system config and cora app config.
     * 
     * The system config file only exists in the installation scope and cannot
     * be overridden on user level. It contains basic options required to
     * start a workbench. It can be loaded at any time - even if the workbench
     * did not finish starting yet.
     * 
     * The core app config behaves just like any other application configuration
     * and contains all the other options - i.e. those not critical for startup.
     * 
     * {@inheritDoc}
     * @see App::getConfig()
     */
    public function getConfig()
    {
        // Make sure the system config is loaded in the first place
        if (is_null($this->system_config)){
            $this->system_config = ConfigurationFactory::createFromApp($this);
            // First from the app folder (without a scope, thus not writable)
            $this->system_config->loadConfigFile($this->getConfigFolder() . DIRECTORY_SEPARATOR . $this->getConfigFileName(static::CONFIG_FILENAME_SYSTEM));
            // Then from the installation folder (with the special system scope)
            $this->system_config->loadConfigFile($this->getWorkbench()->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . $this->getConfigFileName(static::CONFIG_FILENAME_SYSTEM), AppInterface::CONFIG_SCOPE_SYSTEM);
        }
        
        // If the workbench has not been started yet, just return the system config
        if (! $this->getWorkbench()->isStarted()){
            return $this->system_config;
        } else {
            // If the workbench finished starting, load the regular core config 
            // files - but do it only once!
            if ($this->config_loaded === false){
                $this->setConfig($this->loadConfigFiles($this->system_config));
                $this->config_loaded = true;
            }
        }
        return parent::getConfig();
    }
}
?>