<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Exceptions\UnderflowException;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;

class Logger implements LoggerInterface
{

    /** @var LogHandlerInterface[] $handlers */
    private $handlers = array();

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
        if (is_null($sender) && $context['exception'] instanceof iCanGenerateDebugWidgets){
            $sender = $context['exception'];
        }
        
        if ($sender instanceof ExceptionInterface){
            if (is_null($level)){
                $level = $sender->getLogLevel();
            }
            $context['exception'] = $sender;
            $context['id'] = $sender->getId();
        }
        
        foreach ($this->handlers as $handler) {
            try {
                $handler->handle($level, $message, $context, $sender);
            } catch (\Throwable $e) {
                try {
                    $this->log(LoggerInterface::ERROR, $e->getMessage(), array(), new InternalError($e->getMessage(), null, $e));
                } catch (\Throwable $ee){
                    // do nothing if even logging fails
                }
            }
        }
    }

    /**
     * Pushes a handler on to the stack.
     *
     * @param LogHandlerInterface $handler            
     * @return LoggerInterface
     */
    public function pushHandler(LogHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
        
        return $this;
    }

    /**
     * Pops a handler from the top of the stack and returns it
     *
     * @throws UnderflowException if no handlers registered
     * @return LogHandlerInterface
     */
    public function popHandler()
    {
        if (! $this->handlers) {
            throw new UnderflowException('Can not pop handler from an empty handler stack.');
        }
        
        return array_shift($this->handlers);
    }

    /**
     * Returns a numeric array with all log handlers currently registered
     *
     * @return LogHandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlers;
    }
}
