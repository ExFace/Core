<?php
namespace exface\Core\Interfaces;

interface InstallerContainerInterface extends InstallerInterface
{

    /**
     *
     * @param InstallerInterface $installer
     * @param boolean $insertAtBeinning
     * @return InstallerContainerInterface
     */
    public function addInstaller(InstallerInterface $installer, $insertAtBeinning = false) : InstallerContainerInterface;

    /**
     * 
     * @return InstallerInterface[]
     */
    public function getInstallers() : array;

    /**
     * Returns a new installer container with only installers matching the ginve filter
     * 
     * @param callable $filterCallback
     * @return InstallerContainerInterface
     */
    public function extract(callable $filterCallback) : InstallerContainerInterface;

    /**
     * Adds a message to be displayed when performing `install()`, `backup()` or `uninstall()`.
     * 
     * NOTE: Adding a message is similar to adding an installer. Both use the same queue. Adding a message
     * just before adding an installer means, that this message will always be displayed before that installer 
     * is executed.
     *
     * @param string $message
     * @return InstallerContainerInterface
     */
    public function addMessage(string $message) : InstallerContainerInterface;
}