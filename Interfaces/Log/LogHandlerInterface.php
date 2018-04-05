<?php
namespace exface\Core\Interfaces\Log;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface LogHandlerInterface extends WorkbenchDependantInterface
{
    /**
     * Handles a single log message.
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets $sender
     */
    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
}
