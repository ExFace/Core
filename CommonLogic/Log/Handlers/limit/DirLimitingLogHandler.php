<?php

namespace exface\Core\CommonLogic\Log\Handlers\limit;


use exface\Core\Interfaces\iCanGenerateDebugWidgets;

/**
 * Log handler that uses the given createCallback to instantiate an underlying log handler that logs files with a
 * specific file name schema to a specific directory and limits the number of days to keep the log files from to the
 * given value of maxDays.
 *
 * @package exface\Core\CommonLogic\Log\Handlers
 */
class DirLimitingLogHandler extends LimitingWrapper {
	private $logPath;
	private $fileEnding;
	private $maxDays;

	function __construct(Callable $createCallback, $logPath, $fileEnding, $maxDays = 0) {
		parent::__construct($createCallback);

		$this->logPath = $logPath;
		$this->fileEnding = $fileEnding;
		$this->maxDays  = $maxDays;
	}

	protected function callLogger(Callable $createLoggerCall, $level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		$createLoggerCall()->handle($level, $message, $context, $sender);
	}

	/**
	 * Log file cleanup.
	 */
	protected function limit() {
		// skip GC of old logs if files are unlimited
		if (0 === $this->maxDays) {
			return;
		}

		$limitTime = max(0, time() - ($this->maxDays * 24 * 60 * 60));
		$logFiles = glob($this->getGlobPattern());
		foreach ($logFiles as $logFile) {
			$mtime = filemtime($logFile);
			if ($mtime > $limitTime)
				continue;

			if (is_writable($logFile)) {
				// suppress errors here as unlink() might fail if two processes
				// are cleaning up/rotating at the same time
				set_error_handler(function ($errno, $errstr, $errfile, $errline) {});
				unlink($logFile);
				restore_error_handler();
			}
		}
	}

	protected function getGlobPattern() {
		return $this->logPath . '/*' . $this->fileEnding;
	}
}
