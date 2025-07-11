<?php
namespace exface\Core;

use exface\Core\CommonLogic\AppInstallers\AppDocsInstaller;
use exface\Core\Facades\PermalinkFacade;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Facades\AbstractHttpFacade\HttpFacadeInstaller;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\DocsFacade;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Facades\WebConsoleFacade;
use exface\Core\CommonLogic\AppInstallers\FileContentInstaller;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Facades\HttpTaskFacade;
use exface\Core\CommonLogic\AppInstallers\SchedulerInstaller;
use exface\Core\Facades\PWAapiFacade;
use exface\Core\DataTypes\ServerSoftwareDataType;
use exface\Core\CommonLogic\AppInstallers\IISServerInstaller;

class CoreApp extends App
{
    const CONFIG_FILENAME_SYSTEM = 'System';
    
    private $config_loading = false;
    
    private $config_loaded = false;
    
    private $system_config = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\App::getUid()
     */
    public function getUid() : ?string
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
        
        // .htaccess for Apache servers
        
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
RewriteRule ^api/.*$ vendor/exface/Core/index.php [L,QSA,NC]

# Force trailing slash on requests to the root folder of the workbench
# E.g. me.com/exface -> me.com/exface/
RewriteCond %{REQUEST_URI} ^$
RewriteRule ^$ %{REQUEST_URI} [R=301]

# index request without any path
RewriteRule ^/?$ vendor/exface/Core/index.php [L,QSA]

# Requests to UI pages
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^[^/]*$ vendor/exface/Core/index.php [L,QSA]

")
            ->addContent('Core Security', "

# Block direct access to PHP scripts
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_FILENAME} !vendor/exface/core/index.php [NC]
RewriteRule ^vendor/.*\.php$ - [F,L,NC]

# Block requests to config, cache, backup, etc.
RewriteRule ^(config|backup|translations|logs)/.*$ - [F,NC]
# Block requests to system files (starting with a dot) in the data folder
RewriteRule ^data/\..*$ - [F,NC]

");
        $installer->addInstaller($htaccessInstaller);
        
        // robot.txt
        
        $robotsTxtInstaller = new FileContentInstaller($this->getSelector());
        $robotsTxtInstaller
        ->setFilePath(Filemanager::pathJoin([$this->getWorkbench()->getInstallationPath(), 'robots.txt']))
        ->setFileTemplatePath('default.robots.txt')
        ->setMarkerBegin("\n# BEGIN [#marker#]")
        ->setMarkerEnd('# END [#marker#]')
        ->addContent('Disallow robots in general', "
User-agent: *
Disallow: /
    
");
        $installer->addInstaller($robotsTxtInstaller);
        
        // Add facade installers for core facades
        
        // HTTP file server facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(HttpFileServerFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        
        // Docs facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(DocsFacade::class, $this->getWorkbench()));
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
        
        // HttpTask facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(HttpTaskFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        
        // PWA API facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(PWAapiFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);

        // Permalink facade
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade(FacadeFactory::createFromString(PermalinkFacade::class, $this->getWorkbench()));
        $installer->addInstaller($tplInstaller);
        
        // Server installer (e.g. for Microsoft IIS)
        $serverInstallerClass = $this->getWorkbench()->getConfig()->getOption("INSTALLER.SERVER_INSTALLER.CLASS");
        // Autodetect server installer if not set explicitly
        if ($serverInstallerClass === null) {
            switch (true) {
                case ServerSoftwareDataType::isServerIIS():
                    $serverInstallerClass = '\\' . ltrim(IISServerInstaller::class, "\\");
                    break;
                // TODO add installers for apache and nginx here!
            }
        }
        if ($serverInstallerClass != null) {
            $serverInstaller = new $serverInstallerClass($this->getSelector());
            $installer->addInstaller($serverInstaller);
        }
        
        // Scheduler
        $schedulerInstaller = new SchedulerInstaller($this->getSelector());
        $schedulerName = 'Workbench scheduler (' . $this->getWorkbench()->getInstallationName() . ')';
        $schedulerInstaller->addTask($schedulerName, 'exface.Core:RunScheduler', 60, true);
        $installer->addInstaller($schedulerInstaller);

        // Docs installer
        $docsInstaller = new AppDocsInstaller($this->getSelector());
        $installer->addInstaller($docsInstaller);
        
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
        if ($this->system_config === null){
            $this->system_config = ConfigurationFactory::createFromApp($this);
            // First from the app folder (without a scope, thus not writable)
            $this->system_config->loadConfigFile($this->getConfigFolder() . DIRECTORY_SEPARATOR . $this->getConfigFileName(static::CONFIG_FILENAME_SYSTEM));
            // Then from the installation folder (with the special system scope)
            $this->system_config->loadConfigFile($this->getWorkbench()->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . $this->getConfigFileName(static::CONFIG_FILENAME_SYSTEM), AppInterface::CONFIG_SCOPE_SYSTEM);
        }
        
        // Then load the core config on-top - only once and only if the workbench has already started up
        if ($this->config_loaded === false) {
            // If the workbench has not been fully started yet, just return the system config
            if (! $this->getWorkbench()->isStarted() || $this->config_loading === true){
                return $this->system_config;
            }
            
            // If the workbench finished starting, load the regular core config files - but 
            // do it only once! Also keep in mind, that loading the config the files might
            // call getConfig() again, so also prevent loops whild actually loading
            $this->config_loading = true;
            $this->setConfig($this->loadConfigFiles($this->system_config));
            $this->config_loading = false;
            $this->config_loaded = true;
        }
        return parent::getConfig();
    }
}
?>