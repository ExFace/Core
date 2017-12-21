<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\InstallerInterface;

Interface InstallerExceptionInterface
{

    /**
     *
     * @param InstallerInterface $installer            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(InstallerInterface $installer, $message, $alias = null, $previous = null);

    /**
     *
     * @return InstallerInterface
     */
    public function getInstaller();

    /**
     *
     * @param InstallerInterface $installer            
     * @return InstallerExceptionInterface
     */
    public function setInstaller(InstallerInterface $installer);
}
?>