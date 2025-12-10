<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\InstallerInterface;

Interface InstallerExceptionInterface
{
    /**
     *
     * @return InstallerInterface
     */
    public function getInstaller();
}