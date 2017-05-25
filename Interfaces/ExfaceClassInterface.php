<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\Workbench;

interface ExfaceClassInterface
{

    /**
     * Returns the instance of ExFace, this entity has been instantiated for
     *
     * @return Workbench
     */
    public function getWorkbench();
}
?>