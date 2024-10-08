<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Factories\LoggerFactory;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\LogLevelDataType;

/**
 * Default implementation of the LoggerInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class Logger implements LoggerInterface
{
    /** @var LogHandlerInterface[] $handlers */
    private $handlers = array();

    private $queue = [];

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function emergency($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::EMERGENCY, $message, $context, $sender);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function alert($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::ALERT, $message, $context, $sender);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function critical($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::CRITICAL, $message, $context, $sender);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function error($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::ERROR, $message, $context, $sender);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function warning($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::WARNING, $message, $context, $sender);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function notice($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::NOTICE, $message, $context, $sender);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function info($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::INFO, $message, $context, $sender);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function debug($message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        $this->log(LoggerInterface::DEBUG, $message, $context, $sender);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     *
     * @return void
     */
    public function log($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null) : void
    {
        // If the current log message occurred while another was being logged, don't process it immediately
        // as this might cause recursion. We will enqueue errors and skip any other interruption.
        if ($this->isLogging()) {
            $this->enqueueError($level, $message, $context, $sender);
            return;
        }

        // Mark as "logging" now. Any errors occurring here on, will be deferred to the queue above.
        $this->setLogging([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'sender' => $sender
        ]);

        try {
            if (is_null($sender) && ($context['exception'] ?? null) instanceof iCanGenerateDebugWidgets) {
                $sender = $context['exception'];
            }

            if ($sender instanceof ExceptionInterface) {
                if (is_null($level)) {
                    $level = $sender->getLogLevel();
                }
                $context['exception'] = $sender;
                $context['id']        = $sender->getId();
            } else {
                $context['id']        = $this::generateLogId();
            }
            
            foreach ($this->handlers as $i => $handler) {
                try {
                    $handler->handle($level, $message, $context, $sender);
                } catch (\Throwable $e) {
                    try {
                        if (count($this->handlers) > 1) {
                            unset($this->handlers[$i]);
                            $this->setLogging(false);
                            $this->logException(new RuntimeException('Log handler error (handler ' . $i . ' disabled now): ' . $e->getMessage(), null, $e));
                        } else {
                            LoggerFactory::createPhpErrorLogLogger()->alert('Log handler error: ' . $e->getMessage(), ['exception' => $e]);
                        }
                    } catch (\Throwable $ee) {
                        // Log both errors to PHP error log if regular logging fails
                        error_log($e);
                        error_log($ee);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log to PHP error log if regular logging fails
            error_log($e);
        } finally {
            // clear "in logging process" mark
            $this->setLogging(false);
        }
        
        // See if logging the current error produced any errors in the queue and try to
        // process this queue now
        if (!empty($this->queue)) {
            if (count($this->queue) > 30) {
                $this->queue = [];
                $this->alert('Logger queue for errors while logging overfilled! Recurrence in error processing suspected: dumping error queue to avoid infinite loop!');
            } else {
                foreach ($this->queue as $i => $queued) {
                    if(empty($queued)) {
                        continue;
                    }

                    $this->queue[$i] = [];
                    $this->log($queued['level'], $queued['message'], $queued['context'], $queued['sender']);
                }
            }
        }

        // Empty the queue.
        unset($this->queue);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     * @param iCanGenerateDebugWidgets|null $sender
     * @return void
     */
    protected function enqueueError(string $level, string $message, array $context, ?iCanGenerateDebugWidgets $sender) : void
    {
        if (LogLevelDataType::compareLogLevels($level, self::ERROR) < 0) {
            return;
        }

        // Make sure not to enqueue duplicates in case of recursion
        if ($sender instanceof \Throwable) {
            // If the logged item is an exception, a duplicate would be the same
            // level, message, file and line number.
            foreach ($this->queue as $queued) {
                // If the already queued item is NOT an exception, it cannot be a duplicate
                if (! ($queued['sender'] instanceof \Throwable)) {
                    continue;
                }
                // Same goes for the case when it does not have the same level or message
                if ($queued['level'] !== $level && $queued['message'] !== $message) {
                    continue;
                }
                // For exceptions with equal levels and message, compare the file and line
                $queuedEx = $queued['sender'];
                if ($queuedEx->getFile() === $sender->getFile() && $queuedEx->getLine() === $sender->getLine()) {
                    return;
                }
            }
        } else {
            // For all other senders simply compare the sender: e.g. is it the same data sheet
            foreach ($this->queue as $queued) {
                if ($queued['level'] === $level && $queued['message'] === $message && $queued['sender'] === $sender) {
                    return;
                }
            }
        }

        $this->queue[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'sender' => $sender
        ];
    }

    /**
     *
     * @param \Throwable $e
     * @param null $level
     * @return LoggerInterface|Logger
     */
    public function logException(\Throwable $e, $level = null): LoggerInterface|static
    {
        if ($e instanceof ExceptionInterface){
            $this->log($level ?? $e->getLogLevel(), $e->getMessage(), [], $e);
        } else {
            $this->log($level ?? LoggerInterface::CRITICAL, $e->getMessage(), ["exception" => $e]);
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LoggerInterface::appendHandler()
     */
    public function appendHandler(LogHandlerInterface $handler): LoggerInterface|static
    {
        $this->handlers[] = $handler;
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LoggerInterface::removeHandler()
     */
    public function removeHandler(LogHandlerInterface $handler): LoggerInterface|static
    {
        foreach ($this->handlers as $i => $h){
            if ($h === $handler){
                unset($this->handlers[$i]);
            }
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LoggerInterface::getHandlers()
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Set the error currently being processed. To signal that you are done with logging
     * simply set `beingLogged` to be false or empty.
     *
     *
     * @param array|false $beingLogged
     * @return LoggerInterface
     */
    protected function setLogging(array|false $beingLogged) : LoggerInterface
    {
        if(empty($beingLogged)) {
            unset($this->queue['logging']);
        } else {
            $this->queue['logging'] = $beingLogged;
        }

        return $this;
    }

    /**
     * Get the error currently being processed, if any.
     *
     * @return array
     */
    protected function getLogging() : array
    {
        return $this->queue['logging'] ?? [];
    }

    /**
     * 
     * @return boolean
     */
    protected function isLogging() : bool
    {
        return !empty($this->queue['logging']);
    }
    
    /**
     * 
     * @return string
     */
    public static function generateLogId(): string
    {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }
}
