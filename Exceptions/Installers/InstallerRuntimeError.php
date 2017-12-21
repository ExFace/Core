<?php
namespace exface\Core\Exceptions\Installers;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\InstallerExceptionInterface;
use exface\Core\Interfaces\InstallerInterface;

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