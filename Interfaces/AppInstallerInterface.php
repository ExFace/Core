<?php

namespace exface\Core\Interfaces;

interface AppInstallerInterface extends InstallerInterface
{

    /**
     *
     * @return \exface\Core\Interfaces\AppInterface
     */
    public function getApp();
}
?>