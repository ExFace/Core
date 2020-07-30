<?php
namespace exface\Core;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Facades\AbstractHttpFacade\HttpFacadeInstaller;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\DocsFacade;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Facades\ProxyFacade;
use exface\Core\Facades\WebConsoleFacade;
use exface\Core\CommonLogic\AppInstallers\FileContentInstaller;
use exface\Core\CommonLogic\Filemanager;

class CoreApp extends App
{
    const CONFIG_FILENAME_SYSTEM = 'System';
    
    private $config_loaded = false;
    
    private $system_config = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\App::getUid()
     */
    public function getUid()
    {
        // Hardcode the UID of the core app, because some installers might attempt to use it
        // before the model is fully functional on first time installing.
        return '0x31000000000000000000000000000000';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\App::getInstaller()
     */
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);
        
        // Add the custom core installer, that will take care of model schema updates, etc.
        // Make sure, it runs before any other installers do.
        $installer->addInstaller(new CoreInstaller($this->getSelector()), true);
        
        $htaccessInstaller = new FileContentInstaller($this->getSelector());
        $htaccessInstaller
            ->setFilePath(Filemanager::pathJoin([$this->getWorkbench()->getInstallationPath(), '.htaccess']))
            ->setFileTemplatePath('default.htaccess')
            ->setMarkerBegin("\n# BEGIN [#marker#]")
            ->setMarkerEnd('# END [#marker#]')
            ->addContent('Core URLs', "

# API requests
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/.*$ vendor/exface/Core/index.php [L,QSA]

# index request without any path
RewriteRule ^/?$ vendor/exface/Core/index.php [L,QSA]

# Requests to UI pages
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^[^/]*$ vendor/exface/Core/index.php [L,QSA]

")
            ->addContent('Core Security', "

# Block requests to config, cache, backup, etc.
RewriteRule ^(config|backup|translations|logs)/.*$ - [F]
# Block requests to system files (starting with a dot) in the data folder
RewriteRule ^data/\..*$ - [F]

");
        $installer->addInstaller($htaccessInstaller);
        
        $webconfigInstaller = new FileContentInstaller($this->getSelector());
        $webconfigInstaller
        ->setFilePath(Filemanager::pathJoin([$this->getWorkbench()->getInstallationPath(), 'Web.config']))
        ->setFileTemplatePath('default.Web.config')
        ->setMarkerBegin("\n<!-- BEGIN [#marker#] -->")
        ->setMarkerEnd("<!-- END [#marker#] -->");
        $installer->addInstaller($webconfigInstaller);
        
        // Add facade installers for core facades
        
        // HTTP file server facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(HttpFileServerFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        
        // Docs facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(DocsFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        
        // Proxy facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(ProxyFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        
        // Web console facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(WebConsoleFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        $htaccessInstaller->addContent("zlib compression off for webconsole facade\n", "
<If \"'%{THE_REQUEST}' =~ m#api/webconsole#\">
    php_flag zlib.output_compression Off
</If>
            
");
        
        $serverInstallerClass = $this->getWorkbench()->getConfig()->getOption("INSTALLER.SERVER_INSTALLER.CLASS");
        if ($serverInstallerClass != null) {
            $serverInstaller = new $serverInstallerClass($this->getSelector());
            $installer->addInstaller($serverInstaller);
        }
        
        
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