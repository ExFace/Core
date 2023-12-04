<?php
namespace exface\Core\Events\Installer;

use exface\Core\Interfaces\AppInstallerInterface;

/**
 * Event fired right before an app installer starts installing.
 * 
 * @event exface.Core.Installer.OnBeforeInstallEvent
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeInstallEvent extends AbstractAppInstallerEvent
{
    private $srcPath = null;
    
    public function __construct(AppInstallerInterface $installer, string $srcPath)
    {
        parent::__construct($installer);
        $this->srcPath = $srcPath;
    }
    
    public function getSourcePath() : string
    {
        return $this->srcPath;
    }
}