<?php

namespace exface\Core\CommonLogic\Log;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface LoggerInterface extends \Psr\Log\LoggerInterface {
	/**
	 * PSR 3 log levels
	 *
	 * These level values work flawlessly with monolog. If some other log mechanism/library is to be used there
	 * possibly needs to be some translation.
	 */
	const DEBUG = 'debug';
	const INFO = 'info';
	const NOTICE = 'notice';
	const WARNING = 'warning';
	const ERROR = 'error';
	const CRITICAL = 'critical';
	const ALERT = 'alert';
	const EMERGENCY = 'emergency';

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function emergency($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function alert($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function critical($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function error($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function warning($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function notice($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function info($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function debug($message, array $context = array(), iCanGenerateDebugWidgets $sender = null);
	
	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level
	 * @param string $message
	 * @param array  $context
	 * @param iCanGenerateDebugWidgets $sender
	 *
	 * @return void
	 */
	public function log($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null);

	/**
	 * Pushes a handler on to the stack.
	 * 
	 * @param LogHandlerInterface $handler
	 * @return LoggerInterface
	 */
	public function pushHandler(LogHandlerInterface $handler);
	
	/**
	 * Pops a handler from the top of the stack and returns it
	 * 
	 * @return LogHandlerInterface
	 */
	public function popHandler();
	
	/**
	 * Returns a numeric array with all log handlers currently registered
	 * 
	 * @return LogHandlerInterface[]
	 */
	public function getHandlers();
	
}
