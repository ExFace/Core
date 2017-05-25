<?php
namespace exface\Core;

use exface\Core\CommonLogic\AbstractApp;
use exface\Core\Interfaces\InstallerInterface;

class CoreApp extends AbstractApp
{

    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);
        // Add the custom core installer, that will take care of model schema updates, etc.
        // Make sure, it runs before any other installers do.
        $installer->addInstaller(new CoreInstaller($this->getNameResolver()), true);
        return $installer;
    }
}
?>