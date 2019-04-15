<?php
namespace exface\Core\Interfaces;

interface InstallerInterface extends WorkbenchDependantInterface
{

    /**
     *
     * @return string
     */
    public function install($source_absolute_path);

    /**
     *
     * @return string
     */
    public function backup($absolute_path);

    /**
     *
     * @return string
     */
    public function uninstall();
}
?>