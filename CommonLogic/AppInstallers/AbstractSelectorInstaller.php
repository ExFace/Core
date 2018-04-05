<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\SelectorInstallerInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractSelectorInstaller implements SelectorInstallerInterface
{

    private $selector = null;

    /**
     *
     * @param SelectorInterface $selectorToInstall            
     */
    public function __construct(SelectorInterface $selectorToInstall)
    {
        $this->selector = $selectorToInstall;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\SelectorInstallerInterface::getSelectorInstalling()
     */
    public function getSelectorInstalling()
    {
        return $this->selector;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getSelectorInstalling()->getWorkbench();
    }
}