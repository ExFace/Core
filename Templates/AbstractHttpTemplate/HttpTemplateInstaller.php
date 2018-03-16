<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * This installer registeres routes for it's HTTP template in the system's
 * template routing configuration (System.config.json > TEMPLATE.ROUTES).
 * 
 * @author Andrej Kabachnik
 *        
 */
class HttpTemplateInstaller extends AbstractAppInstaller
{
    private $template = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install($source_absolute_path)
    {
        try {
            $config = $this->getWorkbench()->getConfig();
            $routes = $config->getOption('TEMPLATE.ROUTES');
            foreach ($this->getTemplate()->getUrlRoutePatterns() as $pattern) {
                $routes->setProperty($pattern, $this->getTemplate()->getAliasWithNamespace());
            }      
            $config->setOption('TEMPLATE.ROUTES', $routes, App::CONFIG_SCOPE_SYSTEM);
        } catch (\Throwable $e) {
            throw new InstallerRuntimeError($this, 'Failed to setup HTTP template routing!', null, $e);
        }
        
        return 'Updated HTTP template routing configuration';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::update()
     */
    public function update($source_absolute_path)
    {
        return $this->install();
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
            $routes = $config->getOption('TEMPLATE.ROUTES');
            foreach ($this->getTemplate()->getUrlRoutePatterns() as $pattern) {
                $routes->unsetProperty($pattern);
            }
            $config->setOption('TEMPLATE.ROUTES', $routes, App::CONFIG_SCOPE_SYSTEM);
        } catch (\Throwable $e) {
            throw new InstallerRuntimeError($this, 'Failed to uninstall HTTP template routes!', null, $e);
        }
        return 'Template URL routes uninstalled.';
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
     * @return HttpTemplateInterface
     */
    public function getTemplate() : HttpTemplateInterface
    {
        return $this->template;
    }
    
    /**
     * 
     * @param HttpTemplateInterface $tpl
     * @return HttpTemplateInstaller
     */
    public function setTemplate(HttpTemplateInterface $tpl) : HttpTemplateInstaller
    {
        $this->template = $tpl;
        return $this;
    }
}
?>