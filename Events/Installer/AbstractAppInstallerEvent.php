<?php
namespace exface\Core\Events\Installer;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\InstallerEventInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Interfaces\AppInstallerInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractAppInstallerEvent extends AbstractEvent implements InstallerEventInterface
{
    private $installer = null;
    
    /**
     * 
     * @param AppInstallerInterface $installer
     */
    public function __construct(AppInstallerInterface $installer)
    {
        $this->installer = $installer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\InstallerEventInterface::getInstaller()
     */
    public function getInstaller() : InstallerInterface
    {
        return $this->installer;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->installer->getWorkbench();
    }
}