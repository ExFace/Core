<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\Log\LogHandlerInterface;

interface FileHandlerInterface extends LogHandlerInterface
{

    public function setFilename($filename);
}
