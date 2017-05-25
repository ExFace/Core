<?php

namespace exface\Core\Interfaces;

interface InstallerInterface extends ExfaceClassInterface
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
    public function update($source_absolute_path);

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