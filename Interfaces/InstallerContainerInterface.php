<?php

namespace exface\Core\Interfaces;

interface InstallerContainerInterface extends InstallerInterface
{

    /**
     *
     * @param InstallerInterface $installer            
     * @return InstallerContainerInterface
     */
    public function addInstaller(InstallerInterface $installer);

    /**
     *
     * @return InstallerInterface[]
     */
    public function getInstallers();
}
?>