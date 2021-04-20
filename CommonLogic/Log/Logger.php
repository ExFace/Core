<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Factories\LoggerFactory;
use exface\Core\Exceptions\RuntimeException;

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
    
    private $isLogging = false;

    /**
     * System is unusable.
     *
     * @param string $message            
     * @param array $context            
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function emergency($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
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
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function alert($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
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
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function critical($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->log(LoggerInterface::CRITICAL, $message, $context, $sender);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message            
     * @param array $context            
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function error($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
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
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function warning($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->log(LoggerInterface::WARNING, $message, $context, $sender);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message            
     * @param array $context            
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function notice($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
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
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function info($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->log(LoggerInterface::INFO, $message, $context, $sender);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message            
     * @param array $context            
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function debug($message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->log(LoggerInterface::DEBUG, $message, $context, $sender);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level            
     * @param string $message            
     * @param array $context            
     * @param iCanGenerateDebugWidgets $sender            
     *
     * @return void
     */
    public function log($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        if ($this->isLogging()) {
            return;
        }

        // mark as "in logging process"
        $this->setLogging(true);

        try {
            if (is_null($sender) && $context['exception'] instanceof iCanGenerateDebugWidgets) {
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
    }
    
    /**
     * 
     * @param \Throwable $e
     * @param string $level
     */
    public function logException(\Throwable $e, $level = null)
    {
        if ($e instanceof ExceptionInterface){
            $this->log((is_null($level) ? $e->getLogLevel() : $level), $e->getMessage(), [], $e);
        } else {
            $this->log((is_null($level) ? LoggerInterface::CRITICAL : $level), $e->getMessage(), ["exception" => $e]);
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LoggerInterface::appendHandler()
     */
    public function appendHandler(LogHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LoggerInterface::removeHandler()
     */
    public function removeHandler(LogHandlerInterface $handler)
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
    public function getHandlers()
    {
        return $this->handlers;
    }

    /**
     * 
     * @param bool $isLogging
     * @return LoggerInterface
     */
    protected function setLogging(bool $isLogging) : LoggerInterface
    {
        $this->isLogging = $isLogging;
        return $this;
    }

    /**
     * 
     * @return boolean
     */
    protected function isLogging() : bool
    {
        return $this->isLogging;
    }
    
    /**
     * 
     * @return string
     */
    public static function generateLogId()
    {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }
}
