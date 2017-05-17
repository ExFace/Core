<?php namespace exface\Core\Interfaces;

interface LogHandlerInterface {
    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
}
