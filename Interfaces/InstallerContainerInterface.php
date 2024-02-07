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
}