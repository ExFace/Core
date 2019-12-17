<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\CommonLogic\UxonObject;

/**
 * This installer registeres routes for it's HTTP facade in the system's
 * facade routing configuration (System.config.json > FACADES.ROUTES).
 * 
 * ## Initializing the installer
 * 
 * Add something like this to the `getInstaller()` method of your app class:
 * 
 * ```
 * ...preceding installers here...
 *        
 * $facadeInstaller = new HttpFacadeInstaller($this->getSelector());
 * $facadeInstaller->setFacade(FacadeFactory::createFromString(YourFacade::class, $this->getWorkbench()));
 * $installer->addInstaller($facadeInstaller);
 * 
 * ...subsequent installers here...
 * 
 * ```
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
    public function install(string $source_absolute_path) : \Iterator
    {
        $indent = $this->getOutputIndentation();
        $result = $indent . 'Facade routing for ' . $this->getFacade()->getAliasWithNamespace() . '...';
        try {
            $config = $this->getWorkbench()->getConfig();
            $routes = $config->getOption('FACADES.ROUTES');
            if (! $routes instanceof UxonObject) {
                throw new InstallerRuntimeError($this, 'Invalid routing configuration detected!');
            }
            $before = $routes->toJson();
            foreach ($this->getFacade()->getUrlRoutePatterns() as $pattern) {
                $routes->setProperty($pattern, $this->getFacade()->getAliasWithNamespace());
            }      
            if ($routes->isEmpty() === false) {
                $config->setOption('FACADES.ROUTES', $routes, App::CONFIG_SCOPE_SYSTEM);
            } else {
                yield 'failed: empty result!' . PHP_EOL;
            }
        } catch (\Throwable $e) {
            throw new InstallerRuntimeError($this, 'Failed to setup HTTP facade routing!', null, $e);
        }
        
        if ($routes->toJson() !== $before) {
            yield $result . ' updated' . PHP_EOL;
        } else {
            yield $result . ' verified' . PHP_EOL; 
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
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
        yield 'Facade URL routes uninstalled for ' . $this->getFacade()->getAliasWithNamespace();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
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