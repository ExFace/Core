<?php
namespace exface\Core;

use exface\Core\CommonLogic\AppInstallers\ApacheServerInstaller;
use exface\Core\CommonLogic\AppInstallers\AppDocsInstaller;
use exface\Core\CommonLogic\AppInstallers\AppInstallerContainer;
use exface\Core\CommonLogic\AppInstallers\NginxServerInstaller;
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
    const CONFIG_SERVER_INSTALLER = 'INSTALLER.SERVER_INSTALLER.CLASS';
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
        
        // Server installer.
        $serverInstallerClass = $this->getServerInstallerClass($installer);
        if ($serverInstallerClass !== null) {
            $serverInstaller = new $serverInstallerClass($this->getSelector());
            $installer->addInstaller($serverInstaller);
        } else {
            $installer->addMessage('FAILED - Could not determine server installer class! Consider defining the' .
                ' server installer class explicitly by setting "' . self::CONFIG_SERVER_INSTALLER . 
                '" in "System.config.json".'
            );
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
     * @param AppInstallerContainer $installer
     * @return string|null
     */
    protected function getServerInstallerClass(AppInstallerContainer $installer) : string|null
    {
        $installer->addMessage('Determining server installer class:');
        $indent = '  ';

        // From config option.
        $cfg = $this->getWorkbench()->getConfig();
        if($cfg->hasOption(self::CONFIG_SERVER_INSTALLER)) {
            $configOption = $this->getWorkbench()->getConfig()->getOption(self::CONFIG_SERVER_INSTALLER);

            // Valid-ish config option.
            if(class_exists($configOption)) {
                $installer->addMessage($indent . 'Found installer class in config: "' . $configOption . '".');
                return $configOption;
            } 
            
            // Invalid config option.
            $installer->addMessage($indent . 'Value "' . $configOption . '" for config option "' .
                self::CONFIG_SERVER_INSTALLER . '" is not a valid class name.');
        }

        // Read from PHP constant.
        $softwareFamily = ServerSoftwareDataType::getServerSoftwareFamily();
        
        // Guess via folder structure - this is important for backwards compatibility with existing installations. 
        // Future installations should have a manually defined ``
        if(empty($softwareFamily)) {
            $path = $this->getWorkbench()->getInstallationPath();
            $installer->addMessage($indent . 'Deducing server software from installation path "' . $path . 
                '" under "' . php_uname('s') . '".');

            $softwareFamily = match (true) {
                // Microsoft IIS runs on windows and has its files mostly in c:\inetpub\wwwroot 
                ServerSoftwareDataType::isOsWindows() && preg_match('/[Cc]:\\\\inetpub\\\\wwwroot\\\\/', $path) === 1 => ServerSoftwareDataType::SERVER_SOFTWARE_IIS,
                // nginx runs on Linux/Unix only and also has wwwroot in its path
                ServerSoftwareDataType::isOsLinux() && preg_match('/\/wwwroot\//', $path) === 1 => ServerSoftwareDataType::SERVER_SOFTWARE_NGINX,
                // Apache will have www in its path while being able to run on both
                ServerSoftwareDataType::isOsWindows() && preg_match("/\\\\www\\\\/", $path) === 1 => ServerSoftwareDataType::SERVER_SOFTWARE_APACHE,
                ServerSoftwareDataType::isOsLinux() && preg_match("/\/www\//", $path) === 1 => ServerSoftwareDataType::SERVER_SOFTWARE_APACHE,
                default => null
            };

            if(empty($softwareFamily)) {
                $installer->addMessage($indent . 'Could not determine server software.');
                return null;
            } else {
                $installer->addMessage($indent . 'Server software from folder structure: "' . $softwareFamily . '".');
            }
        } else {
            $installer->addMessage($indent . 'Server software from PHP constant: "' . $softwareFamily . '".');
        }
        
        $class = match ($softwareFamily) {
            ServerSoftwareDataType::SERVER_SOFTWARE_APACHE => '\\' . ltrim(ApacheServerInstaller::class, "\\"),
            ServerSoftwareDataType::SERVER_SOFTWARE_IIS => '\\' . ltrim(IISServerInstaller::class, "\\"),
            ServerSoftwareDataType::SERVER_SOFTWARE_NGINX => '\\' . ltrim(NginxServerInstaller::class, "\\"),
            default => null
        };
        
        if($class !== null) {
            $installer->addMessage($indent . 'Deduced installer class from server software: "' . $class . '".');
        } else {
            $installer->addMessage($indent . 'Could not deduce server installer class.');
        }
        
        return $class;
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