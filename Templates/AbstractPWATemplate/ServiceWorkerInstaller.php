<?php
namespace exface\Core\Templates\AbstractPWATemplate;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * This installer uses a ServiceWorkerBuilder to generate a ServiceWorker and places it as a new resource in the CMS.
 *        
 * @author Andrej Kabachnik
 *        
 */
class ServiceWorkerInstaller extends AbstractAppInstaller
{
    private $serviceWorkerUrl = null;
    
    private $serviceWorkerBuilder = null;

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
        
        if (! $path = $this->getServiceWorkerUrl()) {
            throw new InstallerRuntimeError($this, 'Cannot create a ServiceWorker file: no export URL specified!');
        }
        
        try {
            $this->getWorkbench()->getCMS()->createResource($this->getServiceWorkerUrl(), $builder->buildJs(), true);
            $result = 'Generated ServiceWorker at ' . $path . '.';
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $result = 'Failed to generate ServiceWorker at ' . $path . ': ' . $e->getMessage() . '.';
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
        if (! $path = $this->getServiceWorkerUrl()) {
            throw new InstallerRuntimeError($this, 'Cannot remove ServiceWorker file: no path specified in installer!');
        }
        
        try {
            $this->getWorkbench()->getCMS()->deleteResource($this->getServiceWorkerUrl());
            $result = 'Removed ServiceWorker file from ' . $path . '.';
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $result = 'Could not remove ServiceWorker file from ' . $path . ': please remove the file manually!';
        }
        
        return $result;
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
    
    public function setServiceWorkerUrl(string $absolutePath) : ServiceWorkerInstaller
    {
        $this->serviceWorkerUrl = $absolutePath;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getServiceWorkerUrl() : ?string
    {
        return $this->serviceWorkerUrl;
    }
    
    public function setServiceWorkerBuilder(ServiceWorkerBuilder $builder) : ServiceWorkerInstaller
    {
        $this->serviceWorkerBuilder = $builder;
        return $this;
    }
    
    /**
     * 
     * @return ServiceWorkerBuilder|NULL
     */
    protected function getServiceWorkerBuilder() : ?ServiceWorkerBuilder
    {
        return $this->serviceWorkerBuilder;
    }
}
?>