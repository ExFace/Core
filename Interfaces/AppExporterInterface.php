<?php
namespace exface\Core\Interfaces;

interface AppExporterInterface extends AppInstallerInterface
{

    /**
     * Exports the model to the folder of the app
     * 
     * @return \exface\Core\Interfaces\AppInterface
     */
    public function exportModel() : \Iterator;
}