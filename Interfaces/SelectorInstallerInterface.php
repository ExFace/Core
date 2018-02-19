<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Selectors\SelectorInterface;

interface SelectorInstallerInterface extends InstallerInterface
{

    /**
     *
     * @param SelectorInterface $name_resolver            
     */
    public function __construct(SelectorInterface $selector);

    /**
     * Returns the name resolver representing the element to install
     *
     * @return SelectorInterface
     */
    public function getSelectorInstalling();
}
?>