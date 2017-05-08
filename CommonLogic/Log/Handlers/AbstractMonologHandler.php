<?php

namespace exface\Core\CommonLogic\Log\Handlers;


use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\LogHandlerInterface;
use Monolog\Logger;

/**
 * Abstract base class for LogHandlers using monolog as log backend.
 *
 * @package exface\Core\CommonLogic\Log\Handlers
 */
abstract class AbstractMonologHandler implements LogHandlerInterface {
	protected $levelFunctions = array(
		LogHandlerInterface::DEBUG     => 'debug',
		LogHandlerInterface::INFO      => 'info',
		LogHandlerInterface::NOTICE    => 'notice',
		LogHandlerInterface::WARNING   => 'warning',
		LogHandlerInterface::ERROR     => 'error',
		LogHandlerInterface::CRITICAL  => 'critical',
		LogHandlerInterface::ALERT     => 'alert',
		LogHandlerInterface::EMERGENCY => 'ermergency'
	);

	/** @var Logger $logger */
	private $logger;

	function __construct($logger) {
		$this->logger = $logger;
	}

	public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		$fnc = $this->levelFunctions[$level];
		if ($fnc && method_exists($this->logger, $fnc)) {
			$this->logger->$fnc($message, $context);
		}
	}
}
