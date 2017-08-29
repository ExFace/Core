<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\NameResolverInstallerInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractNameResolverInstaller implements NameResolverInstallerInterface
{

    private $name_resolver = null;

    /**
     *
     * @param NameResolverInterface $name_resolver            
     */
    public function __construct(NameResolverInterface $name_resolver_to_install)
    {
        $this->name_resolver = $name_resolver_to_install;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\NameResolverInstallerInterface::getNameResolver()
     */
    public function getNameResolver()
    {
        return $this->name_resolver;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getNameResolver()->getWorkbench();
    }
}