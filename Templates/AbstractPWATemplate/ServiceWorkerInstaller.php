<?php
namespace exface\Core\Templates\AbstractPWATemplate;

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
    
    private $serviceWorkerScope = '';
    
    public function __construct(SelectorInterface $selectorToInstall, ServiceWorkerBuilder $builder)
    {
        parent::__construct($selectorToInstall);
        $this->serviceWorkerBuilder = $builder;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install($source_absolute_path)
    {
        if (! $builder = $this->getServiceWorkerBuilder()) {
            throw new InstallerRuntimeError($this, 'Cannot create a ServiceWorker file: no builder class specified!');
        }
        
        $config = $this->updateConfig($this->getApp(), $builder);
        
        return $this->buildServiceWorker($config, $this->getWorkbench()->getCMS());
    }
    
    protected function buildServiceWorker(ConfigurationInterface $config, CmsConnectorInterface $cms) : string
    {
        $workboxUrl = $this->getWorkbench()->getCms()->buildUrlToInclude($this->getWorkbench()->getConfig()->getOption('TEMPLATES.ABSTRACTPWATEMPLATE.WORKBOX_VENDOR_PATH'));
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
            $result = 'Generated ServiceWorker "' . $path . '".';
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $result = 'Failed to generate ServiceWorker "' . $path . '": ' . $e->getMessage() . '.';
        }
        
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::update()
     */
    public function update($source_absolute_path)
    {
        return $this->install($source_absolute_path);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall()
    {
        $config = $this->getConfig();
        $config->unsetOption($this->getApp()->getAliasWithNamespace(), $this->getConfigScope());
        
        return 'ServiceWorker configuration removed.';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup($destination_absolute_path)
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
    
    /**
     *
     * @return string
     */
    protected function getServiceWorkerScope() : string
    {
        return $this->serviceWorkerScope;
    }
    
    /**
     * 
     * @param string $value
     * @return ServiceWorkerInstaller
     */
    public function setServiceWorkerScope(string $value) : ServiceWorkerInstaller
    {
        $this->serviceWorkerScope = $value;
        return $this;
    }
    
    protected function getConfig() : ConfigurationInterface
    {
        $wb = $this->getWorkbench();
        $config = ConfigurationFactory::create($wb);
        $config->loadConfigFile($wb->filemanager()->getPathToConfigFolder() . DIRECTORY_SEPARATOR . 'ServiceWorker.config.json', $this->getConfigScope());
        return $config;
    }
    
    protected function updateConfig(AppInterface $app, ServiceWorkerBuilder $swBuilder) : ConfigurationInterface
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
    
    protected function getConfigScope() : string
    {
        return AppInterface::CONFIG_SCOPE_SYSTEM;   
    }
}
?>