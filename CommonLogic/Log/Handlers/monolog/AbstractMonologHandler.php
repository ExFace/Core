<?php

namespace exface\Core\CommonLogic\Log\Handlers\monolog;


use exface\Core\CommonLogic\Log\LogHandlerInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use Monolog\Logger;

/**
 * Abstract base class for LogHandlers using monolog as log backend.
 *
 * @package exface\Core\CommonLogic\Log\Handlers
 */
abstract class AbstractMonologHandler implements LogHandlerInterface {
	/** @var Logger $logger */
	private $logger;

	public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		$logger = $this->getLogger();
		$logger->log($level, $message, $context);
	}

	protected function getLogger() {
		if (!$this->logger)
			$this->logger = $this->createRealLogger();

		return $this->logger;
	}

	protected abstract function createRealLogger();
}
