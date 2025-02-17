<?php
namespace exface\Core\Interfaces;

interface AppInstallerInterface extends InstallerInterface
{

    /**
     * Returns the app being installed.
     * 
     * @return \exface\Core\Interfaces\AppInterface
     */
    public function getApp();
}
?>