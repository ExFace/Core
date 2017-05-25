<?php
namespace exface\Core\Interfaces\Log;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface LogHandlerInterface
{

    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
}
