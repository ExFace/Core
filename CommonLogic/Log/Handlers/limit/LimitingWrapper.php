<?php


namespace exface\Core\CommonLogic\Log\Handlers\limit;


use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\LogHandlerInterface;

/**
 * Abstract log handler wrapper to implement any kind of log file cleanup according to the implementation function
 * "limit".
 *
 * @package exface\Core\CommonLogic\Log\Handlers\rotation
 */
abstract class LimitingWrapper implements LogHandlerInterface {
	private $createCallback;

	/**
	 * LimitingWrapper constructor.
	 *
	 * @param Callable $createCallback callback function that create the underlying, "real" log handler
	 */
	function __construct(Callable $createCallback) {
		$this->createCallback = $createCallback;
	}

	public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		// check and possibly rotate
		$this->limit();

		$this->callLogger($this->createCallback, $level, $message, $context, $sender);
	}

	/**
	 * Create and call the underlying, "real" log handler.
	 *
	 * @param Callable $createLoggerCall callback function that create the underlying, "real" log handler
	 * @param $level
	 * @param $message
	 * @param array $context
	 * @param iCanGenerateDebugWidgets|null $sender
	 *
	 * @return mixed
	 */
	protected abstract function callLogger(Callable $createLoggerCall, $level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);

	/**
	 * Log file cleanup.
	 */
	protected abstract function limit();
}