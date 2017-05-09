<?php

namespace exface\Core\CommonLogic\Log\Handlers;


use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\LogHandlerInterface;

class RotatingLogHandler implements LogHandlerInterface {
	private $createCallback;
	private $filename;
	private $maxFiles;
	private $filenameFormat;
	private $dateFormat;

	function __construct($createCallback, $filename, $maxFiles = 0) {
		$this->filename = $filename;
		$this->maxFiles = $maxFiles;
		$this->createCallback = $createCallback;

		$this->filenameFormat = '{filename}-{date}';
		$this->dateFormat = 'Y-m-d';
	}

	public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		// check and possibly rotate
		$this->rotate();

		$this->callLogger($level, $message, $context, $sender);
	}

	public function callLogger($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) {
		$call = $this->createCallback;  // stupid PHP
		$call($this->getTimedFilename())->handle($level, $message, $context, $sender);
	}

	protected function getTimedFilename()
	{
		$fileInfo = pathinfo($this->filename);
		$timedFilename = str_replace(
			array('{filename}', '{date}'),
			array($fileInfo['filename'], date($this->dateFormat)),
			$fileInfo['dirname'] . '/' . $this->filenameFormat
		);

		if (!empty($fileInfo['extension'])) {
			$timedFilename .= '.'.$fileInfo['extension'];
		}

		return $timedFilename;
	}

	/**
	 * Rotates the files.
	 */
	protected function rotate()
	{
		// skip GC of old logs if files are unlimited
		if (0 === $this->maxFiles) {
			return;
		}

		$logFiles = glob($this->getGlobPattern());
		if ($this->maxFiles >= count($logFiles)) {
			// no files to remove
			return;
		}

		// Sorting the files by name to remove the older ones
		usort($logFiles, function ($a, $b) {
			return strcmp($b, $a);
		});

		foreach (array_slice($logFiles, $this->maxFiles) as $file) {
			if (is_writable($file)) {
				// suppress errors here as unlink() might fail if two processes
				// are cleaning up/rotating at the same time
				set_error_handler(function ($errno, $errstr, $errfile, $errline) {});
				unlink($file);
				restore_error_handler();
			}
		}
	}

	protected function getGlobPattern()
	{
		$fileInfo = pathinfo($this->filename);
		$glob = str_replace(
			array('{filename}', '{date}'),
			array($fileInfo['filename'], '*'),
			$fileInfo['dirname'] . '/' . $this->filenameFormat
		);
		if (!empty($fileInfo['extension'])) {
			$glob .= '.'.$fileInfo['extension'];
		}

		return $glob;
	}
}
