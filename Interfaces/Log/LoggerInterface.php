<?php
namespace exface\Core\Interfaces\Log;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface LoggerInterface extends \Psr\Log\LoggerInterface
{

    /*
     * PSR 3 log levels
     *
     * These level values work flawlessly with monolog. If some other log mechanism/library is to be used there
     * possibly needs to be some translation.
     */
    
    /**
     * Detailed debug information
     * @var string
     */
    const DEBUG = 'debug';
    
    /**
     * Interesting events. Example: User logs in, SQL logs
     * @var string
     */
    const INFO = 'info';
    
    /**
     * Normal but significant events
     * @var string
     */
    const NOTICE = 'notice';
    
    /**
     * Exceptional occurrences that are not errors. Example: Use of deprecated 
     * APIs, poor use of an API, undesirable things that are not necessarily 
     * wrong.
     * @var string
     */
    const WARNING = 'warning';
    
    /**
     * Runtime errors that do not require immediate action but should typically 
     * be logged and monitored.
     * @var string
     */
    const ERROR = 'error';
    
    /**
     * Critical condition. Example: Application component unavailable, 
     * unexpected exception.
     * @var string
     */
    const CRITICAL = 'critical';
    
    /**
     * Action must be taken immediately. Example: Entire website down, database 
     * unavailable, etc. This should trigger the SMS alerts and wake you up.
     * @var string
     */
    const ALERT = 'alert';
    
    /**
     * System is unusable
     * @var string
     */
    const EMERGENCY = 'emergency';

    /**
     * System is unusable.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function emergency($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function alert($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function critical($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function error($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function warning($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Normal but significant events.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function notice($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function info($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Detailed debug information.
     *
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function debug($message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level            
     * @param string                        $message
     * @param array                         $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function log($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null): void;
    
    /**
     * Shortcut to logging an exception automatically figuring out the sender, message, log level, etc.
     * 
     * @param \Throwable $e
     * @param string $level
     * @return LoggerInterface
     */
    public function logException(\Throwable $e, $level = null);

    /**
     * Pushes a handler on to the stack.
     * 
     * Does nothing if the same instance is already registered. If the handler implements
     * `UniqueLogHandlerInterface` and another handler with the same key is registered, it is ignored too.
     *
     * @param LogHandlerInterface $handler            
     * @return LoggerInterface
     */
    public function appendHandler(LogHandlerInterface $handler);

    /**
     * Removes the given handler from the handler stack.
     * 
     * @param LogHandlerInterface
     * @return LoggerInterface
     */
    public function removeHandler(LogHandlerInterface $handler);

    /**
     * Returns a numeric array with all log handlers currently registered
     *
     * @return LogHandlerInterface[]
     */
    public function getHandlers();
}
