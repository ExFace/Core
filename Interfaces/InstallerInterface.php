<?php
namespace exface\Core\Interfaces;

interface InstallerInterface extends WorkbenchDependantInterface
{

    /**
     * 
     * @triggers \exface\Core\Interfaces\Events\InstallerEventInterface
     * 
     * @return string|string[]|\Traversable
     */
    public function install($source_absolute_path);

    /**
     * 
     * @triggers \exface\Core\Interfaces\Events\InstallerEventInterface
     * 
     * @return string|string[]|\Traversable
     */
    public function backup($absolute_path);

    /**
     * 
     * @triggers \exface\Core\Interfaces\Events\InstallerEventInterface
     * 
     * @return string|string[]|\Traversable
     */
    public function uninstall();
}
?>