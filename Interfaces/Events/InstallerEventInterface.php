<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\InstallerInterface;

interface InstallerEventInterface extends EventInterface
{
    /**
     * Returns the widget, for which the event was triggered.
     * 
     * @return InstallerInterface
     */
    public function getInstaller() : InstallerInterface;
}