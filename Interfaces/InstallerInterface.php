<?php
namespace exface\Core\Interfaces;

interface InstallerInterface extends WorkbenchDependantInterface
{

    /**
     * 
     * @triggers \exface\Core\Interfaces\Events\InstallerEventInterface
     * 
     * @return \Iterator
     */
    public function install(string $source_absolute_path) : \Iterator;

    /**
     * 
     * @triggers \exface\Core\Interfaces\Events\InstallerEventInterface
     * 
     * @return \Iterator
     */
    public function backup(string $absolute_path) : \Iterator;

    /**
     * 
     * @triggers \exface\Core\Interfaces\Events\InstallerEventInterface
     * 
     * @return \Iterator
     */
    public function uninstall() : \Iterator;
}
?>