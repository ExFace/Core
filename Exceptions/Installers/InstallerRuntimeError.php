<?php
namespace exface\Core\Exceptions\Installers;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\InstallerExceptionInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Interfaces\AppInstallerInterface;

class InstallerRuntimeError extends RuntimeException implements InstallerExceptionInterface {
    
    private $installer = null;
    
    /**
     * 
     * @param InstallerInterface $installer
     * @param string $message
     * @param string $code
     * @param \Throwable $previous
     */
    public function __construct(InstallerInterface $installer, $message, $alias = null, $previous = null)
    {
        if ($installer instanceof AppInstallerInterface) {
            try {
                $message = 'Error installing app ' . $installer->getApp()->getAliasWithNamespace() . '.' . $message;
            } catch (\Throwable $e) {
                // ignore errors! Just leave the message as it is then.
            }
        }
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setInstaller($installer);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\InstallerExceptionInterface::getInstaller()
     */
    public function getInstaller()
    {
        return $this->installer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\InstallerExceptionInterface::setInstaller()
     */
    public function setInstaller(InstallerInterface $installer)
    {
        $this->installer = $installer;
        return $this;
    }

}