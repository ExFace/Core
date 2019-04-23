<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * This installer registeres routes for it's HTTP facade in the system's
 * facade routing configuration (System.config.json > FACADES.ROUTES).
 * 
 * @author Andrej Kabachnik
 *        
 */
class HttpFacadeInstaller extends AbstractAppInstaller
{
    private $facade = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install($source_absolute_path)
    {
        try {
            $config = $this->getWorkbench()->getConfig();
            $routes = $config->getOption('FACADES.ROUTES');
            $before = $routes->toJson();
            foreach ($this->getFacade()->getUrlRoutePatterns() as $pattern) {
                $routes->setProperty($pattern, $this->getFacade()->getAliasWithNamespace());
            }      
            $config->setOption('FACADES.ROUTES', $routes, App::CONFIG_SCOPE_SYSTEM);
        } catch (\Throwable $e) {
            throw new InstallerRuntimeError($this, 'Failed to setup HTTP facade routing!', null, $e);
        }
        
        if ($routes->toJson() === $before) {
            return 'HTTP facade routing already up to date';    
        } else {
            return 'Updated HTTP facade routing configuration';
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall()
    {
        try {
            $config = $this->getWorkbench()->getConfig();
            $routes = $config->getOption('FACADES.ROUTES');
            foreach ($this->getFacade()->getUrlRoutePatterns() as $pattern) {
                $routes->unsetProperty($pattern);
            }
            $config->setOption('FACADES.ROUTES', $routes, App::CONFIG_SCOPE_SYSTEM);
        } catch (\Throwable $e) {
            throw new InstallerRuntimeError($this, 'Failed to uninstall HTTP facade routes!', null, $e);
        }
        return 'Facade URL routes uninstalled.';
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup($destination_absolute_path)
    {
        return 'Backup not implemented for installer "' . $this->getSelectorInstalling()->getAliasWithNamespace() . '"!';
    }
    
    /**
     * 
     * @return HttpFacadeInterface
     */
    public function getFacade() : HttpFacadeInterface
    {
        return $this->facade;
    }
    
    /**
     * 
     * @param HttpFacadeInterface $tpl
     * @return HttpFacadeInstaller
     */
    public function setFacade(HttpFacadeInterface $tpl) : HttpFacadeInstaller
    {
        $this->facade = $tpl;
        return $this;
    }
}
?>