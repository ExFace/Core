<?php namespace exface\Core\Interfaces;

interface LogHandlerInterface {
    const DEBUG = 100;
    const INFO = 200;
    const NOTICE = 250;
    const WARNING = 300;
    const ERROR = 400;
    const CRITICAL = 500;
    const ALERT = 550;
    const EMERGENCY = 600;

	public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
}
