<?php
namespace exface\Core\Facades\AbstractPWAFacade;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Factories\ConfigurationFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;

/**
 * This installer uses a ServiceWorkerBuilder to generate a ServiceWorker and places it as a new resource in the CMS.
 *        
 * @author Andrej Kabachnik
 *        
 */
class ServiceWorkerInstaller extends AbstractAppInstaller
{
    private $serviceWorkerBuilder = null;
    
    private $disabled = false;
    
    public function __construct(SelectorInterface $selectorToInstall, ServiceWorkerBuilder $builder)
    {
        parent::__construct($selectorToInstall);
        $this->serviceWorkerBuilder = $builder;
    }
    
    /**
     * 
     * @param SelectorInterface $selectorToInstall
     * @param ConfigurationInterface $config
     * @param CmsConnectorInterface $cms
     * 
     * @return ServiceWorkerInstaller
     */
    public static function fromConfig(SelectorInterface $selectorToInstall, ConfigurationInterface $config, CmsConnectorInterface $cms) : ServiceWorkerInstaller
    {
        $builder = new ServiceWorkerBuilder();
        
        foreach ($config->getOption('INSTALLER.SERVICEWORKER.ROUTES') as $id => $uxon) {
            $builder->addRouteToCache(
                $id,
                $uxon->getProperty('matcher'),
                $uxon->getProperty('strategy'),
                $uxon->getProperty('method'),
                $uxon->getProperty('description'),
                $uxon->getProperty('cacheName'),
                $uxon->getProperty('maxEntries'),
                $uxon->getProperty('maxAgeSeconds')
                );
        }
        
        if ($config->hasOption('INSTALLER.SERVICEWORKER.IMPORTS')) {
            foreach ($config->getOption('INSTALLER.SERVICEWORKER.IMPORTS') as $path) {
                $builder->addImport($cms->buildUrlToInclude($path));
            }
        }
        
        $installer = new self($selectorToInstall, $builder);
        
        if ($config->hasOption('INSTALLER.SERVICEWORKER.DISABLED')) {
            $installer->setDisabled($config->getOption('INSTALLER.SERVICEWORKER.DISABLED'));
        }
        
        return $installer;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        $indent = $this->getOutputIndentation();
        if ($this->isDisabled()) {
            $config = $this->uninstallConfig($this->getApp());
        } else {
            $config = $this->installConfig($this->getApp(), $this->getServiceWorkerBuilder());
        }
        yield $indent . $this->buildServiceWorker($config, $this->getWorkbench()->getCMS()) . PHP_EOL;
    }
    
    protected function buildServiceWorker(ConfigurationInterface $config, CmsConnectorInterface $cms) : string
    {
        $workboxUrl = $this->getWorkbench()->getCms()->buildUrlToInclude($this->getWorkbench()->getConfig()->getOption('FACADES.ABSTRACTPWAFACADE.WORKBOX_VENDOR_PATH'));
        $builder = new ServiceWorkerBuilder($workboxUrl);
        foreach ($config->exportUxonObject() as $appAlias => $code) {
            if ($appAlias === '_IMPORTS') {
                foreach ($code as $path) {
                    $builder->addImport($path);
                }
            } else {
                $builder->addCustomCode($code, $appAlias);
            }
        }
        
        try {
            $path = $cms->setServiceWorker($builder->buildJsLogic(), $builder->buildJsImports());
            $result = 'ServiceWorker "' . $path . '" generated.';
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $result = 'ERROR: Failed to generate ServiceWorker "' . $path . '": ' . $e->getMessage();
        }
        
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        $this->uninstallConfig($this->getApp());        
        return 'ServiceWorker configuration removed';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        return '';
    }
    
    /**
     * 
     * @return ServiceWorkerBuilder|NULL
     */
    protected function getServiceWorkerBuilder() : ?ServiceWorkerBuilder
    {
        return $this->serviceWorkerBuilder;
    }
    
    protected function getConfig() : ConfigurationInterface
    {
        $wb = $this->getWorkbench();
        $config = ConfigurationFactory::create($wb);
        $config->loadConfigFile($wb->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . 'ServiceWorker.config.json', $this->getConfigScope());
        return $config;
    }
    
    protected function installConfig(AppInterface $app, ServiceWorkerBuilder $swBuilder) : ConfigurationInterface
    {
        $config = $this->getConfig();
        $config->setOption($app->getAliasWithNamespace(), $swBuilder->buildJsLogic(), $this->getConfigScope());
        
        try {
            $currentImports = $config->getOption('_IMPORTS')->toArray();
        } catch (ConfigOptionNotFoundError $e) {
            $currentImports = [];
        }
        $imports = array_merge($currentImports, $swBuilder->getImports());
        $config->setOption('_IMPORTS', array_unique($imports), $this->getConfigScope());
        
        return $config;
    }
    
    protected function uninstallConfig(AppInterface $app) : ConfigurationInterface
    {
        $config = $this->getConfig();
        $config->unsetOption($app->getAliasWithNamespace(), $this->getConfigScope());
        return $config;
    }
    
    protected function getConfigScope() : string
    {
        return AppInterface::CONFIG_SCOPE_SYSTEM;   
    }
    
    protected function isDisabled() : bool
    {
        return $this->disabled;
    }
    
    public function setDisabled(bool $trueOrFalse) : ServiceWorkerInstaller
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }
}
?>